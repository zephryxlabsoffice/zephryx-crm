<?php

/*
|--------------------------------------------------------------------------
| Meetings
|--------------------------------------------------------------------------
|
| ─────────────────────────────────────────────────────────────────────────
| THIS SERVER HOSTS NOTHING
|
| Decided 2026-08-28. Every meeting is a Google Meet, created through the
| Google Calendar API on the company's Workspace account. The CRM organises:
| it holds who is meeting whom about what, and a reference to the Google
| event. It does not host, record, or proxy a call, and it never constructs
| a Meet URL — that comes back from Google or it does not exist.
|
| Google Calendar is the source of truth for the event itself, including
| every attendee's RSVP. We read those back; we do not own them. Holding our
| own copy of somebody else's calendar is the same mistake as holding our
| own copy of somebody else's payroll.
| ─────────────────────────────────────────────────────────────────────────
|
*/

return [

    /*
    | The company Workspace account that owns every event.
    |
    | A service account with domain-wide delegation, impersonating one calendar,
    | rather than each person connecting their own Google (decided 2026-08-28).
    | Links then survive somebody leaving, nobody has to re-consent, and the CRM
    | stores no per-user refresh tokens — credentials it would then have to
    | protect.
    |
    | The key file lives OUTSIDE the web root and is never committed (§11.1).
    | `storage/` is not enough on shared hosting if the document root is
    | misconfigured; put it beside the application directory, not inside public.
    */
    'google' => [
        'calendar_id' => env('GOOGLE_CALENDAR_ID'),
        'service_account_key' => env('GOOGLE_SERVICE_ACCOUNT_KEY'),
        'impersonate' => env('GOOGLE_IMPERSONATE_EMAIL'),
    ],

    /*
    | Times are STORED in UTC and SHOWN in this zone (decided 2026-08-28).
    |
    | Storing local time is what makes a meeting with an overseas client drift
    | by an hour twice a year, when their daylight saving changes and ours does
    | not. Storing UTC also means showing a different zone later is a display
    | change rather than a migration.
    */
    'display_timezone' => env('ZEPHRYX_TIMEZONE', 'Asia/Kolkata'),

    /*
    | Clients must be able to join without friction (decided 2026-08-28).
    |
    | That is a Google Calendar setting on the event, not something this
    | application can do on its own: the conference has to allow external
    | guests, and the invite has to carry the link. Recorded here so whoever
    | writes GoogleMeetProvider knows it is a requirement and not a preference.
    */
    'allow_external_guests' => true,

    /*
    | How long a meeting runs by default, in minutes, when nobody says.
    */
    'default_duration' => 30,

];
