<?php

namespace App\Support\Meetings;

/**
 * The conferencing service a meeting actually lives on.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHY THIS IS AN INTERFACE AND NOT A GOOGLE CLASS
 *
 * Not to keep options open — Google Meet is the decision (2026-08-28). It is an
 * interface because the seam is where the failures live, and naming it makes
 * them impossible to skip:
 *
 *   - `create()` can fail. When it does, the meeting is NOT scheduled, and the
 *     application has to say so rather than showing a row with a missing link.
 *   - `cancel()` can fail. A meeting called off here but still live on Google
 *     means attendees keep the invite and turn up.
 *   - `refreshAttendance()` reads state we do not own. An RSVP belongs to
 *     Google Calendar; this application displays it and must never write it.
 *
 * A concrete class with these methods inlined would let a caller forget that
 * every one of them is a network call to somebody else's system.
 * ─────────────────────────────────────────────────────────────────────────────
 */
interface MeetingProvider
{
    /**
     * Create the calendar event and its conference, returning what Google
     * assigned.
     *
     * The join URL is whatever comes back. It is never assembled from an event
     * id or a room name: a Meet link that this application invented would be a
     * link to nothing, rendered as though it worked.
     *
     * @param  array<string, mixed>  $meeting
     * @return array{event_id: string, join_url: string, html_link: string}
     */
    public function create(array $meeting): array;

    /**
     * Call the meeting off on Google, so the invite disappears from everyone's
     * calendar.
     *
     * Cancelling here without cancelling there is worse than not cancelling at
     * all: the organiser believes it is off and the attendees turn up.
     *
     * @param  array<string, mixed>  $meeting
     */
    public function cancel(array $meeting): void;

    /**
     * Read every attendee's RSVP back from Google.
     *
     * One direction only. Google Calendar owns responses — a person accepts or
     * declines in their own calendar, and this application reports what they
     * did. There is deliberately no `setAttendance()`.
     *
     * @param  array<string, mixed>  $meeting
     * @return array<string, string> email => accepted|declined|tentative|awaiting
     */
    public function refreshAttendance(array $meeting): array;
}
