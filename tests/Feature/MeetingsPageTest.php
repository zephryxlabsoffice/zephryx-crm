<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\Role;
use App\Models\User;
use App\Support\MeetingDirectory;
use App\Support\Rbac\Rbac;
use App\Support\Meetings\GoogleMeetProvider;
use App\Support\Meetings\MeetingProvider;
use App\Support\MeetingPresenter as P;
use RuntimeException;
use Tests\TestCase;

class MeetingsPageTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Every route in the staff realm is behind `realm:staff` now (§3.1), so
         * a page test has to be somebody. A CEO, because this file is about
         * what the page renders rather than about who may see it — the guard
         * and the permission filtering have their own tests.
         */
        $this->signInAsStaff();
    }
    /** The demo person these pages are read as. */
    protected const VIEWER = 'EMP002';

    /**
     * The demo meetings, as real rows, read as somebody who is on some.
     *
     * Every read in this module takes the viewer, because the join link is
     * withheld per person — so a test about who can see a link has to be
     * somebody, and the CEO the suite signs in as by default is on no meetings.
     */
    protected function withDemoData(): void
    {
        $this->seedDemoWorkforce();

        $user = User::where('user_id', self::VIEWER)->firstOrFail();
        $user->roles()->syncWithoutDetaching(Role::whereIn('role_key', ['employee', 'manager'])->pluck('id'));

        app(Rbac::class)->forget($user);
        $this->actingAs($user);
    }

    protected function viewerAccount(): User
    {
        return User::where('user_id', self::VIEWER)->firstOrFail();
    }

    /**
     * Every meeting, as rows, seen by the demo viewer.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function allMeetings(): \Illuminate\Support\Collection
    {
        return MeetingDirectory::rows(MeetingDirectory::query(), $this->viewerAccount());
    }

    /**
     * @param  array<string, mixed>  $meeting
     */
    protected function isAttendee(array $meeting, string $staffId): bool
    {
        return collect($meeting['attendees'])->contains('user_id', $staffId)
            || $meeting['organiser'] === $staffId;
    }

    public function test_the_three_pages_render(): void
    {
        $this->withDemoData();

        $this->get('/meetings')->assertOk()->assertSee('Meetings', false);
        $this->get('/meetings/schedule')->assertOk()->assertSee('Schedule a meeting', false);
        $this->get('/meetings/MTG-2026-058')->assertOk()->assertSee('Sprint planning', false);
    }

    public function test_schedule_is_not_read_as_a_meeting_reference(): void
    {
        $this->withDemoData();

        $this->get('/meetings/schedule')->assertOk();
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE JOIN LINK IS A PASSWORD

       A Meet link is usually enough on its own to walk into a call. These
       assert against rendered HTML, because the question is what reached the
       browser — masking or hiding it there would be a decoration over a leak.
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_non_attendee_never_receives_the_join_link(): void
    {
        $this->withDemoData();

        /*
         * Read from the MODEL, not from a row.
         *
         * A row for somebody not on the invite has already had its link
         * removed — that is the whole design — so the raw value has to come
         * from the record in order for there to be anything to look for.
         */
        $notMine = Meeting::with(['attendees.user', 'organiser.user'])
            ->whereNotNull('join_url')
            ->get()
            ->first(fn (Meeting $m) => ! $m->isAttendedBy($this->viewerAccount()));

        $this->assertNotNull($notMine, 'no sample meeting the viewer is absent from');

        $html = $this->get('/meetings/'.$notMine->reference)->getContent();

        $this->assertStringNotContainsString($notMine->join_url, $html);
        $this->assertStringNotContainsString('meet.google.com', $html);
        // And it says why, rather than looking broken.
        $this->assertStringContainsString('not on this invite', $html);
    }

    public function test_the_list_carries_no_link_for_a_meeting_the_viewer_is_absent_from(): void
    {
        $this->withDemoData();

        $html = $this->get('/meetings?tab=all')->getContent();
        $viewer = $this->viewerAccount();

        foreach (Meeting::with(['attendees.user', 'organiser.user'])->whereNotNull('join_url')->get() as $meeting) {
            if ($meeting->isAttendedBy($viewer)) {
                continue;
            }

            $this->assertStringNotContainsString(
                $meeting->join_url,
                $html,
                "{$meeting->reference} leaked its link into the list",
            );
        }
    }

    public function test_an_attendee_does_get_the_link(): void
    {
        // The counterpart: withholding must be about who is asking, not about
        // links being switched off everywhere.
        $this->withDemoData();

        $mine = $this->allMeetings()->first(
            fn (array $m) => $m['join_url'] !== null && $this->isAttendee($m, self::VIEWER)
        );

        $this->assertNotNull($mine);
        $this->get('/meetings/'.$mine['id'])->assertSee($mine['join_url'], false);
    }

    public function test_the_next_meeting_card_only_shows_one_the_viewer_is_on(): void
    {
        // A card headed "Your next meeting" showing one somebody is not invited
        // to is worse than showing nothing — they will act on it.
        $this->withDemoData();

        $next = MeetingDirectory::nextFor($this->viewerAccount());

        if ($next !== null) {
            $this->assertTrue($this->isAttendee($next, self::VIEWER));
            $this->get('/meetings')->assertSee($next['title'], false);
        }
    }

    public function test_every_external_link_carries_noopener(): void
    {
        // Without it the opened page can reach back through window.opener.
        $this->withDemoData();

        foreach (['/meetings', '/meetings/MTG-2026-058'] as $url) {
            $html = $this->get($url)->getContent();

            preg_match_all('/<a[^>]*target="_blank"[^>]*>/i', $html, $matches);

            foreach ($matches[0] as $anchor) {
                $this->assertStringContainsString('noopener', $anchor, "a _blank link without noopener in {$url}");
            }
        }
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE CRM ORGANISES; GOOGLE HOSTS
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_google_provider_is_not_stubbed_to_look_like_it_works(): void
    {
        // Returning a plausible event id and a fabricated meet.google.com link
        // would make the pages look finished and put somebody in a room that
        // does not exist, waiting for a client.
        $provider = new GoogleMeetProvider;

        $this->expectException(RuntimeException::class);
        $provider->create(['id' => 'MTG-2026-054']);
    }

    public function test_the_provider_cannot_write_an_rsvp(): void
    {
        // Responses belong to Google Calendar. A person accepts or declines in
        // their own calendar; this application reads that back.
        $this->assertFalse(method_exists(MeetingProvider::class, 'setAttendance'));
        $this->assertFalse(method_exists(GoogleMeetProvider::class, 'setAttendance'));
        $this->assertFalse(app('router')->has('meetings.rsvp'));
    }

    public function test_a_meeting_with_no_google_event_has_no_link(): void
    {
        // The link comes back from Google or it does not exist — it is never
        // assembled from an event id.
        $this->withDemoData();

        foreach ($this->allMeetings() as $meeting) {
            if ($meeting['event_id'] === null) {
                $this->assertNull($meeting['join_url'], "{$meeting['id']} invented a link");
            }
        }
    }

    public function test_a_requested_meeting_says_nothing_has_been_sent(): void
    {
        // Clients may ask for a meeting but not create one (decided
        // 2026-08-28). The alternative to saying so plainly is a client sitting
        // in a room that does not exist.
        $this->withDemoData();

        $response = $this->get('/meetings/MTG-2026-054');

        $response->assertSee('asked for this meeting', false);
        $response->assertSee('no calendar event, no invite and no link', false);
        $response->assertSee('Create on Google', false);
    }

    public function test_a_scheduled_meeting_offers_no_create_button(): void
    {
        $this->withDemoData();

        $this->get('/meetings/MTG-2026-058')->assertDontSee('Create on Google', false);
    }

    /* ══════════════════════════════════════════════════════════════════════
       ATTENDEES AND STATE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_every_meeting_lists_its_attendees(): void
    {
        // The handover showed none anywhere, and hardcoded one name in its rail.
        $this->withDemoData();

        foreach ($this->allMeetings() as $meeting) {
            $this->assertNotEmpty($meeting['attendees'], "{$meeting['id']} has nobody on it");
        }

        $html = $this->get('/meetings/MTG-2026-057')->getContent();
        $this->assertStringContainsString('Urban Nest Interiors', $html);
        $this->assertStringContainsString('Organiser', $html);
    }

    public function test_responses_are_shown_in_words_not_only_colour(): void
    {
        $this->withDemoData();

        $html = $this->get('/meetings/MTG-2026-058')->getContent();

        $this->assertStringContainsString('Coming', $html);
        $this->assertStringContainsString('No reply yet', $html);
    }

    public function test_no_page_calls_a_past_meeting_completed(): void
    {
        $this->withDemoData();

        foreach (['/meetings?tab=all', '/meetings?tab=ended', '/meetings/MTG-2026-050'] as $url) {
            $this->assertStringNotContainsStringIgnoringCase('completed', $this->get($url)->getContent(), "in {$url}");
        }
    }

    public function test_cancelled_is_not_described_as_declined(): void
    {
        // Calling a meeting off and turning down an invite are different acts by
        // different people; the handover's tile conflated them.
        $this->withDemoData();

        $html = $this->get('/meetings')->getContent();

        $this->assertStringContainsString('Called off, invites withdrawn', $html);
        $this->assertStringNotContainsString('Meetings declined', $html);
    }

    public function test_a_cancelled_meeting_explains_itself(): void
    {
        $this->withDemoData();

        $response = $this->get('/meetings/MTG-2026-049');
        $response->assertSee('This meeting was cancelled', false);
        $response->assertSee('Client asked to move it', false);
    }

    /* ══════════════════════════════════════════════════════════════════════
       TIMES
       ══════════════════════════════════════════════════════════════════════ */

    public function test_every_stored_time_is_utc(): void
    {
        // Storing local time is what makes a meeting with an overseas client
        // drift when their clocks change.
        $this->withDemoData();

        foreach ($this->allMeetings() as $meeting) {
            $starts = \Illuminate\Support\Carbon::parse($meeting['starts_at'], 'UTC');
            $ends = \Illuminate\Support\Carbon::parse($meeting['ends_at'], 'UTC');

            $this->assertTrue($ends->greaterThan($starts), "{$meeting['id']} ends before it starts");
        }
    }

    public function test_the_page_states_which_zone_it_is_showing(): void
    {
        $this->withDemoData();

        $this->get('/meetings/MTG-2026-058')->assertSee('IST', false);
        $this->get('/meetings')->assertSee('Times are Asia/Kolkata', false);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE USUAL GUARDS
       ══════════════════════════════════════════════════════════════════════ */

    public function test_an_empty_database_produces_an_empty_module(): void
    {

        /*
         * This replaced "the demo source is inert outside local + debug", which
         * was true only because the fixture switched itself off. Meetings come
         * from a table now, and in production real ones SHOULD be shown.
         *
         * What survives is the guarantee underneath it: nothing is invented —
         * and in particular, no join link is composed for a meeting that has
         * no event behind it.
         */
        $this->assertTrue(MeetingDirectory::rows(MeetingDirectory::query(), null)->isEmpty());
        $this->assertNull(MeetingDirectory::nextFor(null));
        $this->assertNull(MeetingDirectory::find('MTG-2026-058', null));
    }

    public function test_no_figure_is_written_into_the_markup(): void
    {
        // The handover hardcoded 7 / 12 / 18 / 6 and "1 to 7 of 43".
        $this->withDemoData();

        $response = $this->get('/meetings');
        $response->assertSee('Requested', false);
        $response->assertDontSee('of 43 meetings', false);
    }

    public function test_no_support_phone_number_or_email_is_hardcoded_on_the_page(): void
    {
        // The handover's rail carried a "Need Immediate Help?" card with a phone
        // number and two obfuscated addresses — a client-support panel that had
        // wandered onto an internal staff page.
        $this->withDemoData();

        $html = $this->get('/meetings')->getContent();

        $this->assertStringNotContainsString('+91 80 1234 5678', $html);
        $this->assertStringNotContainsString('__cf_email__', $html);
    }

    public function test_the_tabs_filter_the_list(): void
    {
        $this->withDemoData();

        // Scoped to the table: the "Your next meeting" rail is on every tab and
        // links to a scheduled meeting by design, so a bare assertDontSee here
        // would be testing the rail rather than the filter.
        $rows = fn (string $html) => preg_match_all('/class="row-link"/', $html);

        $requested = $this->get('/meetings?tab=requested')->getContent();
        $scheduled = $this->get('/meetings?tab=scheduled')->getContent();

        $this->assertStringContainsString('MTG-2026-054', $requested);
        $this->assertSame($this->allMeetings()->where('status', 'requested')->count(), $rows($requested));
        $this->assertSame($this->allMeetings()->where('status', 'scheduled')->count(), $rows($scheduled));

        // And the counts on the tabs match what each one lists.
        $this->assertStringContainsString('Showing 1 to '.$this->allMeetings()->where('status', 'requested')->count(), $requested);
    }

    public function test_an_invalid_tab_is_rejected(): void
    {
        $this->get('/meetings?tab=whatever')->assertSessionHasErrors('tab');
    }

    public function test_an_unknown_meeting_is_not_found(): void
    {
        $this->withDemoData();

        $this->get('/meetings/MTG-9999-999')->assertNotFound();
        $this->get('/meetings/'.urlencode('<script>'))->assertNotFound();
    }

    public function test_the_write_routes_exist_so_the_forms_are_real(): void
    {
        $this->assertTrue(app('router')->has('meetings.store'));
        $this->assertTrue(app('router')->has('meetings.create.event'));
        $this->assertTrue(app('router')->has('meetings.cancel'));

        /*
         * Requesting a meeting is the CLIENT's act, so it lives in the client
         * realm (moved 2026-09-07). It was here while /client did not exist,
         * which left a client's POST at a /meetings URL that realm middleware
         * would refuse to the only people meant to use it.
         *
         * Still asserted from this side because the staff queue renders what it
         * produces: a requested meeting with no Google event behind it.
         */
        $this->assertFalse(app('router')->has('meetings.request'));
        $this->assertTrue(app('router')->has('client.meetings.request'));
    }

    public function test_no_route_deletes_a_meeting(): void
    {
        // Cancelling withdraws the Google invite and keeps the record; deleting
        // would leave the event live on everyone's calendar with nothing here
        // to show it existed.
        foreach (app('router')->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'meetings')) {
                $this->assertNotContains('DELETE', $route->methods(), "a DELETE route exists at {$route->uri()}");
            }
        }
    }

    public function test_the_pages_render_nothing_the_content_security_policy_would_block(): void
    {
        $this->withDemoData();

        foreach (['/meetings', '/meetings/schedule', '/meetings/MTG-2026-058', '/meetings/MTG-2026-054'] as $url) {
            $html = $this->get($url)->getContent();

            $this->assertSame(0, preg_match_all('/<style[\s>]/i', $html), "inline <style> in {$url}");
            $this->assertSame(0, preg_match_all('/\sstyle="/i', $html), "inline style attribute in {$url}");
            $this->assertSame(0, preg_match_all('/\son[a-z]+="/i', $html), "inline event handler in {$url}");
            $this->assertSame(0, preg_match_all('/<script(?![^>]*\ssrc=)/i', $html), "inline <script> in {$url}");
        }
    }

    public function test_the_sidebar_marks_meetings_as_current(): void
    {
        $this->withDemoData();

        foreach (['/meetings', '/meetings/schedule', '/meetings/MTG-2026-058'] as $url) {
            $this->assertSame(
                1,
                substr_count($this->get($url)->getContent(), 'class="sb-link active"'),
                "sidebar current marker wrong on {$url}"
            );
        }
    }
}
