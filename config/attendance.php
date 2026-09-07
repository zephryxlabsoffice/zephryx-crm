<?php

/*
|--------------------------------------------------------------------------
| Attendance policy
|--------------------------------------------------------------------------
|
| ─────────────────────────────────────────────────────────────────────────────
| THIS FILE IS A PLACEHOLDER FOR THE ADMIN PANEL
|
| Working hours, the half-day threshold, which days are weekly offs and how long
| an unclosed day is left open are all company policy, and company policy is
| configured in the Admin Panel (§12) — not written into the application. Until
| that surface exists the values live here so the Attendance module has
| something real to read.
|
| When Settings lands this becomes a table and App\Support\AttendancePolicy
| reads from that instead. Nothing else changes: every page already goes
| through the policy class rather than reaching for these values directly,
| precisely so that swap is one method.
| ─────────────────────────────────────────────────────────────────────────────
|
*/

return [

    /*
    | The working day, for information.
    |
    | Note that NOTHING is computed from these. They are on the policy card so
    | people know when a normal day runs, and that is all they do — turning up
    | at 09:45 is not a status (decided 2026-09-03; see below), and leaving
    | early is a conversation rather than a flag.
    */
    'work_start' => '09:30',
    'work_end' => '18:30',

    /*
    | ─────────────────────────────────────────────────────────────────────────
    | THE ONE RULE
    |
    | Hours worked, against one threshold. At or above it the day is present;
    | below it the day is half. No check-in at all is an absence. Three
    | statuses, one comparison, and anybody can check it against their own two
    | timestamps without being told how the arithmetic works.
    |
    | There is deliberately no "full day" figure and no grace period. A second
    | threshold would create a fourth status nobody asked for, and a grace
    | period only exists to support a "late" status that this module does not
    | have.
    | ─────────────────────────────────────────────────────────────────────────
    */
    'half_day_hours' => 4.0,

    /*
    | How long a day stays open before it is written off.
    |
    | A check-in with no check-out is the most common attendance data problem
    | there is — somebody shuts their laptop and goes home. After this many
    | hours the record is REJECTED: there is no honest way to say how long that
    | person worked, and the alternatives are inventing a check-out time or
    | leaving a day counted as present on evidence that stops at 09:18.
    |
    | Ten hours is long enough that a genuinely long day closes itself normally
    | and short enough that the record is dealt with the same evening.
    */
    'auto_reject_after_hours' => 10,

    /*
    | Weekly offs, as Carbon day-of-week numbers (0 = Sunday … 6 = Saturday).
    |
    | Sunday only — this company works Saturdays. A day that is a weekly off is
    | never counted absent, which is the whole reason this is a list in a config
    | file rather than an assumption in a class.
    */
    'week_off' => [0],

    /*
    | ─────────────────────────────────────────────────────────────────────────
    | WHAT IS DELIBERATELY NOT HERE
    |
    | NO HOLIDAY LIST. Holidays come from the holiday announcements on the board
    | (decided 2026-09-03) — see App\Support\Holidays. HR posts "office closed
    | on the 14th" once, and that is both the notice everybody reads and the day
    | nobody is marked absent for. A second list here would be the one somebody
    | forgets to update, and then the board and the attendance record disagree
    | about whether the office was open.
    |
    | NO APPROVAL SETTINGS. A check-in is not a request (decided 2026-09-03), so
    | there is nothing to approve and no setting that could turn approval back
    | on. If a record is wrong, HR rejects it afterwards with a reason.
    |
    | NO LOCATION OR IP ALLOW-LIST. Nothing in the check-in flow captures where
    | somebody is, so a setting that claimed to enforce it would be a switch
    | wired to nothing.
    | ─────────────────────────────────────────────────────────────────────────
    */

];
