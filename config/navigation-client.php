<?php

/*
|--------------------------------------------------------------------------
| Client portal navigation
|--------------------------------------------------------------------------
|
| The client realm's sidebar (foundation spec §3). Same shape as
| config/navigation.php and read by the same App\Support\Navigation\Navigation
| — the client portal reuses the staff shell rather than having one of its own,
| because the designer's client screens use the same markup for it.
|
| ─────────────────────────────────────────────────────────────────────────────
| WHY THIS IS A SEPARATE FILE AND NOT A `realm` COLUMN ON THE STAFF ONE
|
| A shared list filtered by realm would put `/employees` and `/salary` one
| mistyped key away from a client's sidebar. Two files cannot make that mistake:
| there is no entry here for a page a client may not see, because the pages a
| client may not see are not in this file at all.
|
| It is also much shorter, and it is short for a reason. A client sees the work
| we are doing for them, what it costs, and how to reach us. Everything else in
| the application is ours.
| ─────────────────────────────────────────────────────────────────────────────
|
| Permission keys are `client.*` and are held only by client accounts. They are
| deliberately NOT the staff keys: `projects.view` on the staff side means every
| project in the company, and a client holding it would be a catastrophe rather
| than a convenience. Nothing on this side reuses a key from the other.
|
*/

return [

    ['key' => 'dashboard', 'label' => 'Dashboard',       'icon' => 'dashboard', 'route' => 'client.dashboard',      'permission' => 'client.dashboard.view'],
    ['key' => 'projects',  'label' => 'Projects',        'icon' => 'projects',  'route' => 'client.projects.index', 'permission' => 'client.projects.view'],
    ['key' => 'invoices',  'label' => 'Invoices',        'icon' => 'invoices',  'route' => 'client.invoices.index', 'permission' => 'client.invoices.view'],
    ['key' => 'tickets',   'label' => 'Support Tickets', 'icon' => 'tickets',   'route' => 'client.tickets.index',  'permission' => 'client.tickets.view'],
    ['key' => 'meetings',  'label' => 'Meetings',        'icon' => 'meetings',  'route' => 'client.meetings.index', 'permission' => 'client.meetings.view'],
    ['key' => 'profile',   'label' => 'My Profile',      'icon' => 'profile',   'route' => 'client.profile.show',   'permission' => 'client.profile.view'],

];
