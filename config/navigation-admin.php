<?php

/*
|--------------------------------------------------------------------------
| Admin Panel navigation
|--------------------------------------------------------------------------
|
| The third realm (foundation spec §3), reached through a single account
| operated by the company owner. Same shape as the other two navigation files
| and read by the same App\Support\Navigation\Navigation.
|
| ─────────────────────────────────────────────────────────────────────────────
| WHAT IS NOT IN THIS LIST, AND CANNOT BE
|
| §2.1: the Admin Panel sets the rules and does not participate in them. It has
| no personal records — no "my attendance", no "my payslip" — and no operational
| authority: it cannot approve leave, run payroll or mark attendance.
|
| So there is no entry here for Leave, Salary, Attendance, Projects or any other
| operational module, and there is no route under /admin that performs an
| operational act. The panel configures WHO may approve leave. It can never
| approve any.
|
| That is enforced by absence rather than by a check, which is the strongest
| form available: a permission somebody forgot to write is a hole, but a route
| that does not exist cannot be called.
| ─────────────────────────────────────────────────────────────────────────────
|
| Permission keys are `admin.*` and are held only by the owner account. They are
| deliberately not shared with any staff key — `employees.view` on the staff side
| means the HR directory, while `admin.accounts.view` means login credentials and
| role assignments, and those are not the same thing to be allowed to see.
|
*/

return [

    ['key' => 'dashboard',   'label' => 'Overview',       'icon' => 'dashboard',     'route' => 'admin.dashboard',    'permission' => 'admin.dashboard.view'],
    ['key' => 'accounts',    'label' => 'Accounts',       'icon' => 'employees',     'route' => 'admin.accounts.index', 'permission' => 'admin.accounts.view'],
    ['key' => 'access',      'label' => 'Access Control', 'icon' => 'teams',         'route' => 'admin.access.index', 'permission' => 'admin.access.view'],
    ['key' => 'master-data', 'label' => 'Master Data',    'icon' => 'reports',       'route' => 'admin.master.index', 'permission' => 'admin.master.view'],
    ['key' => 'settings',    'label' => 'Settings',       'icon' => 'settings',      'route' => 'admin.settings',     'permission' => 'admin.settings.view'],
    ['key' => 'audit',       'label' => 'Audit Log',      'icon' => 'announcements', 'route' => 'admin.audit.index',  'permission' => 'admin.audit.view'],

];
