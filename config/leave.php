<?php

/*
|--------------------------------------------------------------------------
| Leave policy
|--------------------------------------------------------------------------
|
| ─────────────────────────────────────────────────────────────────────────
| THIS FILE IS A PLACEHOLDER FOR THE ADMIN PANEL
|
| Decided 2026-08-28: leave types and their annual entitlements are company
| policy, and company policy is configured in the Admin Panel (§12), not
| written into the application. Until that surface exists, the values live
| here so the Leave module has something real to read.
|
| When Settings lands, this file is replaced by a `leave_types` table and
| App\Support\LeavePolicy reads from that instead. Nothing else changes:
| every page already goes through the policy class rather than reaching for
| these values directly, precisely so that swap is one method.
|
| Do not add rules here that the Admin Panel will not be able to express.
| ─────────────────────────────────────────────────────────────────────────
|
| `days` is the annual entitlement. `null` means the type has no balance at
| all — unpaid leave is granted case by case, so showing a "balance" for it
| would be inventing an allowance nobody has.
|
| `accrual` is `'monthly'` or `'annual'` (default) — decided 2026-09-11:
| privilege and sick are granted in full at the start of an employee's own
| leave year; casual accrues monthly. See
| App\Support\LeavePolicy::accruedEntitlement, which is the only reader.
|
| `tone` maps to the chip colours in resources/css/pages/leave.css. These are
| categorical accents, never feedback colours: a leave type must not borrow the
| green that means "approved" or the red that means "rejected" (§7).
|
*/

return [

    'types' => [
        'casual' => [
            'label' => 'Casual Leave',
            'days' => 12,
            'accrual' => 'monthly',
            'tone' => 'lv-casual',
            'note' => 'Short personal absences.',
        ],
        'sick' => [
            'label' => 'Sick Leave',
            'days' => 12,
            'accrual' => 'annual',
            'tone' => 'lv-sick',
            'note' => 'Illness, medical appointments.',
        ],
        'privilege' => [
            'label' => 'Privilege Leave',
            'days' => 10,
            'accrual' => 'annual',
            'tone' => 'lv-privilege',
            'note' => 'Planned time off, usually booked ahead.',
        ],
        'unpaid' => [
            'label' => 'Unpaid Leave',
            'days' => null,
            'accrual' => 'annual',
            'tone' => 'lv-unpaid',
            'note' => 'Agreed case by case. No annual allowance.',
        ],
    ],

    /*
    | What happens to unused days at the end of the year is NOT decided
    | (2026-08-28). Rather than showing an "Expired" figure whose rule does not
    | exist — which the handover did, reading zero — the balance is stated for
    | the current year only and says so.
    |
    | "The year" is each employee's own leave year now (decided 2026-09-11) —
    | twelve months from their joining month, not a company-wide calendar year.
    | Unused days lapse at the end of it; there is still no carry-forward rule.
    |
    | When the rule is decided, this is where the carry-forward cap goes.
    */
    'carry_forward' => null,

    /*
    | One approver, whoever holds the leave permission (decided 2026-08-28).
    | At twelve people an approval chain adds delay and a second place for
    | requests to sit unnoticed.
    */
    'approvers' => 1,

];
