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
    | The company Workspace account that owns every event, and where its
    | credential actually lives — NOT here.
    |
    | A service account with domain-wide delegation, impersonating one
    | calendar, rather than each person connecting their own Google (decided
    | 2026-08-28). Links then survive somebody leaving, nobody has to
    | re-consent, and the CRM stores no per-user refresh tokens — credentials
    | it would then have to protect.
    |
    | This used to be a `google` key here, reading `GOOGLE_CALENDAR_ID` /
    | `GOOGLE_SERVICE_ACCOUNT_KEY` / `GOOGLE_IMPERSONATE_EMAIL` out of `.env`
    | — a file on the server, which is exactly what the owner said they did
    | not want to depend on (plan doc, "Connecting it", 2026-09-16). The
    | `google_connection` table supersedes all three: `App\Support\Meetings\
    | GoogleMeetProvider` reads `calendar_id` and `impersonate_email` from
    | App\Models\GoogleConnection at the point of use, the same rule
    | App\Support\Documents\DocumentStore follows for the Drive key — never
    | pushed into config, because `company_settings`' boot-time overlay is
    | exactly how a secret ends up in a stack trace.
    */

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
