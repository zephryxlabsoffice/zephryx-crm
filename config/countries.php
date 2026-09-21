<?php

/*
|--------------------------------------------------------------------------
| Countries
|--------------------------------------------------------------------------
|
| ISO 3166-1 alpha-2, for the client record's country field. A fixed
| dropdown, not free text (client portal decisions, Q3) — the same reasoning
| every closed list in this application follows: a country typed once as
| "India" and once as "india" is a filter that quietly misses half its rows.
|
| Not the full 249-territory ISO list. This is a small consultancy with a
| specific, known client base — India first, then the countries clients have
| actually been in. Adding one is a line here, not a migration.
|
*/

return [
    'IN' => 'India',
    'US' => 'United States',
    'GB' => 'United Kingdom',
    'CA' => 'Canada',
    'AU' => 'Australia',
    'AE' => 'United Arab Emirates',
    'SG' => 'Singapore',
    'DE' => 'Germany',
    'FR' => 'France',
    'NL' => 'Netherlands',
    'JP' => 'Japan',
    'NZ' => 'New Zealand',
    'ZA' => 'South Africa',
];
