<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment methods
    |--------------------------------------------------------------------------
    |
    | How money can arrive. Config rather than a table for the same reason
    | ticket categories are: nothing points at a method, nobody reports on one,
    | and a management screen for six words would have no reader.
    |
    | It is a closed list on purpose. Free text here means "NEFT", "neft" and
    | "Neft " in the same column, and the first person to reconcile a month
    | against a bank statement is the one who finds out.
    |
    */

    'payment_methods' => [
        'NEFT',
        'UPI',
        'Wire transfer',
        'Cheque',
        'Card',
        'Cash',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default payment terms
    |--------------------------------------------------------------------------
    |
    | Days from the invoice date to the due date, used to prefill the form. The
    | due date is stored per invoice — this only decides what the field starts
    | at, because terms are agreed per client and this is a starting point
    | rather than a rule.
    |
    */

    'default_terms_days' => 30,

];
