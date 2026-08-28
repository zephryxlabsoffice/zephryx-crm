<?php

namespace Tests\Unit;

use App\Support\MeetingPresenter as P;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MeetingPresenterTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function meeting(array $overrides = []): array
    {
        return array_merge([
            // UTC, as everything stored is.
            'starts_at' => Carbon::now('UTC')->addDay()->toDateTimeString(),
            'ends_at' => Carbon::now('UTC')->addDay()->addMinutes(30)->toDateTimeString(),
            'event_id' => 'gcal_abc123',
            'cancelled_at' => null,
        ], $overrides);
    }

    /* ────────────  status is derived  ──────────── */

    public function test_a_meeting_with_no_google_event_is_only_requested(): void
    {
        // Nothing was sent, whatever the clock says.
        $this->assertSame(P::REQUESTED, P::statusOf($this->meeting(['event_id' => null])));

        $this->assertSame(P::REQUESTED, P::statusOf($this->meeting([
            'event_id' => null,
            'starts_at' => Carbon::now('UTC')->subDays(3)->toDateTimeString(),
            'ends_at' => Carbon::now('UTC')->subDays(3)->addHour()->toDateTimeString(),
        ])));
    }

    public function test_a_created_future_meeting_is_scheduled(): void
    {
        $this->assertSame(P::SCHEDULED, P::statusOf($this->meeting()));
    }

    public function test_a_past_meeting_reads_as_ended_not_completed(): void
    {
        // Nothing here can see whether a meeting took place. "Completed"
        // asserts something we cannot know; "Ended" is a fact about the clock.
        $past = $this->meeting([
            'starts_at' => Carbon::now('UTC')->subHours(3)->toDateTimeString(),
            'ends_at' => Carbon::now('UTC')->subHours(2)->toDateTimeString(),
        ]);

        $this->assertSame(P::ENDED, P::statusOf($past));
        $this->assertSame('Ended', P::status(P::ENDED)['label']);
        $this->assertNotContains('completed', P::statusOptions());
    }

    public function test_cancelled_outranks_everything(): void
    {
        $this->assertSame(P::CANCELLED, P::statusOf($this->meeting([
            'cancelled_at' => Carbon::now('UTC')->subDay()->toDateTimeString(),
        ])));

        // Including a cancelled meeting whose time has since passed.
        $this->assertSame(P::CANCELLED, P::statusOf($this->meeting([
            'cancelled_at' => Carbon::now('UTC')->subDays(5)->toDateTimeString(),
            'starts_at' => Carbon::now('UTC')->subDays(2)->toDateTimeString(),
            'ends_at' => Carbon::now('UTC')->subDays(2)->addHour()->toDateTimeString(),
        ])));
    }

    public function test_every_status_has_words_a_tone_and_a_meaning(): void
    {
        foreach (P::statusOptions() as $status) {
            $rendered = P::status($status);

            $this->assertNotSame('', $rendered['label']);
            $this->assertStringStartsWith('pill-', $rendered['tone']);
            $this->assertNotSame('', $rendered['meaning'], "{$status} has no plain-English meaning");
        }
    }

    /* ────────────  RSVP is read, not written  ──────────── */

    public function test_an_unknown_or_missing_response_reads_as_no_reply(): void
    {
        // Assuming somebody is coming is the error that costs a meeting.
        foreach ([null, '', 'needsAction', 'maybe-ish'] as $response) {
            $this->assertSame('No reply yet', P::response($response)['label']);
        }
    }

    public function test_the_responses_read_as_words(): void
    {
        $this->assertSame('Coming', P::response(P::ACCEPTED)['label']);
        $this->assertSame('Not coming', P::response(P::DECLINED)['label']);
        $this->assertSame('Maybe', P::response(P::TENTATIVE)['label']);
    }

    /* ────────────  times  ──────────── */

    public function test_a_stored_utc_time_is_shown_in_the_display_zone(): void
    {
        config(['meetings.display_timezone' => 'Asia/Kolkata']);

        // 09:30 UTC is 15:00 IST.
        $meeting = $this->meeting([
            'starts_at' => '2026-09-02 09:30:00',
            'ends_at' => '2026-09-02 10:00:00',
        ]);

        $this->assertSame('02 Sep 2026', P::date($meeting['starts_at']));
        $this->assertSame('3:00 PM', P::time($meeting['starts_at']));
        $this->assertSame('3:00 – 3:30 PM', P::timeRange($meeting));
    }

    public function test_a_time_crossing_midnight_in_utc_shows_the_local_date(): void
    {
        // 20:00 UTC on the 1st is 01:30 IST on the 2nd. Rendering the stored
        // date would put the meeting on the wrong day.
        config(['meetings.display_timezone' => 'Asia/Kolkata']);

        $this->assertSame('02 Sep 2026', P::date('2026-09-01 20:00:00'));
    }

    public function test_the_span_states_the_meridiem_once_when_both_ends_share_it(): void
    {
        config(['meetings.display_timezone' => 'Asia/Kolkata']);

        // 05:00–06:30 UTC = 10:30 AM – 12:00 PM IST, which straddles noon.
        $straddling = $this->meeting([
            'starts_at' => '2026-09-02 05:00:00',
            'ends_at' => '2026-09-02 06:30:00',
        ]);

        $this->assertSame('10:30 AM – 12:00 PM', P::timeRange($straddling));
    }

    public function test_when_names_the_zone(): void
    {
        // A client reading this in another country needs to know which four
        // o'clock is meant.
        config(['meetings.display_timezone' => 'Asia/Kolkata']);

        $when = P::when($this->meeting([
            'starts_at' => '2026-09-02 09:30:00',
            'ends_at' => '2026-09-02 10:00:00',
        ]));

        $this->assertStringContainsString('Wed, 02 Sep 2026', $when);
        $this->assertStringContainsString('IST', $when);
    }

    public function test_duration_reads_in_minutes_then_hours(): void
    {
        $at = fn (int $mins) => $this->meeting([
            'starts_at' => '2026-09-02 09:00:00',
            'ends_at' => Carbon::parse('2026-09-02 09:00:00')->addMinutes($mins)->toDateTimeString(),
        ]);

        $this->assertSame('30 min', P::duration($at(30)));
        $this->assertSame('1 hr', P::duration($at(60)));
        $this->assertSame('1 hr 30 min', P::duration($at(90)));
        $this->assertSame('2 hr', P::duration($at(120)));
    }

    /* ────────────  urgency and joining  ──────────── */

    public function test_a_meeting_in_progress_says_so(): void
    {
        $running = $this->meeting([
            'starts_at' => Carbon::now('UTC')->subMinutes(10)->toDateTimeString(),
            'ends_at' => Carbon::now('UTC')->addMinutes(20)->toDateTimeString(),
        ]);

        $this->assertSame('Happening now', P::timing($running)['label']);
        $this->assertSame('is-overdue', P::timing($running)['tone']);
    }

    public function test_timing_counts_down_in_minutes_then_hours_then_days(): void
    {
        $in = fn (int $mins) => $this->meeting([
            'starts_at' => Carbon::now('UTC')->addMinutes($mins)->toDateTimeString(),
            'ends_at' => Carbon::now('UTC')->addMinutes($mins + 30)->toDateTimeString(),
        ]);

        $this->assertStringContainsString('min', P::timing($in(20))['label']);
        $this->assertStringContainsString('hr', P::timing($in(300))['label']);
        $this->assertStringContainsString('days', P::timing($in(60 * 24 * 4))['label']);
    }

    public function test_joining_opens_shortly_before_the_start(): void
    {
        // Not a lock — a Meet link works whenever. It is about not putting a
        // live "Join" beside a meeting three weeks out.
        $soon = $this->meeting([
            'starts_at' => Carbon::now('UTC')->addMinutes(5)->toDateTimeString(),
            'ends_at' => Carbon::now('UTC')->addMinutes(35)->toDateTimeString(),
        ]);
        $later = $this->meeting([
            'starts_at' => Carbon::now('UTC')->addDays(3)->toDateTimeString(),
            'ends_at' => Carbon::now('UTC')->addDays(3)->addMinutes(30)->toDateTimeString(),
        ]);

        $this->assertTrue(P::isJoinable($soon));
        $this->assertFalse(P::isJoinable($later));
    }

    public function test_nothing_uncreated_or_cancelled_is_ever_joinable(): void
    {
        $now = fn (array $extra) => $this->meeting(array_merge([
            'starts_at' => Carbon::now('UTC')->toDateTimeString(),
            'ends_at' => Carbon::now('UTC')->addMinutes(30)->toDateTimeString(),
        ], $extra));

        $this->assertFalse(P::isJoinable($now(['event_id' => null])));
        $this->assertFalse(P::isJoinable($now(['cancelled_at' => Carbon::now('UTC')->toDateTimeString()])));
    }
}
