<?php

/*
|--------------------------------------------------------------------------
| The dashboard
|--------------------------------------------------------------------------
|
| ─────────────────────────────────────────────────────────────────────────────
| THERE IS ONE DASHBOARD, NOT ONE PER ROLE (decided 2026-09-07)
|
| The handover shipped five separate pages — Superadmin, Admin, HR, Mentor and
| Employee — each a complete HTML file. This file is the argument against that,
| and the reason is §2.4: roles STACK, additively.
|
| Manager + HR is a real person. So is Team Lead + Support. A fixed "HR
| dashboard" has no answer for either: it either hides the projects somebody
| manages or shows payroll to somebody who does not run it, and the only way
| out is a sixth file, then a seventh. Five files also means five places to fix
| a widget, and the predictable outcome is four of them drifting.
|
| So the dashboard is a REGISTRY, and the page is whatever the viewer's
| permissions add up to. A Manager + HR gets the union without anybody writing
| a Manager+HR layout, because a union is what stacking already means.
|
| This is the same mechanism as config/navigation.php, deliberately: one list,
| each entry carrying the permission key that reveals it, filtered at render.
| Somebody who has understood the sidebar has already understood this file.
| ─────────────────────────────────────────────────────────────────────────────
|
| `key`        the widget's identity. KPI tiles resolve their value through
|              App\Support\Dashboard\DashboardData; card widgets additionally
|              resolve a Blade partial at resources/views/dashboard/widgets/.
| `permission` the RBAC key required to see it. Filtering is a courtesy on top
|              of the guards the underlying modules already carry — the widget
|              summarises a page, and that page checks for itself (§3.1).
| `column`     `main` (wide, the work) or `rail` (narrow, the person).
| `weight`     ascending. Gaps of ten so a widget can be slotted between two
|              without renumbering the file.
| `icon`       a name from resources/views/partials/nav-icon.blade.php.
|
| ─────────────────────────────────────────────────────────────────────────────
| WHY THE KPI TILES CARRY NO PERCENTAGE
|
| The handover printed "12.5% vs last month" under every tile. All of them were
| hardcoded, and the shape is wrong even when the figure is real: a percentage
| change on a count of two is a hundred per cent, which reads as a crisis and
| means one. Worse, this system has no history to compute one from — clients and
| projects have no month-over-month record at all, so the number would have to
| be invented, and an invented figure on a dashboard is one somebody eventually
| quotes in a meeting.
|
| Each tile carries a factual second line instead: what makes up the number, or
| what about it needs attention. See App\Support\DashboardPresenter::delta for
| the one place a comparison IS available and how it is worded.
| ─────────────────────────────────────────────────────────────────────────────
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | KPI tiles
    |--------------------------------------------------------------------------
    |
    | The row across the top. Each tile filters INDIVIDUALLY rather than the row
    | being an all-or-nothing block: a Team Lead who can see projects but not
    | payroll should get the projects tile, not an empty row.
    |
    | Deliberately capped in the composer rather than here — see
    | DashboardComposer::MAX_KPIS for why a viewer holding everything still gets
    | a readable row instead of eleven tiles wrapping onto three lines.
    |
    */

    'kpis' => [

        ['key' => 'my-open-work',  'permission' => 'tasks.self',        'icon' => 'tasks',       'weight' => 10],
        ['key' => 'clients',       'permission' => 'clients.view',      'icon' => 'clients',     'weight' => 20],
        ['key' => 'projects',      'permission' => 'projects.view',     'icon' => 'projects',    'weight' => 30],
        ['key' => 'headcount',     'permission' => 'employees.view',    'icon' => 'employees',   'weight' => 40],
        ['key' => 'leave-pending', 'permission' => 'leave.approve',     'icon' => 'leave',       'weight' => 50],
        ['key' => 'attendance',    'permission' => 'attendance.view.all', 'icon' => 'attendance', 'weight' => 60],
        ['key' => 'tickets',       'permission' => 'tickets.view',      'icon' => 'tickets',     'weight' => 70],
        ['key' => 'receivables',   'permission' => 'invoices.view',     'icon' => 'invoices',    'weight' => 80],

    ],

    /*
    |--------------------------------------------------------------------------
    | Widgets
    |--------------------------------------------------------------------------
    |
    | Two columns, and the split is not cosmetic. `rail` is narrow and mostly
    | personal — my day, my leave, my payslip. `main` is wide: queues,
    | overviews, the things somebody else is waiting on.
    |
    | ─────────────────────────────────────────────────────────────────────────
    | `*.self` IS A DIFFERENT KEY FROM `*.view`, AND THE DIFFERENCE MATTERS
    |
    | A widget headed "Your tasks" needs an Employee base (§2.2); a widget
    | headed "Projects" needs sight of the module. They are not the same
    | permission and they must not share a key, because a Mentor — read-only,
    | no Employee base, no personal records at all (§2.1) — holds the second
    | and never the first.
    |
    | Get this wrong and a Mentor's dashboard fills with cards headed "Your"
    | that are empty by construction, because a Mentor has no tasks, no team and
    | no leave to be shown. Every personal widget below therefore carries a
    | `.self` key, and previewing as a Mentor is the fastest way to catch one
    | that does not.
    | ─────────────────────────────────────────────────────────────────────────
    |
    */

    'widgets' => [

        /*
         * Checking in. First widget in the rail on purpose: for most of the
         * company it is the only thing they open this page to do, and a button
         * somebody has to scroll to is a button somebody stops using.
         *
         * `attendance.self`, not `attendance.view` — punching in is the
         * Employee base, while `attendance.view` is the company-wide roll.
         */
        ['key' => 'punch',            'permission' => 'attendance.self',     'column' => 'rail', 'weight' => 10, 'icon' => 'attendance'],
        ['key' => 'my-meetings',      'permission' => 'meetings.self',       'column' => 'rail', 'weight' => 20, 'icon' => 'meetings'],
        ['key' => 'my-leave',         'permission' => 'leave.self',          'column' => 'rail', 'weight' => 30, 'icon' => 'leave'],
        ['key' => 'my-salary',        'permission' => 'salary.self',         'column' => 'rail', 'weight' => 40, 'icon' => 'salary'],

        /*
         * The two rail widgets that are NOT personal, and the only reason a
         * Mentor's rail is not empty. The board is published to the company and
         * milestones are announced on it — reading either needs sight of
         * Announcements, not an Employee base.
         */
        ['key' => 'announcements',    'permission' => 'announcements.view',  'column' => 'rail', 'weight' => 50, 'icon' => 'announcements'],
        ['key' => 'milestones',       'permission' => 'announcements.view',  'column' => 'rail', 'weight' => 60, 'icon' => 'profile'],

        ['key' => 'my-tasks',         'permission' => 'tasks.self',          'column' => 'main', 'weight' => 10, 'icon' => 'tasks'],

        /*
         * The approval queues, above the overviews. An overview is something
         * you read; a queue is somebody waiting on you, and the things people
         * are waiting on belong higher than the things you might like to know.
         */
        ['key' => 'leave-approvals',  'permission' => 'leave.approve',       'column' => 'main', 'weight' => 20, 'icon' => 'leave'],
        ['key' => 'attendance-open',  'permission' => 'attendance.reject',   'column' => 'main', 'weight' => 30, 'icon' => 'attendance'],
        ['key' => 'ticket-queue',     'permission' => 'tickets.triage',      'column' => 'main', 'weight' => 40, 'icon' => 'tickets'],
        ['key' => 'meeting-requests', 'permission' => 'meetings.schedule',   'column' => 'main', 'weight' => 50, 'icon' => 'meetings'],

        ['key' => 'projects',         'permission' => 'projects.view',       'column' => 'main', 'weight' => 60, 'icon' => 'projects'],
        ['key' => 'my-team',          'permission' => 'teams.self',          'column' => 'main', 'weight' => 70, 'icon' => 'teams'],
        ['key' => 'receivables',      'permission' => 'invoices.view',       'column' => 'main', 'weight' => 80, 'icon' => 'invoices'],
        ['key' => 'payroll',          'permission' => 'salary.view',         'column' => 'main', 'weight' => 90, 'icon' => 'salary'],

    ],

];
