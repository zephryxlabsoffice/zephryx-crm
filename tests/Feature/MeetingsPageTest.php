<?php

namespace Tests\Feature;

use App\Support\Demo\DemoMeetings;
use App\Support\Meetings\GoogleMeetProvider;
use App\Support\Meetings\MeetingProvider;
use App\Support\MeetingPresenter as P;
use RuntimeException;
use Tests\TestCase;

class MeetingsPageTest extends TestCase
{
    protected function withDemoData(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config(['app.debug' => true]);
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

        $viewer = DemoMeetings::VIEWER;
        $notMine = DemoMeetings::all()
            ->first(fn (array $m) => $m['join_url'] !== null && ! DemoMeetings::isAttendee($m, $viewer));

        $this->assertNotNull($notMine, 'no sample meeting the viewer is absent from');

        $html = $this->get('/meetings/'.$notMine['id'])->getContent();

        $this->assertStringNotContainsString($notMine['join_url'], $html);
        $this->assertStringNotContainsString('meet.google.com', $html);
        // And it says why, rather than looking broken.
        $this->assertStringContainsString('not on this invite', $html);
    }

    public function test_the_list_carries_no_link_for_a_meeting_the_viewer_is_absent_from(): void
    {
        $this->withDemoData();

        $viewer = DemoMeetings::VIEWER;
        $html = $this->get('/meetings?tab=all')->getContent();

        foreach (DemoMeetings::all() as $meeting) {
            if ($meeting['join_url'] === null || DemoMeetings::isAttendee($meeting, $viewer)) {
                continue;
            }

            $this->assertStringNotContainsString($meeting['join_url'], $html, "{$meeting['id']} leaked its link into the list");
        }
    }

    public function test_an_attendee_does_get_the_link(): void
    {
        // The counterpart: withholding must be about who is asking, not about
        // links being switched off everywhere.
        $this->withDemoData();

        $mine = DemoMeetings::all()->first(
            fn (array $m) => $m['join_url'] !== null && DemoMeetings::isAttendee($m, DemoMeetings::VIEWER)
        );

        $this->assertNotNull($mine);
        $this->get('/meetings/'.$mine['id'])->assertSee($mine['join_url'], false);
    }

    public function test_the_next_meeting_card_only_shows_one_the_viewer_is_on(): void
    {
        // A card headed "Your next meeting" showing one somebody is not invited
        // to is worse than showing nothing — they will act on it.
        $this->withDemoData();

        $next = DemoMeetings::nextFor(DemoMeetings::VIEWER);

        if ($next !== null) {
            $this->assertTrue(DemoMeetings::isAttendee($next, DemoMeetings::VIEWER));
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

        foreach (DemoMeetings::all() as $meeting) {
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

        foreach (DemoMeetings::all() as $meeting) {
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

        foreach (DemoMeetings::all() as $meeting) {
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

    public function test_the_demo_source_is_inert_outside_local_debug(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.debug' => false]);

        $this->assertFalse(DemoMeetings::enabled());
        $this->assertTrue(DemoMeetings::all()->isEmpty());
        $this->assertNull(DemoMeetings::nextFor('EMP002'));
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
        $this->assertSame(DemoMeetings::requested()->count(), $rows($requested));
        $this->assertSame(DemoMeetings::upcoming()->count(), $rows($scheduled));

        // And the counts on the tabs match what each one lists.
        $this->assertStringContainsString('Showing 1 to '.DemoMeetings::requested()->count(), $requested);
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
        $this->assertTrue(app('router')->has('meetings.request'));
        $this->assertTrue(app('router')->has('meetings.create.event'));
        $this->assertTrue(app('router')->has('meetings.cancel'));
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
