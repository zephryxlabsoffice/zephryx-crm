<?php

namespace App\Support;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * What went wrong, for whom, and a code they can quote (owner, 2026-11-07).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THREE KINDS OF EVENT
 *
 * - error: a server error (the 500 page). The page shows the code.
 * - expired: a form sent after its page expired (the 419 page).
 * - refused: an action the application turned down with a message, such as a
 *   check-out with no check-in to close. Not a crash, but exactly what somebody
 *   means by "it would not let me".
 *
 * When somebody raises a ticket, their events from the last two days go on it as
 * an internal note, so whoever picks it up starts from what actually happened.
 * Admin > Diagnostics lists them and downloads them with the server's log.
 *
 * NOTHING HERE MAY BREAK THE REQUEST IT IS DESCRIBING
 *
 * Every write is wrapped: a diagnostics table that is missing (before the
 * update is applied) or a database that is down must not turn one error into
 * two. Request bodies are never stored, so a password cannot end up here.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class Diagnostics
{
    public const ERROR = 'error';

    public const EXPIRED = 'expired';

    public const REFUSED = 'refused';

    /** How far back a new ticket looks. */
    public const ATTACH_HOURS = 48;

    /** How long events are kept. */
    public const KEEP_DAYS = 30;

    protected static ?string $lastCode = null;

    protected static ?bool $ready = null;

    public static function ready(): bool
    {
        try {
            return self::$ready ??= Schema::hasTable('diagnostic_events');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The code of the last event recorded in this request, for the error page.
     */
    public static function lastCode(): ?string
    {
        return self::$lastCode;
    }

    public static function recordException(Throwable $e, string $kind = self::ERROR, ?int $status = null): ?string
    {
        return self::record($kind, Str::limit($e->getMessage() !== '' ? $e->getMessage() : class_basename($e), 990), $status, $e);
    }

    /**
     * Write one event. Returns its code, or null when it could not be written.
     */
    public static function record(string $kind, string $message, ?int $status = null, ?Throwable $e = null): ?string
    {
        if (! self::ready()) {
            return null;
        }

        try {
            // A cron job has no request worth naming; a test request does.
            $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();
            $code = 'ERR-'.strtoupper(Str::random(6));

            DB::table('diagnostic_events')->insert([
                'code' => $code,
                'user_id' => $request?->user()?->id,
                'kind' => $kind,
                'method' => $request?->method() ?? 'CLI',
                'path' => Str::limit($request !== null ? '/'.ltrim($request->path(), '/') : 'console', 250),
                'status' => $status,
                'message' => Str::limit($message, 990),
                'exception' => $e === null ? null : Str::limit($e::class, 250),
                'location' => $e === null ? null : Str::limit(Str::after($e->getFile(), base_path()).':'.$e->getLine(), 250),
                'created_at' => Carbon::now(),
            ]);

            return self::$lastCode = $code;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Somebody's events from the last two days.
     *
     * @return Collection<int, object>
     */
    public static function recentFor(?User $user, int $hours = self::ATTACH_HOURS): Collection
    {
        if ($user === null || ! self::ready()) {
            return collect();
        }

        return DB::table('diagnostic_events')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', Carbon::now()->subHours($hours))
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }

    /**
     * Put the raiser's recent events on a new ticket as an internal note. Staff
     * read it; a client never does (internal notes do not reach the portal).
     */
    public static function attachTo(Ticket $ticket, ?User $raiser): void
    {
        try {
            $events = self::recentFor($raiser);

            if ($events->isEmpty()) {
                return;
            }

            TicketComment::create([
                'ticket_id' => $ticket->id,
                'author_id' => null,
                'author_label' => 'System',
                'author_role' => 'Diagnostics',
                'body' => "What went wrong for {$raiser->name} in the last ".self::ATTACH_HOURS." hours:\n"
                    .$events->map(fn (object $event) => self::line($event))->implode("\n"),
                'visibility' => TicketComment::INTERNAL,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public static function line(object $event): string
    {
        return '- '.$event->code.', '.Carbon::parse($event->created_at)->format('d M, g:i A').', '
            .$event->kind.', '.$event->method.' '.$event->path.': '.$event->message
            .($event->location ? ' ('.$event->location.')' : '');
    }

    /**
     * The download: every event of the last week, then the end of the server's
     * own log, which has the full stack traces.
     */
    public static function export(): string
    {
        $events = ! self::ready() ? collect() : DB::table('diagnostic_events')
            ->leftJoin('users', 'users.id', '=', 'diagnostic_events.user_id')
            ->where('diagnostic_events.created_at', '>=', Carbon::now()->subDays(7))
            ->orderByDesc('diagnostic_events.id')
            ->get(['diagnostic_events.*', 'users.user_id as staff_id', 'users.name as user_name']);

        $out = 'Zephryx CRM diagnostics, '.Carbon::now()->format('d M Y, g:i A')."\n\n";
        $out .= '== Events, last 7 days ('.$events->count().") ==\n";

        foreach ($events as $event) {
            $out .= self::line($event).' [user '.($event->staff_id ?? $event->user_name ?? 'none').']'
                .($event->exception ? ' '.$event->exception : '')."\n";
        }

        foreach (self::logFiles() as $file) {
            $out .= "\n== ".basename($file)." (last 400 KB) ==\n".self::tail($file, 400 * 1024);
        }

        return $out;
    }

    /**
     * The newest two of the application's own log files.
     *
     * @return list<string>
     */
    protected static function logFiles(): array
    {
        $files = glob(storage_path('logs/laravel*.log')) ?: [];
        usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        return array_slice($files, 0, 2);
    }

    protected static function tail(string $file, int $bytes): string
    {
        $size = (int) @filesize($file);
        $handle = @fopen($file, 'rb');

        if ($handle === false) {
            return "(could not be read)\n";
        }

        fseek($handle, max(0, $size - $bytes));
        $text = (string) stream_get_contents($handle);
        fclose($handle);

        return $text;
    }

    /**
     * Keep a month. Run by the nightly backup job.
     */
    public static function prune(): void
    {
        if (! self::ready()) {
            return;
        }

        try {
            DB::table('diagnostic_events')->where('created_at', '<', Carbon::now()->subDays(self::KEEP_DAYS))->delete();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
