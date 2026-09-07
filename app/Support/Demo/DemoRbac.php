<?php

namespace App\Support\Demo;

use Illuminate\Support\Collection;

/**
 * Roles, permissions, domain ranks and who holds what — the Access Control
 * screen's data.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE PERMISSION LIST IS ASSEMBLED, NOT TYPED OUT
 *
 * permissions() reads the keys out of the places permissions are actually used:
 * the three navigation files and the dashboard registry. It is not a hand-kept
 * list beside them.
 *
 * A hand-kept list is wrong within a month. Somebody adds a module, writes its
 * permission key into config/navigation.php, and forgets the copy in here —
 * after which the Admin Panel cannot grant the permission that the sidebar is
 * already filtering on, and the module is invisible to everyone with no
 * explanation. Deriving it means a key cannot exist in the application and be
 * missing from the screen that assigns it.
 *
 * §5 says permissions "are seeded per module as each module is built, and the
 * Admin Panel maps them to roles". This is that, done in the front-end phase:
 * the seeder replaces the derivation, and the screen does not change.
 * ═════════════════════════════════════════════════════════════════════════════
 *
 * Local + debug only, like every other Demo class. Deleted when the real
 * `roles`, `permissions`, `role_permissions`, `user_roles` and
 * `role_domain_rank` tables (§8) land.
 */
class DemoRbac
{
    public static function enabled(): bool
    {
        return app()->environment('local') && config('app.debug');
    }

    /**
     * The five domains rank is stored per (§2.5).
     *
     * Rank exists ONLY to answer "may I act on this person" and to route
     * approvals. It never grants a permission — that is entirely
     * role_permissions — which is why this list is separate from everything
     * else on the page.
     *
     * @return array<string, string>
     */
    public static function domains(): array
    {
        return [
            'people' => 'People',
            'finance' => 'Finance',
            'work' => 'Work',
            'support' => 'Support',
            'system' => 'System',
        ];
    }

    /**
     * Every permission a ROLE may be given, grouped by module.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * `admin.*` IS NOT IN HERE, AND MUST NOT BE
     *
     * §2.1: the Admin Panel is "not a role and not assignable". It is an
     * account type reached through a single account operated by the owner, in
     * its own realm with its own session.
     *
     * Listing its permissions on a role page would offer a toggle that grants
     * nothing — realm middleware refuses /admin to a staff session whatever
     * keys it holds — and would imply the panel's authority is something a
     * person can be given a piece of. It is not. The whole point of a
     * configuration surface with no operational authority is that it is one
     * account, not a rank anybody can be promoted into.
     *
     * The keys still exist and still gate the panel's own sidebar; they are
     * simply not on this list, because this list is what a role may hold.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @return array<string, list<array{key: string, label: string, note: string}>>
     */
    public static function permissions(): array
    {
        $keys = [];

        // The staff and client sidebars. Not navigation-admin — see above.
        foreach (['navigation', 'navigation-client'] as $file) {
            foreach ((array) config($file, []) as $entry) {
                if (isset($entry['permission'])) {
                    $keys[$entry['permission']] = true;
                }
            }
        }

        // The dashboard registry, which carries the finer-grained `*.self` and
        // approval keys the sidebars have no reason to name.
        foreach (array_merge((array) config('dashboard.kpis', []), (array) config('dashboard.widgets', [])) as $entry) {
            if (isset($entry['permission'])) {
                $keys[$entry['permission']] = true;
            }
        }

        // Keys a module declares for itself rather than through a sidebar.
        foreach ([
            config('announcements.post_permission'),
            config('announcements.holiday_permission'),
        ] as $declared) {
            if (is_string($declared) && $declared !== '') {
                $keys[$declared] = true;
            }
        }

        $grouped = [];

        foreach (array_keys($keys) as $key) {
            $grouped[self::moduleOf($key)][] = [
                'key' => $key,
                'label' => self::labelFor($key),
                'note' => self::noteFor($key),
            ];
        }

        ksort($grouped);

        foreach ($grouped as $module => $entries) {
            usort($entries, fn (array $a, array $b) => strcmp($a['key'], $b['key']));
            $grouped[$module] = $entries;
        }

        return $grouped;
    }

