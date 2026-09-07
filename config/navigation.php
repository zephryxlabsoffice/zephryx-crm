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
| `deferred`   true for Leads, Calendar and Reports, which §12 keeps in the
|              navigation deliberately so the shape stays stable when they ship
|              in v2. Their routes exist and return 404; the 404 view recognises
|              them and says "not built yet" rather than "not found".
|
*/

return [

    ['key' => 'dashboard',     'label' => 'Dashboard',      'icon' => 'dashboard',     'route' => 'dashboard',     'permission' => 'dashboard.view'],
    ['key' => 'clients',       'label' => 'Clients',        'icon' => 'clients',       'route' => 'clients.index', 'permission' => 'clients.view'],
    ['key' => 'employees',     'label' => 'Employees',      'icon' => 'employees',     'route' => 'employees.index', 'permission' => 'employees.view'],
    ['key' => 'teams',         'label' => 'Teams',          'icon' => 'teams',         'route' => 'teams.index',   'permission' => 'teams.view'],
    ['key' => 'projects',      'label' => 'Projects',       'icon' => 'projects',      'route' => 'projects.index', 'permission' => 'projects.view'],
    ['key' => 'tasks',         'label' => 'Tasks',          'icon' => 'tasks',         'route' => 'tasks.index',   'permission' => 'tasks.view'],
    ['key' => 'leads',         'label' => 'Leads',          'icon' => 'leads',         'route' => 'leads.index',   'permission' => 'leads.view', 'deferred' => true],
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
     * Settings is the company configuration surface and lives in the Admin
     * Panel (§12), so this entry points into the /admin realm. Only the owner
     * account holds `settings.view`, and realm middleware refuses the route to
     * anyone else regardless of what the sidebar renders.
     */
    ['key' => 'settings',      'label' => 'Settings',       'icon' => 'settings',      'route' => 'admin.settings', 'permission' => 'settings.view'],

];
