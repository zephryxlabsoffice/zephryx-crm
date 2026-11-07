<?php

/*
|--------------------------------------------------------------------------
| Ticket categories
|--------------------------------------------------------------------------
|
| Moved to master data (§12) on 2026-09-21 - the "nothing points at a
| category" reasoning that used to justify keeping this in config stopped
| being true once ticket departments joined master data alongside it and the
| Admin Panel needed one consistent place to manage both. See
| MasterDataItem::TICKET_CATEGORIES and TicketController::options(), which is
| now the only reader.
|
*/

return [

    /*
    | IT tickets: a System Administrator sees and decides these (owner,
    | 2026-11-07). A client's technical issue goes straight to one.
    */
    'technical_categories' => ['Access', 'Network', 'Bug'],

    /*
    | Desks: a role that sees and decides the tickets of some categories, and
    | only those, by category name. `route_on_raise` sends a newly raised ticket
    | of that category straight to one of them instead of to Support.
    |
    | IT to the System Administrator, attendance to HR (owner, 2026-11-07).
    */
    'desks' => [
        'it' => [
            'permission' => 'tickets.it',
            'role' => 'system_admin',
            'categories' => ['Access', 'Network', 'Bug'],
            'route_on_raise' => false,
        ],
        'hr' => [
            'permission' => 'tickets.hr',
            'role' => 'hr',
            'categories' => ['Attendance'],
            'route_on_raise' => true,
        ],
    ],

    /*
    | A resolved ticket closes by itself this many days later, unless the person
    | who raised it replies first (owner, 2026-11-06).
    */
    'auto_close_after_days' => 7,

];
