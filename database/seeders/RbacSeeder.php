<?php

namespace Database\Seeders;

use App\Models\Domain;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Rbac\Rbac;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds roles, permissions, domains and ranks (foundation spec §5, §8).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE PERMISSION LIST IS DERIVED, NOT TYPED OUT
 *
 * §5: permissions "are seeded per module as each module is built, and the Admin
 * Panel maps them to roles."
 *
 * Rather than a hand-written list that drifts, the keys are read out of the
 * places they are actually used — the three navigation files, the dashboard
 * registry, and the modules that declare their own. A key cannot exist in the
 * application and be missing from the table that grants it, because the table
 * is built from the application.
 *
 * The alternative fails quietly and in the worst direction: somebody adds a
 * module, wires its permission into config/navigation.php, forgets the seeder,
 * and the sidebar entry is invisible to everybody with no error anywhere.
 * ═════════════════════════════════════════════════════════════════════════════
 *
 * Idempotent. Run it again after adding a module and it adds the new keys and
 * leaves every existing grant alone — re-seeding must never silently revoke
 * somebody's access.
 */
class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $this->domains();
        $this->permissions();
        $this->roles();
    }

    protected function domains(): void
    {
        foreach ([
            Domain::PEOPLE => 'People',
            Domain::FINANCE => 'Finance',
            Domain::WORK => 'Work',
            Domain::SUPPORT => 'Support',
            Domain::SYSTEM => 'System',
        ] as $key => $name) {
            Domain::updateOrCreate(['domain_key' => $key], ['domain_name' => $name]);
        }
    }

    /**
     * Every permission key the application uses.
     *
     * `admin.*` and `client.*` are seeded too, even though no role may hold
     * them — the Admin Panel and the client portal grant theirs implicitly by
     * account type (see Rbac::ADMIN_BASE and Rbac::CLIENT_BASE). They are here
     * so the catalogue is complete and so a permission can never be referenced
     * by a page without existing as a row.
     */
    protected function permissions(): void
    {
        foreach ($this->permissionKeys() as $key) {
            Permission::updateOrCreate(
                ['permission_key' => $key],
                [
                    'permission_name' => $this->nameFor($key),
                    'module' => explode('.', $key)[0],
                    'is_sensitive' => in_array($key, self::SENSITIVE, true),
                ],
            );
        }
    }

    /**
     * The permissions no role should hold casually.
     *
     * A column on the row rather than a list the UI keeps, so marking a new
     * permission sensitive is data and not a deploy.
     *
     * @var list<string>
     */
    public const SENSITIVE = [
        'salary.view',
        'leave.approve',
        'attendance.reject',
        'attendance.view.all',
        'announcements.holiday',
        'invoices.view',
    ];

    /**
     * @return list<string>
     */
    protected function permissionKeys(): array
    {
        $keys = [];

        foreach (['navigation', 'navigation-client', 'navigation-admin'] as $file) {
            foreach ((array) config($file, []) as $entry) {
                if (isset($entry['permission'])) {
                    $keys[$entry['permission']] = true;
                }
            }
        }

        foreach (array_merge((array) config('dashboard.kpis', []), (array) config('dashboard.widgets', [])) as $entry) {
            if (isset($entry['permission'])) {
                $keys[$entry['permission']] = true;
            }
        }

        // Modules that declare a key without putting it in a sidebar.
        foreach ([config('announcements.post_permission'), config('announcements.holiday_permission')] as $declared) {
            if (is_string($declared) && $declared !== '') {
                $keys[$declared] = true;
            }
        }

        // The implicit bases. They are not grantable, but they are permissions,
        // and a page asking for one must find a row behind it.
        foreach ([Rbac::EMPLOYEE_BASE, Rbac::CLIENT_BASE, Rbac::ADMIN_BASE] as $base) {
            foreach ($base as $key) {
                $keys[$key] = true;
            }
        }

        ksort($keys);

        return array_keys($keys);
    }

    protected function nameFor(string $key): string
    {
        $parts = explode('.', $key);
        $action = end($parts);

        $verb = match ($action) {
            'view' => str_ends_with($key, '.view.all') ? "See everybody's" : 'See',
            'all' => "See everybody's",
            'self' => 'See their own',
            'approve' => 'Approve',
            'reject' => 'Reject',
            'post' => 'Post',
            'holiday' => 'Declare a closure',
            'triage' => 'Triage',
            'schedule' => 'Schedule',
            default => ucfirst($action),
        };

        return $verb.' — '.$key;
    }

    /**
     * The roles, their grants and their per-domain rank.
     *
     * Permission sets are written as the modules they cover, not key by key:
     * "everything in Leave, plus approving it" is the sentence somebody
     * actually means, and a list of thirty literals is where a typo hides.
     */
    protected function roles(): void
    {
        $all = Permission::pluck('id', 'permission_key');
        $domains = Domain::pluck('id', 'domain_key');

        foreach ($this->roleDefinitions() as $key => $definition) {
            $role = Role::updateOrCreate(
                ['role_key' => $key],
                [
                    'role_name' => $definition['name'],
                    'description' => $definition['description'],
                    'is_active' => true,
                ],
            );

            $ids = collect($definition['permissions'])
                ->map(fn (string $permission) => $all[$permission] ?? null)
                ->filter()
                ->all();

            /*
             * syncWithoutDetaching, not sync: re-running the seeder after
             * adding a module must add the new keys and leave every existing
             * grant alone. A seeder that silently revoked somebody's access on
             * deploy would be the worst kind of bug — invisible until they
             * needed the thing.
             */
            $role->permissions()->syncWithoutDetaching($ids);

            foreach ($definition['ranks'] as $domain => $rank) {
                DB::table('role_domain_rank')->updateOrInsert(
                    ['role_id' => $role->id, 'domain_id' => $domains[$domain]],
                    ['rank' => $rank],
                );
            }
        }
    }

    /**
     * @return array<string, array{name: string, description: string, permissions: list<string>, ranks: array<string, int>}>
     */
    protected function roleDefinitions(): array
    {
        /*
         * What every staff member can open, on top of the implicit Employee
         * base. Seeing a module is not the same as acting in it.
         *
         * `calendar.view` is here even though Calendar is deferred to v2 and
         * its route returns 404. §12 keeps deferred entries in the navigation
         * so its shape does not shift when they ship — and an entry nobody
         * holds the permission for is not in the navigation at all, which
         * defeats the point. The 404 says "not built yet"; the missing sidebar
         * entry would say nothing.
         */
        $staffReading = [
            'tasks.view', 'teams.view', 'projects.view', 'meetings.view',
            'tickets.view', 'leave.view', 'announcements.view', 'calendar.view',
        ];

        return [
            'employee' => [
                'name' => 'Employee',
                'description' => 'The Employee base and nothing on top of it.',
                'permissions' => $staffReading,
                'ranks' => ['people' => 10, 'work' => 10, 'support' => 10, 'system' => 10],
            ],

            'team_lead' => [
                'name' => 'Team Lead',
                'description' => 'Authority over teams.',
                'permissions' => [...$staffReading, 'employees.view'],
                'ranks' => ['people' => 30, 'work' => 50, 'support' => 30, 'system' => 10],
            ],

            'manager' => [
                'name' => 'Manager',
                'description' => 'Authority over projects and teams, and the leave decisions that follow.',
                'permissions' => [
                    ...$staffReading, 'employees.view', 'clients.view', 'leave.approve',
                    'meetings.schedule', 'leads.view',
                ],
                'ranks' => ['people' => 50, 'finance' => 30, 'work' => 70, 'support' => 40, 'system' => 10],
            ],

            'hr' => [
                'name' => 'HR',
                'description' => 'People operations. Outranks System Administrator in People and Finance (§2.5).',
                'permissions' => [
                    ...$staffReading, 'employees.view', 'attendance.view', 'attendance.view.all',
                    'attendance.reject', 'leave.approve', 'salary.view', 'announcements.post',
                    'announcements.holiday',
                ],
                'ranks' => ['people' => 80, 'finance' => 70, 'work' => 30, 'support' => 20, 'system' => 10],
            ],

            'support' => [
                'name' => 'Support Associate',
                'description' => 'The ticket queue, and the clients behind it.',
                'permissions' => [...$staffReading, 'clients.view', 'tickets.triage'],
                'ranks' => ['people' => 10, 'work' => 20, 'support' => 70, 'system' => 20],
            ],

            'system_admin' => [
                'name' => 'System Administrator',
                'description' => 'Engineer maintaining the system. Not a business administrator (§2.3).',
                'permissions' => [...$staffReading, 'employees.view'],
                // Outranks HR in system and is outranked by them everywhere
                // else — the row that makes rank per-domain rather than a
                // single ladder.
                'ranks' => ['people' => 20, 'finance' => 10, 'work' => 30, 'support' => 40, 'system' => 90],
            ],

            'ceo' => [
                'name' => 'CEO',
                'description' => 'Top of the human hierarchy — and still an Employee, with their own leave and attendance.',
                'permissions' => [
                    ...$staffReading, 'clients.view', 'employees.view', 'attendance.view',
                    'attendance.view.all', 'attendance.reject', 'leave.approve', 'salary.view',
                    'invoices.view', 'tickets.triage', 'meetings.schedule', 'announcements.post',
                    'announcements.holiday', 'reports.view', 'leads.view',
                ],
                'ranks' => ['people' => 90, 'finance' => 90, 'work' => 90, 'support' => 80, 'system' => 40],
            ],

            /*
             * The one role with no Employee base (§2.1) — that comes from
             * `staff_kind = 'mentor'` on the account, not from this row. Rank
             * is zero everywhere: read-only means no standing to act on
             * anybody.
             */
            'mentor' => [
                'name' => 'Mentor',
                'description' => 'Read-only, and no Employee base — so no personal records at all (§2.1).',
                'permissions' => [
                    'clients.view', 'employees.view', 'teams.view', 'projects.view', 'tasks.view',
                    'tickets.view', 'invoices.view', 'meetings.view', 'announcements.view',
                    'reports.view', 'dashboard.view',
                ],
                'ranks' => [],
            ],
        ];
    }
}
