<?php

namespace App\Support\Demo;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The audit log (foundation spec §6): authentication events, permission
 * changes, and every Admin Panel action.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THIS LOG IS APPEND-ONLY, AND THERE IS NO SCREEN THAT SAYS OTHERWISE
 *
 * No edit, no delete, no "clear old entries", no bulk actions. Not because the
 * write is unbuilt — because an audit log with a delete button is not an audit
 * log, and the first thing anybody covering their tracks would reach for is the
 * button that tidies the evidence.
 *
 * The Admin Panel is the most powerful account in the application and the one
 * whose actions most need a record. Its own log being unalterable BY IT is the
 * point, not an oversight. Retention, if it is ever needed, is a scheduled job
 * with its own audit trail — never a control on this page.
 * ═════════════════════════════════════════════════════════════════════════════
 *
 * §6 requires actor, action, entity, before/after, IP, user agent and
 * timestamp on every entry. The shape below carries all of them, so the real
 * table is a straight swap.
 */
class DemoAudit
{
    public const AUTH = 'auth';
    public const PERMISSION = 'permission';
    public const SETTING = 'setting';
    public const ACCOUNT = 'account';
    public const MASTER = 'master';

    public static function enabled(): bool
    {
        return app()->environment('local') && config('app.debug');
    }

    /**
     * @return array<string, array{label: string, tone: string}>
     */
    public static function kinds(): array
    {
        return [
            self::AUTH => ['label' => 'Authentication', 'tone' => 'pill-gray'],
            self::PERMISSION => ['label' => 'Permissions', 'tone' => 'pill-red'],
            self::SETTING => ['label' => 'Settings', 'tone' => 'pill-amber'],
            self::ACCOUNT => ['label' => 'Accounts', 'tone' => 'pill-blue'],
            self::MASTER => ['label' => 'Master data', 'tone' => 'pill-indigo'],
        ];
    }

    public static function kind(string $key): array
    {
        return self::kinds()[$key] ?? ['label' => ucfirst($key), 'tone' => 'pill-gray'];
    }

    /**
     * Newest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function all(): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        return collect(self::rows())
            ->map(fn (array $row) => $row + [
                'at' => Carbon::now()->subMinutes($row['ago']),
            ])
            ->sortByDesc('at')
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function ofKind(?string $kind): Collection
    {
        return $kind === null ? self::all() : self::all()->where('kind', $kind)->values();
    }

    public static function find(string $id): ?array
    {
        return self::all()->firstWhere('id', $id);
    }

    /**
     * @return array<string, int>
     */
    public static function counts(): array
    {
        $all = self::all();

        $counts = ['total' => $all->count()];

        foreach (array_keys(self::kinds()) as $kind) {
            $counts[$kind] = $all->where('kind', $kind)->count();
        }

        return $counts;
    }

    /**
     * The entries worth noticing without being asked — failed sign-ins and
     * permission grants. Everything else is normal traffic.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function notable(int $limit = 5): Collection
    {
        return self::all()
            ->filter(fn (array $row) => $row['kind'] === self::PERMISSION || ($row['failed'] ?? false))
            ->take($limit)
            ->values();
    }

    /**
     * `ago` is minutes, so the log reads as recent rather than drifting into
     * last year the way fixed dates do.
     *
     * Note the `after` on the settings entries: it records what the change DID,
     * not only what the value became. "half_day_hours 4 → 6" is true and
     * useless; the reclassification is the thing somebody comes to this log
     * looking for. See App\Support\Admin\Retroactive.
     *
     * @return list<array<string, mixed>>
     */
    protected static function rows(): array
    {
        return [
            [
                'id' => 'AUD-4021', 'kind' => self::PERMISSION, 'ago' => 35,
                'actor' => 'Owner', 'actor_type' => 'admin',
                'action' => 'Granted salary.view to HR',
                'entity' => 'role:hr',
                'before' => 'HR did not hold salary.view',
                'after' => 'Granted to 2 people: Pooja Singh, Meera Iyer',
                'ip' => '203.0.113.24', 'agent' => 'Chrome 141 · Windows',
            ],
            [
                'id' => 'AUD-4020', 'kind' => self::SETTING, 'ago' => 96,
                'actor' => 'Owner', 'actor_type' => 'admin',
                'action' => 'Changed attendance.half_day_hours',
                'entity' => 'setting:attendance.half_day_hours',
                'before' => '4',
                'after' => '4.5 — reclassified 6 days across 3 people',
                'ip' => '203.0.113.24', 'agent' => 'Chrome 141 · Windows',
            ],
            [
                'id' => 'AUD-4019', 'kind' => self::ACCOUNT, 'ago' => 180,
                'actor' => 'Owner', 'actor_type' => 'admin',
                'action' => 'Suspended account EMP012',
                'entity' => 'user:EMP012',
                'before' => 'active',
                'after' => 'suspended',
                'ip' => '203.0.113.24', 'agent' => 'Chrome 141 · Windows',
            ],
            [
                'id' => 'AUD-4018', 'kind' => self::AUTH, 'ago' => 240, 'failed' => true,
                'actor' => 'dev.chatterjee@zephryxlabs.com', 'actor_type' => 'unknown',
                'action' => 'Sign-in refused — account inactive',
                'entity' => 'user:EMP012',
                'before' => '', 'after' => '',
                'ip' => '198.51.100.77', 'agent' => 'Safari 18 · iOS',
            ],
            [
                'id' => 'AUD-4017', 'kind' => self::MASTER, 'ago' => 420,
                'actor' => 'Owner', 'actor_type' => 'admin',
                'action' => 'Deactivated department "Operations"',
                'entity' => 'department:OPS',
                'before' => 'active',
                'after' => 'inactive — 0 employees, so nothing was reassigned',
                'ip' => '203.0.113.24', 'agent' => 'Chrome 141 · Windows',
            ],
            [
                'id' => 'AUD-4016', 'kind' => self::AUTH, 'ago' => 640,
                'actor' => 'Owner', 'actor_type' => 'admin',
                'action' => 'Signed in from a new device',
                'entity' => 'user:owner',
                'before' => '', 'after' => 'Device trusted for 30 days',
                'ip' => '203.0.113.24', 'agent' => 'Chrome 141 · Windows',
            ],
            [
                'id' => 'AUD-4015', 'kind' => self::PERMISSION, 'ago' => 1450,
                'actor' => 'Owner', 'actor_type' => 'admin',
                'action' => 'Revoked invoices.view from Support Associate',
                'entity' => 'role:support',
                'before' => 'Support Associate held invoices.view',
                'after' => 'Revoked from 1 person: Anjali Desai',
                'ip' => '203.0.113.24', 'agent' => 'Chrome 141 · Windows',
            ],
            [
                'id' => 'AUD-4014', 'kind' => self::AUTH, 'ago' => 2880, 'failed' => true,
                'actor' => 'admin@zephryxlabs.in', 'actor_type' => 'unknown',
                'action' => 'Sign-in refused — wrong password',
                'entity' => 'user:owner',
                'before' => '', 'after' => '',
                'ip' => '198.51.100.14', 'agent' => 'Firefox 133 · Linux',
            ],
            [
                'id' => 'AUD-4013', 'kind' => self::SETTING, 'ago' => 4320,
                'actor' => 'Owner', 'actor_type' => 'admin',
                'action' => 'Changed zephryx.support.email',
                'entity' => 'setting:zephryx.support.email',
                'before' => 'help@zephryxlabs.in',
                'after' => 'admin@zephryxlabs.in',
                'ip' => '203.0.113.24', 'agent' => 'Chrome 141 · Windows',
            ],
        ];
    }
}
