<?php

namespace App\Support\Meetings;

use RuntimeException;

/**
 * Google Calendar / Google Meet, through the company Workspace account.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * NOT IMPLEMENTED YET — AND DELIBERATELY NOT STUBBED
 *
 * Every method throws. It would be easy to return a plausible-looking event id
 * and a fabricated `meet.google.com/abc-defg-hij` so the pages look finished,
 * and that is precisely the failure worth avoiding: a join button that renders,
 * is clickable, and goes to a room that does not exist. Somebody would sit in
 * it waiting for a client.
 *
 * So the front end shows meetings as *requested* until a real provider is bound,
 * and says so.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHAT THE IMPLEMENTATION HAS TO GET RIGHT
 *
 * 1. AUTHENTICATION. A service account with domain-wide delegation,
 *    impersonating the calendar in `config('meetings.google.impersonate')`. The
 *    key file lives outside the web root and is never committed (§11.1).
 *
 * 2. THE CONFERENCE IS REQUESTED, NOT ASSUMED. A Calendar event only gets a
 *    Meet link when it is inserted with `conferenceDataVersion=1` and a
 *    `createRequest`. Insert without it and the event exists with no way to
 *    join — the exact state that looks fine in a list and fails at the meeting.
 *
 * 3. EXTERNAL GUESTS. Clients must be able to join without friction
 *    (decided 2026-08-28), which means the event allows guests outside the
 *    Workspace domain and the invite carries the link.
 *
 * 4. FAILURE IS A STATE, NOT AN EXCEPTION TO SWALLOW. Google rate-limits, and
 *    tokens expire. A failed create leaves the meeting requested-but-not-
 *    scheduled and that must be visible, not retried silently until somebody
 *    notices nobody was invited.
 *
 * 5. CANCELLING PROPAGATES. See MeetingProvider::cancel().
 *
 * 6. TIMES GO OVER THE WIRE AS UTC with an explicit timezone on the event.
 *
 * 7. IDEMPOTENCE. Creating twice for one meeting must not produce two events
 *    and two sets of invites — carry a request id, and check for an existing
 *    `event_id` inside the transaction rather than before it.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class GoogleMeetProvider implements MeetingProvider
{
    public function create(array $meeting): array
    {
        $this->notBuilt();
    }

    public function cancel(array $meeting): void
    {
        $this->notBuilt();
    }

    public function refreshAttendance(array $meeting): array
    {
        $this->notBuilt();
    }

    /**
     * @throws RuntimeException
     */
    protected function notBuilt(): never
    {
        throw new RuntimeException(
            'GoogleMeetProvider is not implemented. Meetings can be requested and recorded, '
            .'but no Google event or Meet link exists until it is — see the notes on this class. '
            .'Returning a fabricated join link here would render a button that goes nowhere.'
        );
    }
}
