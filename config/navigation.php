<?php

/*
|--------------------------------------------------------------------------
| Sidebar navigation
|--------------------------------------------------------------------------
|
| The staff realm's navigation, in order. This is a *superset*: it lists every
| module the shell can show. What any one person actually sees is decided at
| render time by App\Support\Navigation, which drops every entry whose
| `permission` key the viewer does not hold (foundation spec §5).
|
| Hiding an entry is a courtesy, not a security boundary — the route itself is
| guarded too (§3.1). A nav that shows links a person cannot open teaches them
| to expect 403s; a nav filtered without guarded routes is security theatre.
| Both, always.
|
| `key`        matches the page's `activeNav` so the current item highlights.
| `permission` the RBAC key required to see the entry.
| `route`      a named route, resolved at render. Entries whose route does not
|              exist yet are skipped, so this file can list the whole roadmap
|              without breaking the shell as modules land one at a time.
| `deferred`   true for Calendar and Reports, which §12 keeps in the
|              navigation deliberately so the shape stays stable when they ship
|              in v2. Their routes exist and return 404; the 404 view recognises
|              them and says "not built yet" rather than "not found".
|              Leads used to be here too — removed 2026-09-11: "No Leads
|              module. The unused leads.view permission goes."
|
*/

return [

    ['key' => 'dashboard',     'label' => 'Dashboard',      'icon' => 'dashboard',     'route' => 'dashboard',     'permission' => 'dashboard.view'],
    ['key' => 'clients',       'label' => 'Clients',        'icon' => 'clients',       'route' => 'clients.index', 'permission' => 'clients.view'],
    ['key' => 'employees',     'label' => 'Employees',      'icon' => 'employees',     'route' => 'employees.index', 'permission' => 'employees.view'],
    ['key' => 'teams',         'label' => 'Teams',          'icon' => 'teams',         'route' => 'teams.index',   'permission' => 'teams.view'],
    ['key' => 'projects',      'label' => 'Projects',       'icon' => 'projects',      'route' => 'projects.index', 'permission' => 'projects.view'],
    ['key' => 'tasks',         'label' => 'Tasks',          'icon' => 'tasks',         'route' => 'tasks.index',   'permission' => 'tasks.view'],
    ['key' => 'tickets',       'label' => 'Tickets',        'icon' => 'tickets',       'route' => 'tickets.index', 'permission' => 'tickets.view'],
    ['key' => 'invoices',      'label' => 'Invoices',       'icon' => 'invoices',      'route' => 'invoices.index', 'permission' => 'invoices.view'],
    ['key' => 'salary',        'label' => 'Salary',         'icon' => 'salary',        'route' => 'salary.index',  'permission' => 'salary.view'],
    ['key' => 'attendance',    'label' => 'Attendance',     'icon' => 'attendance',    'route' => 'attendance.index', 'permission' => 'attendance.view'],
    ['key' => 'leave',         'label' => 'Leave Requests', 'icon' => 'leave',         'route' => 'leave.index',   'permission' => 'leave.view'],
    ['key' => 'meetings',      'label' => 'Meetings',       'icon' => 'meetings',      'route' => 'meetings.index', 'permission' => 'meetings.view'],
    ['key' => 'calendar',      'label' => 'Calendar',       'icon' => 'calendar',      'route' => 'calendar.index', 'permission' => 'calendar.view', 'deferred' => true],
    ['key' => 'reports',       'label' => 'Reports',        'icon' => 'reports',       'route' => 'reports.index', 'permission' => 'reports.view', 'deferred' => true],
    ['key' => 'announcements', 'label' => 'Announcements',  'icon' => 'announcements', 'route' => 'announcements.index', 'permission' => 'announcements.view'],
    ['key' => 'profile',       'label' => 'My Profile',     'icon' => 'profile',       'route' => 'profile.show',  'permission' => 'profile.view'],

    /*
     * ─────────────────────────────────────────────────────────────────────────
     * SETTINGS IS NOT IN THIS FILE (removed 2026-09-07, when /admin was built)
     *
     * It used to be, pointing at `admin.settings`, on the reasoning that only
     * the owner would hold the permission and realm middleware would refuse
     * everyone else.
     *
     * That was wrong, and building the Admin Panel is what made it obvious. The
     * realms have SEPARATE SESSIONS (§3): `zx_staff` and `zx_admin`. A staff
     * session cannot open /admin at all — not for HR, not for the CEO, and not
     * for the owner, who has to sign in to the admin realm as a different
     * account. So the entry was a link that no staff session could ever follow,
     * for anybody.
     *
     * The header of this file already argued against exactly that: a nav
     * showing links a person cannot open teaches them to expect 403s. Settings
     * now lives in config/navigation-admin.php, in the realm that can reach it.
     * ─────────────────────────────────────────────────────────────────────────
     */

];
