<?php

/*
|--------------------------------------------------------------------------
| verify.zephryxlabs.in - ID card verification (2026-10-29)
|--------------------------------------------------------------------------
|
| A second website served by this same application: anybody can check that
| somebody works here by scanning the QR on their ID card or typing their
| Employee ID. See App\Support\Verify\VerifyDirectory.
|
| `domain` is the host the site answers on. Every other host keeps serving
| the CRM, and this host serves nothing of the CRM but the sign-in pages its
| own admin needs - see App\Http\Middleware\VerifyHost.
*/

return [
    'domain' => env('VERIFY_DOMAIN', 'verify.zephryxlabs.in'),

    // How long a link made by the search box keeps working. A QR's link never
    // expires: it is printed on a card.
    'search_link_minutes' => 15,
];
