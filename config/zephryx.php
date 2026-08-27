<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Brand
    |--------------------------------------------------------------------------
    |
    | Displayed in the topbar lockup on every public surface. The mark itself
    | ships as two authored SVGs (black for light surfaces, white for dark);
    | see public/assets/brand.
    |
    */

    'brand' => [
        'name'   => 'ZephryxLabs',
        'suffix' => 'CRM',
    ],

    /*
    |--------------------------------------------------------------------------
    | Support
    |--------------------------------------------------------------------------
    |
    | Destination of the "Contact Support" call to action. A mailto: link was
    | chosen over a public contact form so the landing page exposes no
    | unauthenticated write endpoint (foundation spec §6, §13.3).
    |
    */

    'support' => [
        'email' => env('ZEPHRYX_SUPPORT_EMAIL', 'admin@zephryxlabs.in'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Theme
    |--------------------------------------------------------------------------
    |
    | Dark is the default (spec §7). Pre-login the choice lives in a cookie;
    | once accounts exist it is persisted to users.theme_preference.
    |
    | The cookie's name is not configurable — it is referenced while the
    | middleware stack is being assembled, before config is available. It lives
    | on App\Support\Theme::COOKIE.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Single-currency by design: this is one company's internal software, not a
    | product sold across markets. Amounts are stored as integer minor units to
    | avoid float rounding on invoices and payroll.
    |
    */

    'currency' => [
        'code' => env('ZEPHRYX_CURRENCY', 'INR'),
        'symbol' => env('ZEPHRYX_CURRENCY_SYMBOL', '₹'),
    ],

    'theme' => [
        'default'     => 'dark',
        'available'   => ['dark', 'light'],
        'cookie_days' => 365,
    ],

];
