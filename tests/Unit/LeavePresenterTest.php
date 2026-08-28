<?php

namespace Tests\Unit;

use App\Support\LeavePresenter as P;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LeavePresenterTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function request(array $overrides = []): array
    {
        return array_merge([
            'from' => Carbon::today()->addDays(5)->toDateString(),
            'to' => Carbon::today()->addDays(5)->toDateString(),
            'days' => 1,
            'status' => P::PENDING,
        ], $overrides);
    }

    /* ────────────  rejected and withdrawn are different things  ──────────── */

    public function test_rejected_and_withdrawn_do_not_share_a_colour(): void
    {
        // The handover drew both red. One was done to the person; the other
        // they did themselves, and a withdrawal should not look like a refusal.
        $this->assertNotSame(P::status(P::REJECTED)['tone'], P::status(P::CANCELLED)['tone']);
    }

    public function test_the_withdrawn_state_is_never_shown_as_the_word_cancelled(): void
    {
        // One vocabulary, or the tab and the pill disagree.
        $this->assertSame('Withdrawn', P::status(P::CANCELLED)['label']);
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

    public function test_an_unknown_status_is_shown_rather_than_swallowed(): void
    {
        $this->assertSame('Escalated', P::status('escalated')['label']);
    }

    /* ────────────  who may do what  ──────────── */

    public function test_only_a_pending_request_can_be_decided(): void
    {
        // Deciding a decided request would silently overwrite somebody else's
        // decision.
        $this->assertTrue(P::isDecidable($this->request()));

        foreach ([P::APPROVED, P::REJECTED, P::CANCELLED] as $status) {
            $this->assertFalse(P::isDecidable($this->request(['status' => $status])));
        }
    }

    public function test_leave_can_be_withdrawn_until_it_starts(): void
    {
        // Approved-but-not-started too: plans change, and the alternative is a
        // balance spent on days nobody took.
        $this->assertTrue(P::isCancellable($this->request()));
        $this->assertTrue(P::isCancellable($this->request(['status' => P::APPROVED])));
    }

    public function test_leave_already_under_way_cannot_be_withdrawn_from_a_button(): void
    {
        $started = $this->request([
            'from' => Carbon::today()->subDay()->toDateString(),
            'to' => Carbon::today()->addDay()->toDateString(),
            'status' => P::APPROVED,
        ]);

        $this->assertFalse(P::isCancellable($started));
    }

    public function test_an_already_decided_request_cannot_be_withdrawn(): void
    {
        $this->assertFalse(P::isCancellable($this->request(['status' => P::REJECTED])));
        $this->assertFalse(P::isCancellable($this->request(['status' => P::CANCELLED])));
    }

    /* ────────────  dates  ──────────── */

    public function test_a_single_day_reads_as_one_date_with_its_weekday(): void
    {
        $range = P::range($this->request([
            'from' => '2026-09-07', 'to' => '2026-09-07',
        ]));

        $this->assertSame('07 Sep 2026 (Mon)', $range);
    }

    public function test_a_span_within_one_month_is_not_repeated(): void
    {
        $this->assertSame(
            '09–13 Sep 2026 (Wed–Sun)',
            P::range($this->request(['from' => '2026-09-09', 'to' => '2026-09-13']))
        );
    }

    public function test_a_span_across_months_states_both_dates_in_full(): void
    {
        $this->assertSame(
            '28 Sep 2026 – 02 Oct 2026 (Mon–Fri)',
            P::range($this->request(['from' => '2026-09-28', 'to' => '2026-10-02']))
        );
    }

    /* ────────────  duration  ──────────── */

    public function test_duration_reads_in_the_singular_for_one_day(): void
    {
        $this->assertSame('1 day', P::duration($this->request(['days' => 1])));
        $this->assertSame('2 days', P::duration($this->request(['days' => 2])));
    }

    public function test_half_days_are_shown_and_whole_days_are_not_padded(): void
    {
        $this->assertSame('0.5 days', P::duration($this->request(['days' => 0.5])));
        $this->assertSame('2.5 days', P::duration($this->request(['days' => 2.5])));
        $this->assertSame('5 days', P::duration($this->request(['days' => 5.0])));
    }

    /* ────────────  urgency  ──────────── */

    public function test_the_soonest_request_carries_the_loudest_tone(): void
    {
        // An undecided request for tomorrow is the one that costs somebody a
        // day off.
        $tomorrow = P::timing($this->request([
            'from' => Carbon::today()->addDay()->toDateString(),
            'to' => Carbon::today()->addDay()->toDateString(),
        ]));

        $this->assertSame('Starts tomorrow', $tomorrow['label']);
        $this->assertSame('is-overdue', $tomorrow['tone']);
    }

    public function test_a_request_within_a_week_is_worth_noticing(): void
    {
        $soon = P::timing($this->request([
            'from' => Carbon::today()->addDays(4)->toDateString(),
            'to' => Carbon::today()->addDays(4)->toDateString(),
        ]));

        $this->assertSame('is-soon', $soon['tone']);
    }

    public function test_a_distant_request_gets_no_tone(): void
    {
        $later = P::timing($this->request([
            'from' => Carbon::today()->addDays(30)->toDateString(),
            'to' => Carbon::today()->addDays(30)->toDateString(),
        ]));

        $this->assertSame('', $later['tone']);
    }

    public function test_leave_in_progress_and_leave_past_read_differently(): void
    {
        $running = P::timing($this->request([
            'from' => Carbon::today()->subDay()->toDateString(),
            'to' => Carbon::today()->addDay()->toDateString(),
        ]));
        $over = P::timing($this->request([
            'from' => Carbon::today()->subDays(10)->toDateString(),
            'to' => Carbon::today()->subDays(8)->toDateString(),
        ]));

        $this->assertSame('Under way', $running['label']);
        $this->assertSame('Already over', $over['label']);
        $this->assertSame('', $over['tone']);
    }

    /* ────────────  overlap  ──────────── */

    public function test_two_requests_sharing_any_day_overlap(): void
    {
        $a = ['from' => '2026-09-09', 'to' => '2026-09-13'];
        $b = ['from' => '2026-09-11', 'to' => '2026-09-15'];

        $this->assertTrue(P::overlaps($a, $b));
        $this->assertTrue(P::overlaps($b, $a));
    }

    public function test_a_shared_single_day_counts_as_an_overlap(): void
    {
        $this->assertTrue(P::overlaps(
            ['from' => '2026-09-13', 'to' => '2026-09-13'],
            ['from' => '2026-09-09', 'to' => '2026-09-13'],
        ));
    }

    public function test_one_request_wholly_inside_another_overlaps(): void
    {
        $this->assertTrue(P::overlaps(
            ['from' => '2026-09-10', 'to' => '2026-09-11'],
            ['from' => '2026-09-09', 'to' => '2026-09-13'],
        ));
    }

    public function test_adjacent_requests_do_not_overlap(): void
    {
        // One ending the day before another begins is two people covering for
        // each other, not a clash.
        $this->assertFalse(P::overlaps(
            ['from' => '2026-09-09', 'to' => '2026-09-13'],
            ['from' => '2026-09-14', 'to' => '2026-09-18'],
        ));
    }
}
