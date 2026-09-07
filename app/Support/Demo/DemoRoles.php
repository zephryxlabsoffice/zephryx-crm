<?php

namespace App\Support\Demo;

/**
 * Permission sets per role, so the composed dashboard can be REVIEWED before
 * the RBAC engine exists.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHY THIS HAS TO EXIST
 *
 * App\Support\Navigation\PermissiveGate allows everything, which is right for
 * building the shell and useless for reviewing a page whose entire behaviour is
 * "show what this person holds". Against a gate that says yes to everything,
 * the dashboard renders every widget at once — which is the one layout no real
 * account will ever see.
 *
 * So `?as=hr` narrows the dashboard to one role's set. It is how the five
 * layouts in the handover get reviewed without five files existing.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * IT CAN ONLY EVER TAKE THINGS AWAY
 *
 * The preview is INTERSECTED with the real gate, never substituted for it (see
 * DashboardController::gate). A role set cannot grant a permission the viewer
 * does not already hold — it can only hide ones they do. That ordering is the
 * whole safety argument: when the real gate lands, `?as=ceo` on an intern's
 * session still shows an intern's dashboard.
 *
 * On top of that it is local + debug only, like every other Demo class, so it
 * does not exist in a deployed application at all.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * These sets are a sketch for review, not the RBAC seed data. When §5 lands,
 * the real `role_permissions` table replaces them and this class is deleted.
 */
class DemoRoles
{
    public static function enabled(): bool
    {
        return app()->environment('local') && config('app.debug');
    }

    /**
     * Every human account except Mentor sits on the Employee base (§2.2) — my
     * attendance, my leave, my payslip, my tasks, my profile. CEO and HR have
     * these too, which is precisely why they cannot approve their own.
     *
     * Note the two kinds of key. `*.self` is the Employee base: my own records.
     * `*.view` is sight of a module. A Mentor holds the second and never the
     * first, which is the distinction the whole rail depends on — see the note
     * in config/dashboard.php.
     *
     * @return list<string>
     */
    protected static function employeeBase(): array
    {
        return [
            'dashboard.view',
            'profile.view',

            // Mine.
            'attendance.self',
            'leave.self',
            'salary.self',
            'tasks.self',
            'teams.self',
            'meetings.self',

            // The modules an employee can open.
            'leave.view',
            'tasks.view',
            'teams.view',
            'projects.view',
            'meetings.view',
            'tickets.view',
            'announcements.view',
        ];
    }

    /**
     * @return array<string, array{label: string, note: string, permissions: list<string>}>
     */
    public static function all(): array
    {
        if (! self::enabled()) {
            return [];
        }

        $base = self::employeeBase();

        return [
            'employee' => [
                'label' => 'Employee',
                'note' => 'The Employee base and nothing on top of it.',
                'permissions' => $base,
            ],

            'team_lead' => [
                'label' => 'Team Lead',
                'note' => 'Employee base plus visibility of the people in their teams.',
                'permissions' => [...$base, 'employees.view'],
            ],

            'manager' => [
                'label' => 'Manager',
                'note' => 'Authority over projects and teams — and the leave decisions that follow from it.',
                'permissions' => [
                    ...$base,
                    'employees.view',
                    'clients.view',
                    'leave.approve',
                    'meetings.schedule',
                ],
            ],

            'hr' => [
                'label' => 'HR',
                'note' => 'People operations. Outranks System Administrator in People and Finance (§2.5).',
                'permissions' => [
                    ...$base,
                    'employees.view',
                    'attendance.view',
                    'attendance.view.all',
                    'attendance.reject',
                    'leave.approve',
                    'salary.view',
                    'announcements.post',
                ],
            ],

            'support' => [
                'label' => 'Support Associate',
                'note' => 'The ticket queue, and the clients behind it.',
                'permissions' => [...$base, 'clients.view', 'tickets.triage'],
            ],

            'ceo' => [
                'label' => 'CEO',
                'note' => 'Top of the human hierarchy — and still an Employee, with their own leave and attendance.',
                'permissions' => [
                    ...$base,
                    'clients.view',
                    'employees.view',
                    'attendance.view',
                    'attendance.view.all',
                    'attendance.reject',
                    'leave.approve',
                    'salary.view',
                    'invoices.view',
                    'tickets.triage',
                    'meetings.schedule',
                    'announcements.post',
                    'reports.view',
                ],
            ],

            /*
             * The one role with NO Employee base (§2.1). A Mentor is an outside
             * investor or advisor: read-only across the system, with no
             * attendance, no leave, no payslip and no profile of their own.
             *
             * Previewing as a Mentor is the fastest way to catch a widget that
             * has been given a `*.view` key where it needed a `*.self` one: a
             * card headed "Your tasks" on a Mentor's dashboard is empty by
             * construction, because a Mentor has no tasks.
             *
             * What a Mentor legitimately keeps is the announcements board —
             * published to the company, and read-only is exactly what they are.
             */
            'mentor' => [
                'label' => 'Mentor',
                'note' => 'Read-only, and no Employee base — so no personal records at all (§2.1).',
                'permissions' => [
                    'dashboard.view',
                    'clients.view',
                    'employees.view',
                    'teams.view',
                    'projects.view',
                    'tasks.view',
                    'tickets.view',
                    'invoices.view',
                    'meetings.view',
                    'announcements.view',
                    'reports.view',
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function has(?string $role): bool
    {
        return $role !== null && array_key_exists($role, self::all());
    }

    /**
     * @return array{label: string, note: string, permissions: list<string>}|null
     */
    public static function find(?string $role): ?array
    {
        return self::all()[$role] ?? null;
    }
}
