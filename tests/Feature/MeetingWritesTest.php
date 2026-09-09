<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingAttendee;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\MeetingPresenter as P;
use App\Support\Meetings\MeetingProvider;
use App\Support\Rbac\Rbac;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Meetings — scheduling, creating the calendar event, and calling one off.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE PROVIDER IS THE THING BEING TESTED AROUND
 *
 * GoogleMeetProvider throws on every method and is bound that way on purpose.
 * These tests bind fakes in its place — one that answers, one that fails — and
 * assert the two behaviours that matter: a failure leaves the meeting visibly
 * unscheduled, and a cancellation that Google refuses does NOT cancel it here.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class MeetingWritesTest extends TestCase
{
    /* ══════════════════════════════════════════════════════════════════════
       WHO MAY SCHEDULE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_an_employee_may_see_meetings_and_not_schedule_one(): void
    {
        // Decided 2026-08-28: only a project manager or the system admin
        // creates one.
        $this->signInAsEmployee();

        $this->get('/meetings')->assertOk();
        $this->get('/meetings/schedule')->assertForbidden();
        $this->post('/meetings', $this->validPayload())->assertForbidden();
    }

    public function test_a_manager_may_schedule(): void
    {
        $this->fakeProvider();
        $this->signInAsManager();

        $this->get('/meetings/schedule')->assertOk();
        $this->post('/meetings', $this->validPayload())->assertRedirect();

        $this->assertSame(1, Meeting::count());
    }

    /* ══════════════════════════════════════════════════════════════════════
       TIMES, AND THE ONE PLACE THEY ARE CONVERTED
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_time_typed_in_the_display_zone_is_stored_as_utc(): void
    {
        /*
         * A meeting is the one record where a timezone mistake means people
         * miss it. 11:00 IST is 05:30 UTC, and the conversion happens once.
         */
        $this->fakeProvider();
        $this->signInAsManager();

        $this->post('/meetings', $this->validPayload([
            'date' => '2026-10-15',
            'time' => '11:00',
            'duration' => 45,
        ]))->assertRedirect();

        $meeting = Meeting::firstOrFail();

        $this->assertSame('2026-10-15 05:30:00', $meeting->starts_at->toDateTimeString());
        $this->assertSame('2026-10-15 06:15:00', $meeting->ends_at->toDateTimeString());

        /*
         * And it reads back as the time somebody typed — through the row, which
         * is how every page gets it.
         *
         * Asserted on the row rather than on the model's Carbon deliberately:
         * the application's timezone is the office's, so the cast labels a
         * stored UTC value as local. The row hands the presenter a plain string
         * and the presenter reads it as UTC, which is the one path all of this
         * goes through.
         */
        $row = $meeting->toRecordArray(null);

        $this->assertSame('11:00 AM', P::time($row['starts_at']));
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE CALENDAR EVENT
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_successful_create_stores_what_google_returned(): void
    {
        // The link comes back from Google or there is no link — it is never
        // assembled from an event id.
        $this->fakeProvider();
        $this->signInAsManager();

        $this->post('/meetings', $this->validPayload())->assertRedirect();

        $meeting = Meeting::firstOrFail();

        $this->assertSame('evt_from_google', $meeting->event_id);
        $this->assertSame('https://meet.google.com/aaa-bbbb-ccc', $meeting->join_url);
        $this->assertSame(P::SCHEDULED, $meeting->toRecordArray(null)['status']);
    }

    public function test_a_failed_create_leaves_the_meeting_requested_and_says_so(): void
    {
        /*
         * The failure this module is shaped around. What it must never do is
         * swallow the error and show a scheduled meeting with no way to join —
         * somebody would sit in a room that does not exist.
         */
        $this->failingProvider();
        $this->signInAsManager();

        $response = $this->post('/meetings', $this->validPayload());

        $meeting = Meeting::firstOrFail();

        $this->assertNull($meeting->event_id);
        $this->assertNull($meeting->join_url);
        $this->assertSame(P::REQUESTED, $meeting->toRecordArray(null)['status']);

        $response->assertSessionHas('status_tone', 'warning');
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::MEETING_EVENT_FAILED)->count());
    }

    public function test_creating_the_event_again_does_not_make_a_second_one(): void
    {
        // Twice must not mean two events and two sets of invites.
        $this->fakeProvider();
        $this->signInAsManager();

        $this->post('/meetings', $this->validPayload());
        $meeting = Meeting::firstOrFail();

        $this->post('/meetings/'.$meeting->reference.'/create')->assertRedirect();

        $this->assertSame('evt_from_google', $meeting->fresh()->event_id);
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::MEETING_EVENT_CREATED)->count());
    }

    public function test_a_failed_create_can_be_retried(): void
    {
        $this->failingProvider();
        $this->signInAsManager();

        $this->post('/meetings', $this->validPayload());
        $meeting = Meeting::firstOrFail();

        $this->fakeProvider();
        $this->post('/meetings/'.$meeting->reference.'/create')->assertRedirect();

        $this->assertSame('evt_from_google', $meeting->fresh()->event_id);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE JOIN LINK
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_organiser_is_always_on_the_invite(): void
    {
        // An organiser who cannot see the link is somebody locked out of their
        // own meeting.
        $this->fakeProvider();
        $organiser = $this->signInAsManager();

        $this->post('/meetings', $this->validPayload(['attendees' => []]));

        $meeting = Meeting::with(['attendees.user', 'organiser.user'])->firstOrFail();

        $this->assertTrue($meeting->isAttendedBy($organiser->user));
        $this->assertNotNull($meeting->toRecordArray($organiser->user)['join_url']);
    }

    public function test_somebody_not_on_the_invite_gets_no_link(): void
    {
        $this->fakeProvider();
        $this->signInAsManager();
        $this->post('/meetings', $this->validPayload(['attendees' => []]));

        $meeting = Meeting::with(['attendees.user', 'organiser.user'])->firstOrFail();
        $outsider = User::factory()->create(['user_id' => 'EMP921', 'account_type' => 'staff']);

        $this->assertNull($meeting->toRecordArray($outsider)['join_url']);
        // And the record still has one — it is withheld, not missing.
        $this->assertNotNull($meeting->join_url);
    }

    /* ══════════════════════════════════════════════════════════════════════
       CANCELLING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_cancelling_tells_google_first(): void
    {
        $this->fakeProvider();
        $this->signInAsManager();
        $this->post('/meetings', $this->validPayload());

        $meeting = Meeting::firstOrFail();

        $this->post('/meetings/'.$meeting->reference.'/cancel', [
            'reason' => 'The client asked to move it to next month.',
        ])->assertRedirect();

        $meeting->refresh();

        $this->assertNotNull($meeting->cancelled_at);
        $this->assertSame(P::CANCELLED, $meeting->toRecordArray(null)['status']);
        $this->assertTrue(app('meetings.cancelled'), 'the provider was never told');
    }

    public function test_a_cancellation_google_refuses_does_not_cancel_it_here(): void
    {
        /*
         * The worst outcome in this module: a meeting the organiser believes is
         * off, still live on Google, with the attendees turning up. So a failed
         * cancel leaves the meeting scheduled and says exactly that.
         */
        $this->fakeProvider();
        $this->signInAsManager();
        $this->post('/meetings', $this->validPayload());

        $meeting = Meeting::firstOrFail();

        $this->failingProvider();

        $this->post('/meetings/'.$meeting->reference.'/cancel', [
            'reason' => 'The client asked to move it to next month.',
        ])->assertSessionHasErrors('reason');

        $this->assertNull($meeting->fresh()->cancelled_at);
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::MEETING_CANCEL_FAILED)->count());
    }

    public function test_cancelling_needs_a_reason(): void
    {
        $this->fakeProvider();
        $this->signInAsManager();
        $this->post('/meetings', $this->validPayload());

        $meeting = Meeting::firstOrFail();

        $this->post('/meetings/'.$meeting->reference.'/cancel', ['reason' => ''])
            ->assertSessionHasErrors('reason');
    }

    /* ══════════════════════════════════════════════════════════════════════
       A CLIENT ASKS — WHICH IS NOT SCHEDULING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_client_request_creates_a_meeting_with_no_event(): void
    {
        // A client cannot put anything in anybody's calendar. The request
        // produces a record somebody here then creates.
        $this->signInAsClient('DGL International School');

        $this->post('/client/meetings/request', [
            'title' => 'Progress catch-up',
            'agenda' => 'Where the admissions pages have got to.',
            'date' => Carbon::today()->addWeek()->toDateString(),
            'time' => '15:00',
            'duration' => 30,
        ])->assertRedirect();

        $meeting = Meeting::firstOrFail();

        $this->assertNull($meeting->event_id);
        $this->assertNull($meeting->organiser_id);
        $this->assertNotNull($meeting->requested_by_client_id);
        $this->assertSame(P::REQUESTED, $meeting->toRecordArray(null)['status']);
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::MEETING_REQUESTED)->count());
    }

    public function test_a_client_cannot_attach_a_request_to_somebody_elses_project(): void
    {
        // §6, re-checked on the write rather than trusted from the select.
        $theirs = Client::create(['reference' => 'CLT930', 'name' => 'Another Client', 'status' => 'active']);
        $project = \App\Models\Project::create([
            'reference' => 'PRJ-THEIRS-1', 'name' => 'Their Project', 'client_id' => $theirs->id,
            'progress' => 0, 'status' => 'planning', 'priority' => 'medium',
            'deadline' => Carbon::today()->addMonth(),
        ]);

        $this->signInAsClient('DGL International School');

        $this->post('/client/meetings/request', [
            'title' => 'Progress catch-up',
            'date' => Carbon::today()->addWeek()->toDateString(),
            'time' => '15:00',
            'project_id' => $project->id,
        ])->assertSessionHasErrors('project_id');

        $this->assertSame(0, Meeting::count());
    }

    public function test_nothing_anywhere_records_an_rsvp(): void
    {
        /*
         * Responses belong to Google Calendar. A second accept button here
         * would be a second source of truth for the same fact — so there is no
         * route that sets one, and no provider method either.
         */
        foreach (app('router')->getRoutes() as $route) {
            $this->assertStringNotContainsString('rsvp', $route->uri());
            $this->assertStringNotContainsString('respond', $route->uri());
        }

        $this->assertFalse(method_exists(MeetingProvider::class, 'setAttendance'));
    }

    /* ══════════════════════════════════════════════════════════════════════
       HELPERS
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * A provider that answers, the way Google would.
     */
    protected function fakeProvider(): void
    {
        app()->instance('meetings.cancelled', false);

        $this->app->bind(MeetingProvider::class, fn () => new class implements MeetingProvider
        {
            public function create(array $meeting): array
            {
                return [
                    'event_id' => 'evt_from_google',
                    'join_url' => 'https://meet.google.com/aaa-bbbb-ccc',
                    'html_link' => 'https://calendar.google.com/event?eid=abc',
                ];
            }

            public function cancel(array $meeting): void
            {
                app()->instance('meetings.cancelled', true);
            }

            public function refreshAttendance(array $meeting): array
            {
                return [];
            }
        });
    }

    /**
     * A provider that fails, the way Google does when it rate-limits.
     */
    protected function failingProvider(): void
    {
        $this->app->bind(MeetingProvider::class, fn () => new class implements MeetingProvider
        {
            public function create(array $meeting): array
            {
                throw new RuntimeException('Rate limited.');
            }

            public function cancel(array $meeting): void
            {
                throw new RuntimeException('Rate limited.');
            }

            public function refreshAttendance(array $meeting): array
            {
                throw new RuntimeException('Rate limited.');
            }
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validPayload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Sprint planning',
            'agenda' => 'Scope for the next two weeks.',
            'date' => Carbon::today()->addDays(3)->toDateString(),
            'time' => '11:00',
            'duration' => 30,
            'attendees' => [],
        ];
    }

    protected function signInAsEmployee(): Employee
    {
        return $this->signInWith(['employee'], 'EMP920');
    }

    protected function signInAsManager(): Employee
    {
        return $this->signInWith(['employee', 'manager'], 'EMP919');
    }

    /**
     * @param  list<string>  $roles
     */
    protected function signInWith(array $roles, string $staffId): Employee
    {
        $user = User::factory()->create([
            'user_id' => $staffId,
            'account_type' => 'staff',
            'staff_kind' => 'employee',
            'status' => 'active',
        ]);

        $user->roles()->sync(Role::whereIn('role_key', $roles)->pluck('id'));

        app(Rbac::class)->forget($user);
        $this->actingAs($user);

        return Employee::create(['user_id' => $user->id, 'joined_on' => Carbon::now()->subYear()])->fresh('user');
    }
}
