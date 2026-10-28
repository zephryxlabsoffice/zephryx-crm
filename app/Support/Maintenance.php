<?php

namespace App\Support;

use App\Mail\MaintenanceMail;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Maintenance mode, switched from Admin → Maintenance (owner, 2026-10-28).
 *
 * While it is on, nobody but the Admin Panel can use the site, so an update
 * or a restore never races somebody else's write. Two ways in:
 *
 * - NOW: on until the admin turns it off. Everybody is emailed when it starts
 *   and again when the site is back.
 * - SCHEDULE: a from and a to. Everybody is emailed the window when it is set;
 *   the site closes at `from` and reopens at `to` on its own, because this
 *   server runs no background jobs to flip a switch. Ending it early from the
 *   panel emails "back" like NOW does.
 *
 * Held as one row in `company_settings` under a key the settings catalogue
 * does not know, so CompanySettings::apply never lays it over the config.
 * Times are in the application's timezone, which is the office's.
 */
class Maintenance
{
    public const KEY = 'maintenance.state';

    public const NOW = 'now';

    public const SCHEDULED = 'scheduled';

    /** Recipients per message. Everybody is in Bcc, never in To. */
    protected const BATCH = 50;

    /**
     * The stored state, or null when maintenance is off.
     *
     * @return array{mode: string, from: ?string, to: ?string, note: ?string, by: ?string}|null
     */
    public static function state(): ?array
    {
        // Read once per request: the middleware, the banner and the page all ask.
        if (request()->attributes->has(self::KEY)) {
            return request()->attributes->get(self::KEY);
        }

        try {
            $value = Schema::hasTable('company_settings')
                ? DB::table('company_settings')->where('key', self::KEY)->value('value')
                : null;
        } catch (Throwable) {
            $value = null;
        }

        $state = $value === null ? null : json_decode($value, true);

        // A schedule whose window has closed is simply over.
        if (is_array($state) && $state['mode'] === self::SCHEDULED && Carbon::parse($state['to'])->isPast()) {
            $state = null;
        }

        $state = is_array($state) ? $state : null;
        request()->attributes->set(self::KEY, $state);

        return $state;
    }

    /** Whether the site is closed right now. */
    public static function isActive(): bool
    {
        $state = self::state();

        if ($state === null) {
            return false;
        }

        return $state['mode'] === self::NOW || Carbon::parse($state['from'])->isPast();
    }

    /** A scheduled window that has not started yet, or null. */
    public static function upcoming(): ?array
    {
        $state = self::state();

        return $state !== null && $state['mode'] === self::SCHEDULED && ! self::isActive() ? $state : null;
    }

    /*
     * The switches change the state and return it; tell() sends the email
     * separately, so a mail server that refuses can never leave the site
     * half switched.
     */

    /**
     * Close the site now.
     *
     * @return array<string, mixed>
     */
    public static function startNow(User $actor, ?string $note): array
    {
        return self::put(['mode' => self::NOW, 'from' => now()->toDateTimeString(), 'to' => null, 'note' => $note, 'by' => $actor->name]);
    }

    /**
     * Set a window.
     *
     * @return array<string, mixed>
     */
    public static function schedule(Carbon $from, Carbon $to, User $actor, ?string $note): array
    {
        return self::put(['mode' => self::SCHEDULED, 'from' => $from->toDateTimeString(), 'to' => $to->toDateTimeString(), 'note' => $note, 'by' => $actor->name]);
    }

    /**
     * Turn it off: end a running maintenance, or call off one not started.
     * Returns what was switched off, with `was_active`, or null when nothing
     * was on.
     *
     * @return array<string, mixed>|null
     */
    public static function end(): ?array
    {
        $state = self::state();

        if ($state === null) {
            return null;
        }

        $state['was_active'] = self::isActive();

        DB::table('company_settings')->where('key', self::KEY)->delete();
        request()->attributes->set(self::KEY, null);

        return $state;
    }

    /**
     * May this account use the site while it is closed? Only the Admin Panel's
     * own account, which is the one doing the maintenance.
     */
    public static function exempts(?User $user): bool
    {
        return $user !== null && $user->account_type === Realm::ADMIN;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    protected static function put(array $state): array
    {
        DB::table('company_settings')->updateOrInsert(
            ['key' => self::KEY],
            ['value' => json_encode($state), 'updated_at' => now(), 'created_at' => now()],
        );

        request()->attributes->set(self::KEY, $state);

        return $state;
    }

    /**
     * Email every active staff and client account. One message per batch with
     * everybody in Bcc: nobody sees anybody else's address, and a few dozen
     * people cost a few SMTP calls rather than one each, inside a request
     * that has no queue behind it. Returns how many were emailed.
     *
     * @param  array<string, mixed>  $state
     */
    public static function tell(string $kind, array $state): int
    {
        $emails = User::query()
            ->where('status', 'active')
            ->whereIn('account_type', [Realm::STAFF, Realm::CLIENT])
            ->whereNotNull('email')
            ->pluck('email')
            ->map(fn (string $e) => trim($e))
            ->filter()
            ->unique()
            ->values();

        foreach ($emails->chunk(self::BATCH) as $batch) {
            Mail::to(config('mail.from.address'))
                ->bcc($batch->all())
                ->send(new MaintenanceMail($kind, $state['from'] ?? null, $state['to'] ?? null, $state['note'] ?? null));
        }

        return $emails->count();
    }
}
