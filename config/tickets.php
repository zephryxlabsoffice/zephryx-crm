<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ticket categories
    |--------------------------------------------------------------------------
    |
    | The support team's own labels for what a ticket is about.
    |
    | Config rather than a table, and deliberately not master data: nothing
    | anywhere points at a category, nobody reports on one, and there is no
    | screen that would deactivate rather than delete one. A table would be a
    | table with a management page in front of it and no reader.
    |
    | If that changes — a report grouped by category, or a routing rule keyed on
    | one — it becomes a master data list, and TicketController::options() is
    | the one place that reads it.
    |
    */

    'categories' => [
        'Access',
        'Billing',
        'Bug',
        'Network',
        'Performance',
        'Reporting',
        'Feature request',
    ],

];
