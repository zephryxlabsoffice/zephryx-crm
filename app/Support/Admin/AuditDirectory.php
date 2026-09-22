<?php

namespace App\Support\Admin;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The audit log (§6), read from `audit_log`.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THIS CLASS HAS NO WRITE, AND NEITHER DOES THE SCREEN ABOVE IT
 *
 * No edit, no delete, no "clear old entries", no bulk actions. Not because they
 * are unbuilt — an audit log with a delete button is not an audit log, and the
 * first thing anybody covering their tracks would reach for is the control that
 * tidies the evidence.
 *
 * The Admin Panel is the most powerful account in the application and the one
 * whose actions most need a record. Its own log being unalterable BY IT is the
 * point. Retention, if it is ever needed, is a scheduled job with its own audit
 * trail, never a control on this page.
 *
 * Writing is App\Support\Audit\AuditLog, which has `record()` and nothing else.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE FILTER IS BUILT FROM THE ACTIONS PRESENT, NOT FROM A FIXED LIST
 *
 * The demo source named five kinds — authentication, permissions, settings,
 * accounts, master data — because those were the five §6 calls out and the log
 * held nothing else. It holds a great deal else now: every module write since
 * the backend phase began.
 *
 * A five-entry filter over a log with sixty action types is worse than no
 * filter: four fifths of the page would be unreachable through it, and the
 * counts beside the tabs would not add up to the total. So the kinds are
 * derived from the `action` vocabulary, and a module that adds an action
 * appears in the filter without anybody remembering to add it.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class AuditDirectory
{
    /**
     * The tone each group of actions is drawn in.
     *
     * Keyed by the action's first segment, which is the module. The five §6
     * categories keep the colours they had; everything else is a record change
     * and shares one, because a page where every row is a different colour is a
     * page with no emphasis in it.
     *
     * @var array<string, array{label: string, tone: string}>
     */
    protected const GROUPS = [
        'auth' => ['label' => 'Authentication', 'tone' => 'pill-gray'],
        'permission' => ['label' => 'Permissions', 'tone' => 'pill-red'],
        'setting' => ['label' => 'Settings', 'tone' => 'pill-amber'],
        'account' => ['label' => 'Accounts', 'tone' => 'pill-blue'],
        'master' => ['label' => 'Master data', 'tone' => 'pill-indigo'],
    ];

    /**
     * Actions that record something NOT working.
     *
     * Marked on the row and pulled onto the overview, because a refused sign-in
     * is the one entry somebody wants to see without having gone looking.
     *
     * @var list<string>
     */
    protected const FAILURES = [
        'auth.sign_in_refused',
        'auth.otp_failed',
        'auth.remember_theft_detected',
        'meeting.event_failed',
        'meeting.cancel_failed',
    ];

    /**
     * Which bucket an action belongs to.
     *
     * `admin.*` splits by its second segment: permission, setting, account and
     * master data are four different things to filter on, and §6 names them
     * separately for that reason. Everything else groups by its module.
     */
    public static function kindOf(string $action): string
    {
        $parts = explode('.', $action);

        if ($parts[0] === 'admin') {
            return match ($parts[1] ?? '') {
                'permission_changed', 'role_created' => 'permission',
                'setting_changed' => 'setting',
                'account_changed', 'account_password_reset_forced',
                'account_sessions_revoked', 'account_devices_untrusted' => 'account',
                'master_data_changed' => 'master',
                default => 'admin',
            };
        }

        return $parts[0];
    }

    /**
     * The filter's tabs — every kind the log actually holds.
     *
     * @return array<string, array{label: string, tone: string}>
     */
    public static function kinds(): array
    {
        $present = DB::table('audit_log')
            ->distinct()
            ->pluck('action')
            ->map(fn (string $action) => self::kindOf($action))
            ->unique();

        $kinds = [];

        // The §6 five first and in their stated order, so the filter does not
        // reshuffle itself as the log fills.
        foreach (self::GROUPS as $key => $meta) {
            if ($present->contains($key)) {
                $kinds[$key] = $meta;
            }
        }

        foreach ($present->reject(fn (string $k) => isset(self::GROUPS[$k]))->sort() as $key) {
            $kinds[$key] = ['label' => ucfirst(str_replace('_', ' ', $key)), 'tone' => 'pill-gray'];
        }

        return $kinds;
    }

    /**
     * @return array{label: string, tone: string}
     */
    public static function kind(string $key): array
    {
        return self::kinds()[$key]
            ?? self::GROUPS[$key]
            ?? ['label' => ucfirst(str_replace('_', ' ', $key)), 'tone' => 'pill-gray'];
    }

    /**
     * Newest first, optionally one kind.
     *
     * @return Builder
     */
    public static function query(?string $kind = null): Builder
    {
        $query = DB::table('audit_log')->orderByDesc('id');

        if ($kind !== null) {
            /*
             * Filtered on the action prefix in SQL rather than by pulling the
             * table into PHP and mapping it. The log is the one table in this
             * application with no upper bound on its size.
             */
            $query->where(function (Builder $q) use ($kind) {
                foreach (self::actionsFor($kind) as $action) {
                    $q->orWhere('action', $action);
                }
            });
        }

        return $query;
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public static function paginate(?string $kind, int $perPage): LengthAwarePaginator
    {
        return self::query($kind)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (object $row) => self::row($row));
    }

    public static function find(string $reference): ?array
    {
        $id = (int) str_replace('AUD-', '', $reference);

        $row = DB::table('audit_log')->find($id);

        return $row === null ? null : self::row($row);
    }

    /**
     * @return array<string, int>
     */
    public static function counts(): array
    {
        $byAction = DB::table('audit_log')
            ->selectRaw('action, count(*) as n')
            ->groupBy('action')
            ->pluck('n', 'action');

        $counts = ['total' => (int) $byAction->sum()];

        foreach (array_keys(self::kinds()) as $kind) {
            $counts[$kind] = 0;
        }

        foreach ($byAction as $action => $n) {
            $kind = self::kindOf($action);
            $counts[$kind] = ($counts[$kind] ?? 0) + (int) $n;
        }

        return $counts;
    }

    /**
     * The entries worth noticing without being asked.
     *
     * Refused sign-ins and permission grants. Everything else is normal
     * traffic, and an overview that surfaced all of it would surface nothing.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function notable(int $limit = 5): Collection
    {
        return collect(DB::table('audit_log')
            ->where(fn (Builder $q) => $q
                ->whereIn('action', self::FAILURES)
                ->orWhere('action', \App\Support\Audit\AuditLog::PERMISSION_CHANGED))
            ->orderByDesc('id')
            ->limit($limit)
            ->get())
            ->map(fn (object $row) => self::row($row));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function recent(int $limit = 6): Collection
    {
        return collect(DB::table('audit_log')->orderByDesc('id')->limit($limit)->get())
            ->map(fn (object $row) => self::row($row));
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE ROW
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * @return array<string, mixed>
     */
    public static function row(object $entry): array
    {
        return [
            // Prefixed, so an audit reference is never a bare primary key in a
            // URL that could be mistaken for anything else's.
            'id' => 'AUD-'.$entry->id,
            'kind' => self::kindOf($entry->action),
            'at' => Carbon::parse($entry->created_at),
            /*
             * The actor's name as it was WHEN THIS HAPPENED. `actor_label` is
             * denormalised at write time precisely so an entry whose account
             * was later removed does not read "somebody changed a permission".
             */
            'actor' => $entry->actor_label ?? 'Unknown',
            'actor_type' => $entry->actor_type ?? 'unknown',
            'action' => self::describe($entry->action),
            'entity' => $entry->entity_type === null
                ? '—'
                : $entry->entity_type.':'.($entry->entity_id ?? '—'),
            'before' => self::readable($entry->before_json),
            'after' => self::readable($entry->after_json),
            'ip' => $entry->ip_address ?? '—',
            'agent' => self::agent($entry->user_agent),
            'failed' => in_array($entry->action, self::FAILURES, true),
        ];
    }

    /**
     * The action as a sentence.
     *
     * `admin.permission_changed` reads as "Permission changed". The key itself
     * is on the page too — this is the line somebody scans, not the one they
     * quote.
     */
    public static function describe(string $action): string
    {
        $parts = explode('.', $action);

        return ucfirst(str_replace('_', ' ', array_pop($parts) ?? $action))
            .' — '.implode('.', $parts);
    }

    /**
     * Every action that maps to one kind.
     *
     * Derived from the vocabulary rather than listed, so a module adding an
     * action becomes filterable without anybody remembering this method.
     *
     * @return list<string>
     */
    protected static function actionsFor(string $kind): array
    {
        return DB::table('audit_log')
            ->distinct()
            ->pluck('action')
            ->filter(fn (string $action) => self::kindOf($action) === $kind)
            ->values()
            ->all();
    }

    /**
     * A stored before/after, as something to read.
     *
     * Both are json, and both are usually a string that was written to be read
     * — see AuditLog's note on why `after` carries the EFFECT and not only the
     * value. An array is flattened rather than printed as json: this page is
     * for a person, and `{"days":47}` is a value somebody has to decode.
     */
    protected static function readable(?string $json): string
    {
        if ($json === null) {
            return '';
        }

        $value = json_decode($json, true);

        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            return implode(' · ', array_map(
                fn ($key, $item) => is_scalar($item) ? $key.': '.$item : (string) $key,
                array_keys($value),
                $value,
            ));
        }

        return $value === null ? '' : (string) $value;
    }

    /**
     * A user agent, shortened to what somebody can actually use.
     *
     * The raw string is 120 characters of version numbers and platform tokens,
     * and the question this column answers is "was that my laptop". Truncated
     * rather than parsed: a browser-detection library would be a dependency and
     * a set of wrong answers about anything new.
     */
    protected static function agent(?string $agent): string
    {
        if ($agent === null || $agent === '') {
            return '—';
        }

        return mb_strimwidth($agent, 0, 60, '…');
    }
}
