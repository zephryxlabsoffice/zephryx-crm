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
    | The designation a client's "technical issue" goes straight to, by its
    | code in Admin > Master data. If nobody active holds it, the ticket joins
    | the normal queue instead, so it can never be lost.
    */
    'technical_designation_code' => 'TECHASST',

    /*
    | Categories Support escalates to a Technical Associate by default.
    */
    'technical_categories' => ['Access', 'Network', 'Bug'],

    /*
    | A resolved ticket closes by itself this many days later, unless the person
    | who raised it replies first (owner, 2026-11-06).
    */
    'auto_close_after_days' => 7,

];