    /**
     * `leave.approve` → Leave. `client.invoices.view` → Client portal.
     */
    protected static function moduleOf(string $key): string
    {
        if (str_starts_with($key, 'client.')) {
            return 'Client portal';
        }

        if (str_starts_with($key, 'admin.')) {
            return 'Admin Panel';
        }

        return ucfirst(str_replace('-', ' ', explode('.', $key)[0]));
    }

    protected static function labelFor(string $key): string
    {
        $parts = explode('.', $key);
        $action = end($parts);

        return match ($action) {
            'view' => str_ends_with($key, '.view.all') ? 'See everybody\'s' : 'See',
            'all' => 'See everybody\'s',
            'self' => 'See their own',
            'approve' => 'Approve',
            'reject' => 'Reject',
            'post' => 'Post',
            'holiday' => 'Declare a closure',
            'triage' => 'Triage',
            'schedule' => 'Schedule',
            default => ucfirst($action),
        };
    }

    /**
     * The sentence that matters on the toggle: what it lets somebody do, in
     * words, not the key restated.
     */
    protected static function noteFor(string $key): string
    {
        return match ($key) {
            'salary.view' => 'Everybody\'s pay. The most sensitive permission in the application.',
            'salary.self' => 'Their own payslips only.',
            'attendance.view.all' => 'The whole company\'s attendance, not just their own.',
            'attendance.reject' => 'Correct somebody else\'s record, with a reason. Never their own (§2.6).',
            'leave.approve' => 'Decide leave requests. Never their own (§2.6).',
            'announcements.holiday' => 'Close the office for a day, which rewrites that day\'s attendance for everyone.',
            'announcements.post' => 'Write to the company board.',
            'tickets.triage' => 'Assign and escalate tickets.',
            'meetings.schedule' => 'Create meetings and their calendar invitations.',
            'invoices.view' => 'What clients have been billed and what they owe.',
            default => '',
        };
    }

    /**
     * The permissions no role should hold casually — used to mark them on the
     * Access Control screen and to flag them on the overview.
     *
     * @return list<string>
     */
    public static function sensitive(): array
    {
        return [
            'salary.view',
            'leave.approve',
            'attendance.reject',
            'attendance.view.all',
            'announcements.holiday',
            'invoices.view',
        ];
    }

    /**
     * Roles, with their permission set and per-domain rank.
     *
     * Permission sets come from DemoRoles rather than being restated here —
     * that class already holds them for the dashboard's role preview, and two
     * copies would disagree within a week. This adds the facts Access Control
     * needs on top: rank, description, and who holds the role.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function roles(): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        $ranks = self::ranks();

        return collect(DemoRoles::all())->map(fn (array $role, string $key) => [
            'key' => $key,
            'name' => $role['label'],
            'description' => $role['note'],
            'permissions' => $role['permissions'],
            'ranks' => $ranks[$key] ?? array_fill_keys(array_keys(self::domains()), 0),
            'holders' => self::holdersOf($key),
        ])->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function role(string $key): ?array
    {
        return self::roles()->firstWhere('key', $key);
    }

    /**
     * Rank per domain, higher meaning more authority.
     *
     * The interesting rows are HR and System Administrator, and they are the
     * reason rank is per-domain at all (§2.5): HR outranks the System
     * Administrator in people and finance, and is outranked by them in system.
     * A single hierarchy_level integer cannot say that.
     *
     * @return array<string, array<string, int>>
     */
    protected static function ranks(): array
    {
        return [
            'ceo' => ['people' => 90, 'finance' => 90, 'work' => 90, 'support' => 80, 'system' => 40],
            'hr' => ['people' => 80, 'finance' => 70, 'work' => 30, 'support' => 20, 'system' => 10],
            'manager' => ['people' => 50, 'finance' => 30, 'work' => 70, 'support' => 40, 'system' => 10],
            'team_lead' => ['people' => 30, 'finance' => 0, 'work' => 50, 'support' => 30, 'system' => 10],
            'support' => ['people' => 10, 'finance' => 0, 'work' => 20, 'support' => 70, 'system' => 20],
            'employee' => ['people' => 10, 'finance' => 0, 'work' => 10, 'support' => 10, 'system' => 10],
            // No Employee base, no operational standing, read-only everywhere.
            'mentor' => ['people' => 0, 'finance' => 0, 'work' => 0, 'support' => 0, 'system' => 0],
        ];
    }

