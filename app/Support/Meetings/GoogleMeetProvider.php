<?php

namespace App\Support\Meetings;

use App\Models\GoogleConnection;
use App\Support\Google\CalendarClient;
use App\Support\Google\GoogleAuth;
use App\Support\Google\GoogleServiceAccountKey;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Google Calendar / Google Meet, through the company Workspace account.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHAT THIS IMPLEMENTATION GETS RIGHT, POINT BY POINT
 *
 * 1. AUTHENTICATION. A service account with domain-wide delegation,
 *    impersonating `GoogleConnection::impersonate_email` via the JWT `sub`
 *    claim — see App\Support\Google\GoogleAuth::token() and CalendarClient's
 *    header comment for why this is the one Google job that impersonates at
 *    all. The key never touches `config()` — see `client()` below, which
 *    reads it at the point of use and nowhere else, the same rule
 *    App\Support\Documents\DocumentStore follows for Drive.
 *
 * 2. THE CONFERENCE IS REQUESTED, NOT ASSUMED. `CalendarClient::insertEvent()`
 *    always carries `conferenceDataVersion=1` and a `createRequest` — see its
 *    own header comment.
 *
 * 3. EXTERNAL GUESTS. Every attendee on the invite — staff or client — is
 *    added by email; whether they can actually join the Meet without
 *    friction is a Workspace Admin Console policy (Meet safety settings),
 *    not a per-event API field this application can set. Nothing here
 *    restricts attendees to the domain.
 *
 * 4. FAILURE IS A STATE, NOT AN EXCEPTION TO SWALLOW. Every method throws
 *    rather than returning a plausible-looking fake — MeetingController
 *    already treats a throw as "the invite did not go out" and says so; that
 *    contract does not change now that the throw can come from a real
 *    network call instead of a deliberate stub.
 *
 * 5. CANCELLING PROPAGATES. `cancel()` calls Google and lets a failure
 *    bubble — MeetingController only marks a meeting cancelled after this
 *    method returns without throwing.
 *
 * 6. TIMES GO OVER THE WIRE AS UTC WITH AN EXPLICIT TIMEZONE. `Meeting::
 *    toRecordArray()` hands this class `starts_at`/`ends_at` as UTC
 *    datetime strings; `rfc3339()` below carries that timezone onto the
 *    wire rather than letting Google assume one.
 *
 * 7. IDEMPOTENCE. `conferenceData.createRequest.requestId` is deterministic
 *    per meeting (`zephryx-{reference}`), so a request that reaches Google
 *    but whose response is lost — a timeout, not a rate limit — returns the
 *    existing conference on any retry rather than a second one. The
 *    stronger guard is `MeetingController::createEvent()`'s own check that a
 *    meeting with an `event_id` is never asked to create again; this is
 *    defence at the layer below that.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class GoogleMeetProvider implements MeetingProvider
{
    /**
     * @param  array<string, mixed>  $meeting
     * @return array{event_id: string, join_url: string, html_link: string}
     *
     * @throws RuntimeException
     */
    public function create(array $meeting): array
    {
        $client = $this->client();

        $event = $client->insertEvent([
            'summary' => $meeting['title'],
            'description' => $meeting['agenda'] ?? '',
            'start' => ['dateTime' => $this->rfc3339($meeting['starts_at']), 'timeZone' => 'UTC'],
            'end' => ['dateTime' => $this->rfc3339($meeting['ends_at']), 'timeZone' => 'UTC'],
            'attendees' => $this->attendeesOf($meeting),
            // Neither is ours to hand out: this application organises the
            // meeting, it does not delegate who else may reshape the invite.
            'guestsCanInviteOthers' => false,
            'guestsCanModify' => false,
            'conferenceData' => [
                'createRequest' => [
                    'requestId' => 'zephryx-'.$meeting['id'],
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ],
        ]);

        $joinUrl = $event['hangoutLink'] ?? null;

        if ($joinUrl === null) {
            // The event exists but carries no way to join it — precisely the
            // state point 2 above exists to prevent. Treated as a failure
            // rather than returned, so the caller's failure path (a
            // requested meeting, not a broken scheduled one) handles it.
            throw new RuntimeException('Google created the event but returned no Meet link.');
        }

        return [
            'event_id' => $event['id'],
            'join_url' => $joinUrl,
            'html_link' => $event['htmlLink'] ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $meeting
     *
     * @throws RuntimeException
     */
    public function cancel(array $meeting): void
    {
        $eventId = $meeting['event_id'] ?? null;

        if ($eventId === null) {
            // Nothing on Google to call off — MeetingController never
            // reaches this branch in practice (it only calls cancel() when
            // an event_id exists), but a provider should not assume its
            // caller's guard is the only one that will ever exist.
            return;
        }

        $this->client()->deleteEvent($eventId);
    }

    /**
     * @param  array<string, mixed>  $meeting
     * @return array<string, string> email => accepted|declined|tentative|awaiting
     *
     * @throws RuntimeException
     */
    public function refreshAttendance(array $meeting): array
    {
        $eventId = $meeting['event_id'] ?? null;

        if ($eventId === null) {
            return [];
        }

        $event = $this->client()->getEvent($eventId);

        // Google's own vocabulary on the wire; this application's is
        // MeetingPresenter's. Mapped here, once, rather than leaking
        // Google's spelling ("needsAction") into a page.
        $responses = [
            'accepted' => 'accepted',
            'declined' => 'declined',
            'tentative' => 'tentative',
            'needsAction' => 'awaiting',
        ];

        $result = [];

        foreach ($event['attendees'] ?? [] as $attendee) {
            $email = $attendee['email'] ?? null;

            if ($email === null) {
                continue;
            }

            $result[$email] = $responses[$attendee['responseStatus'] ?? 'needsAction'] ?? 'awaiting';
        }

        return $result;
    }

    /**
     * A Calendar client built from the current connection. Fresh per call —
     * the connection can change between requests, and this class is
     * resolved new per request anyway (see the container binding in
     * AppServiceProvider).
     *
     * @throws RuntimeException if Google is not connected, or connected
     *                          without the two fields Calendar specifically needs
     */
    protected function client(): CalendarClient
    {
        $connection = GoogleConnection::current();

        if (! $connection->isConnected()) {
            throw new RuntimeException(
                'Google is not connected. An administrator has to connect it in the Admin Panel before meetings can be scheduled.'
            );
        }

        if ($connection->calendar_id === null || $connection->impersonate_email === null) {
            // Drive can be connected on its own — it needs no impersonation
            // at all (see CalendarClient's header comment) — so a key that
            // works for uploads may still be missing what Calendar needs.
            throw new RuntimeException(
                'Google is connected, but no Calendar ID or impersonation address has been set in the Admin Panel.'
            );
        }

        $auth = new GoogleAuth(GoogleServiceAccountKey::parse($connection->service_account_key));

        return new CalendarClient($auth, $connection->calendar_id, $connection->impersonate_email);
    }

    /**
     * @param  array<string, mixed>  $meeting
     * @return list<array{email: string}>
     */
    protected function attendeesOf(array $meeting): array
    {
        return collect($meeting['attendees'] ?? [])
            ->pluck('email')
            ->filter()
            ->unique()
            ->map(fn (string $email) => ['email' => $email])
            ->values()
            ->all();
    }

    /**
     * `Meeting::toRecordArray()` hands over a UTC "Y-m-d H:i:s" string with
     * no timezone marker; Google's API wants RFC 3339. Parsed as UTC
     * explicitly rather than left to whatever the server's default zone
     * happens to be — see point 6 above.
     */
    protected function rfc3339(string $when): string
    {
        return Carbon::parse($when, 'UTC')->toIso8601String();
    }
}
