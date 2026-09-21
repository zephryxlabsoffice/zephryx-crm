<?php

namespace Tests\Unit;

use App\Support\Meetings\GoogleMeetProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * GoogleMeetProvider's own Calendar-calling logic.
 *
 * MeetingWritesTest exercises MeetingController against a fake MeetingProvider
 * and never touches this class — that is deliberate there, the same way it is
 * deliberate here: this file is the one place that proves the real Google
 * REST calls are shaped correctly, so the controller-level tests can keep
 * assuming "the provider behaves" without re-proving it every time.
 */
class GoogleMeetProviderTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function aMeeting(array $overrides = []): array
    {
        return $overrides + [
            'id' => 'MTG-2026-054',
            'title' => 'Sprint planning',
            'agenda' => 'Scope for the next two weeks.',
            'starts_at' => '2026-10-15 05:30:00',
            'ends_at' => '2026-10-15 06:15:00',
            'event_id' => null,
            'attendees' => [
                ['email' => 'manager@zephryxlabs.in'],
                ['email' => 'client@example.com'],
            ],
        ];
    }

    /* ══════════════════════════════════════════════════════════════════════
       create()
       ══════════════════════════════════════════════════════════════════════ */

    public function test_create_inserts_an_event_and_returns_what_google_sent_back(): void
    {
        $this->connectGoogleCalendar();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token'], 200),
            'https://www.googleapis.com/calendar/v3/calendars/*/events?*' => Http::response([
                'id' => 'evt_abc123',
                'hangoutLink' => 'https://meet.google.com/aaa-bbbb-ccc',
                'htmlLink' => 'https://calendar.google.com/event?eid=abc',
            ], 200),
        ]);

        $result = (new GoogleMeetProvider)->create($this->aMeeting());

        $this->assertSame('evt_abc123', $result['event_id']);
        $this->assertSame('https://meet.google.com/aaa-bbbb-ccc', $result['join_url']);
        $this->assertSame('https://calendar.google.com/event?eid=abc', $result['html_link']);
    }

    public function test_create_sends_the_conference_request_and_both_attendees(): void
    {
        $this->connectGoogleCalendar();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token'], 200),
            'https://www.googleapis.com/calendar/v3/calendars/*/events?*' => Http::response([
                'id' => 'evt_abc123',
                'hangoutLink' => 'https://meet.google.com/aaa-bbbb-ccc',
                'htmlLink' => 'https://calendar.google.com/event?eid=abc',
            ], 200),
        ]);

        (new GoogleMeetProvider)->create($this->aMeeting());

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/events?')) {
                return true; // not the event-insert call, ignore
            }

            // `conferenceDataVersion=1` — an insert without it produces an
            // event with no way to join (MeetingProvider's own point 2).
            $this->assertStringContainsString('conferenceDataVersion=1', $request->url());
            $this->assertStringContainsString('sendUpdates=all', $request->url());

            $body = $request->data();
            $this->assertSame('zephryx-MTG-2026-054', $body['conferenceData']['createRequest']['requestId']);
            $this->assertSame('hangoutsMeet', $body['conferenceData']['createRequest']['conferenceSolutionKey']['type']);
            $this->assertCount(2, $body['attendees']);
            $this->assertSame(
                ['manager@zephryxlabs.in', 'client@example.com'],
                array_column($body['attendees'], 'email'),
            );
            // Times carry UTC explicitly onto the wire — point 6.
            $this->assertSame('2026-10-15T05:30:00+00:00', $body['start']['dateTime']);
            $this->assertSame('UTC', $body['start']['timeZone']);

            return true;
        });
    }

    public function test_create_throws_when_google_returns_no_meet_link(): void
    {
        // An event with no way to join is exactly the failure this module
        // is shaped to avoid — treated as a failure, not returned.
        $this->connectGoogleCalendar();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token'], 200),
            'https://www.googleapis.com/calendar/v3/calendars/*/events?*' => Http::response([
                'id' => 'evt_abc123',
                'htmlLink' => 'https://calendar.google.com/event?eid=abc',
                // No hangoutLink.
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no Meet link');

        (new GoogleMeetProvider)->create($this->aMeeting());
    }

    public function test_create_throws_a_clear_message_when_google_is_not_connected(): void
    {
        // No connectGoogleCalendar() — the singleton row does not exist.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google is not connected');

        (new GoogleMeetProvider)->create($this->aMeeting());
    }

    public function test_create_throws_when_connected_for_drive_but_not_for_calendar(): void
    {
        // Drive needs no impersonation at all and can be connected on its
        // own — see CalendarClient's header comment. A key that uploads
        // payslips fine may still be missing what Calendar specifically
        // needs.
        $this->connectGoogleDrive();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Calendar ID or impersonation address');

        (new GoogleMeetProvider)->create($this->aMeeting());
    }

    /* ══════════════════════════════════════════════════════════════════════
       cancel()
       ══════════════════════════════════════════════════════════════════════ */

    public function test_cancel_deletes_the_event(): void
    {
        $this->connectGoogleCalendar();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token'], 200),
            'https://www.googleapis.com/calendar/v3/calendars/*/events/evt_abc123*' => Http::response('', 200),
        ]);

        (new GoogleMeetProvider)->cancel($this->aMeeting(['event_id' => 'evt_abc123']));

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), 'evt_abc123')
            && str_contains($request->url(), 'sendUpdates=all'));
    }

    public function test_cancel_tolerates_the_event_already_being_gone(): void
    {
        $this->connectGoogleCalendar();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token'], 200),
            'https://www.googleapis.com/calendar/v3/calendars/*/events/evt_abc123*' => Http::response('', 410),
        ]);

        // Reaching the end without an exception is the assertion.
        (new GoogleMeetProvider)->cancel($this->aMeeting(['event_id' => 'evt_abc123']));
        $this->assertTrue(true);
    }

    public function test_cancel_of_a_meeting_with_no_event_id_makes_no_call(): void
    {
        $this->connectGoogleCalendar();
        Http::fake(); // any call fails this test

        (new GoogleMeetProvider)->cancel($this->aMeeting(['event_id' => null]));

        Http::assertNothingSent();
    }

    /* ══════════════════════════════════════════════════════════════════════
       refreshAttendance()
       ══════════════════════════════════════════════════════════════════════ */

    public function test_refresh_attendance_maps_googles_response_statuses(): void
    {
        $this->connectGoogleCalendar();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token'], 200),
            'https://www.googleapis.com/calendar/v3/calendars/*/events/evt_abc123*' => Http::response([
                'id' => 'evt_abc123',
                'attendees' => [
                    ['email' => 'a@example.com', 'responseStatus' => 'accepted'],
                    ['email' => 'b@example.com', 'responseStatus' => 'declined'],
                    ['email' => 'c@example.com', 'responseStatus' => 'tentative'],
                    ['email' => 'd@example.com', 'responseStatus' => 'needsAction'],
                ],
            ], 200),
        ]);

        $result = (new GoogleMeetProvider)->refreshAttendance($this->aMeeting(['event_id' => 'evt_abc123']));

        $this->assertSame([
            'a@example.com' => 'accepted',
            'b@example.com' => 'declined',
            'c@example.com' => 'tentative',
            'd@example.com' => 'awaiting',
        ], $result);
    }

    public function test_refresh_attendance_of_a_meeting_with_no_event_id_makes_no_call(): void
    {
        $this->connectGoogleCalendar();
        Http::fake();

        $result = (new GoogleMeetProvider)->refreshAttendance($this->aMeeting(['event_id' => null]));

        $this->assertSame([], $result);
        Http::assertNothingSent();
    }
}
