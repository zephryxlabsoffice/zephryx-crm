<?php

namespace App\Support\Google;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Calendar, through domain-wide delegation impersonating the company
 * calendar.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE ASYMMETRY WITH DriveClient IS DELIBERATE
 *
 * Drive access is Shared Drive membership — no impersonation anywhere in that
 * class. Calendar needs the opposite: the service account has no calendar of
 * its own worth meeting on, so every call here impersonates
 * `GoogleConnection::impersonate_email` via the JWT's `sub` claim (see
 * GoogleAuth::token()), which is what domain-wide delegation actually grants.
 * This is the "note the asymmetry" the plan doc calls out under "Connecting
 * it" — Drive needs no delegation at all; this is the one job that does.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class CalendarClient
{
    public const SCOPE = 'https://www.googleapis.com/auth/calendar';

    protected const EVENTS_URL = 'https://www.googleapis.com/calendar/v3/calendars';

    public function __construct(
        protected GoogleAuth $auth,
        protected string $calendarId,
        protected string $impersonate,
    ) {}

    /**
     * Insert an event and request its Meet conference in the same call.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * `conferenceDataVersion=1` IS NOT OPTIONAL
     *
     * An event inserted without it exists on the calendar with no way to
     * join — the exact state that looks fine in a list and fails at the
     * meeting (MeetingProvider's own header comment, point 2). It is a query
     * parameter, not a body field, because that is where the Calendar API
     * puts it.
     *
     * `sendUpdates=all` is what actually sends the invite emails; without it
     * the event exists and nobody is told.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @param  array<string, mixed>  $event
     * @return array<string, mixed> the raw Calendar API event resource
     *
     * @throws RuntimeException
     */
    public function insertEvent(array $event): array
    {
        $response = Http::withToken($this->token())->post(
            self::EVENTS_URL.'/'.rawurlencode($this->calendarId).'/events'
                .'?conferenceDataVersion=1&sendUpdates=all',
            $event,
        );

        if ($response->failed()) {
            throw new RuntimeException('Google refused to create the event: '.$this->errorMessage($response));
        }

        return $response->json();
    }

    /**
     * Call an event off. Tolerant of it already being gone (404/410) — the
     * same shape as DriveClient::delete(), and for the same reason: the
     * caller is telling Google something that may already be true.
     *
     * @throws RuntimeException for anything other than "already gone"
     */
    public function deleteEvent(string $eventId): void
    {
        $response = Http::withToken($this->token())->delete(
            self::EVENTS_URL.'/'.rawurlencode($this->calendarId).'/events/'.rawurlencode($eventId)
                .'?sendUpdates=all',
        );

        if ($response->failed() && ! in_array($response->status(), [404, 410], true)) {
            throw new RuntimeException('Google refused to cancel the event: '.$this->errorMessage($response));
        }
    }

    /**
     * Read one event back — the RSVPs live on `attendees[].responseStatus`.
     *
     * @return array<string, mixed> the raw Calendar API event resource
     *
     * @throws RuntimeException
     */
    public function getEvent(string $eventId): array
    {
        $response = Http::withToken($this->token())->get(
            self::EVENTS_URL.'/'.rawurlencode($this->calendarId).'/events/'.rawurlencode($eventId),
        );

        if ($response->failed()) {
            throw new RuntimeException('Could not read the event back from Google: '.$this->errorMessage($response));
        }

        return $response->json();
    }

    protected function token(): string
    {
        return $this->auth->token([self::SCOPE], $this->impersonate);
    }

    protected function errorMessage(Response $response): string
    {
        return $response->json('error.message') ?? $response->body();
    }
}