    /**
     * Who holds a role.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function holdersOf(string $role): Collection
    {
        $assignments = self::assignments();

        return DemoEmployees::all()
            ->filter(fn (array $e) => in_array($role, $assignments[$e['user_id']] ?? [], true))
            ->values();
    }

    /**
     * The roles one person holds. Several is normal — they stack (§2.4).
     *
     * @return list<string>
     */
    public static function rolesOf(string $employeeId): array
    {
        return self::assignments()[$employeeId] ?? [];
    }

    /**
     * ─────────────────────────────────────────────────────────────────────────
     * WHO A PERMISSION CHANGE WOULD LAND ON.
     *
     * The single most useful thing the Access Control screen can show. Ticking
     * a box on a role is action at a distance: the person doing it is looking
     * at a role name, and the consequence lands on people whose names are not
     * on the screen.
     *
     * "Grants salary.view to 4 people: Rahul Mehta, Pooja Singh, …" is the
     * sentence that stops the mistake.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function whoWouldHold(string $permission, string $role): Collection
    {
        $assignments = self::assignments();
        $roles = self::roles()->keyBy('key');

        return DemoEmployees::all()
            ->filter(function (array $employee) use ($assignments, $roles, $permission, $role) {
                $held = $assignments[$employee['user_id']] ?? [];

                if (! in_array($role, $held, true)) {
                    return false;
                }

                /*
                 * Only people who do not already hold it through another role.
                 * Roles stack as a union (§2.4), so granting salary.view to
                 * Manager changes nothing for a Manager who is also HR — and
                 * counting them would overstate the change.
                 */
                foreach ($held as $other) {
                    if ($other === $role) {
                        continue;
                    }

                    if (in_array($permission, $roles->get($other)['permissions'] ?? [], true)) {
                        return false;
                    }
                }

                return true;
            })
            ->values();
    }

    /**
     * Everyone who can do something today, however they got it.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function whoHolds(string $permission): Collection
    {
        $roles = self::roles()->keyBy('key');
        $assignments = self::assignments();

        return DemoEmployees::all()
            ->filter(function (array $employee) use ($assignments, $roles, $permission) {
                foreach ($assignments[$employee['user_id']] ?? [] as $role) {
                    if (in_array($permission, $roles->get($role)['permissions'] ?? [], true)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    /**
     * Who holds which roles.
     *
     * Deliberately shows stacking — Pooja is HR and an Employee, Rahul is a
     * Manager and a Team Lead — because a screen that only ever shows one role
     * per person hides the union rule until it bites.
     *
     * @return array<string, list<string>>
     */
    public static function assignments(): array
    {
        if (! self::enabled()) {
            return [];
        }

        return [
            'EMP001' => ['employee', 'team_lead'],
            'EMP002' => ['employee'],
            'EMP003' => ['employee'],
            'EMP004' => ['employee', 'manager'],
            'EMP005' => ['employee', 'hr'],
            'EMP006' => ['employee', 'manager'],
            'EMP007' => ['employee', 'support'],
            'EMP008' => ['employee'],
            'EMP009' => ['employee'],
            'EMP010' => ['employee'],
            'EMP011' => ['employee', 'hr'],
            'EMP012' => ['employee'],
        ];
    }
}
