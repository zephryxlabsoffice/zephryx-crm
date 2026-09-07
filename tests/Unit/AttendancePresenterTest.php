<?php

namespace Tests\Unit;

use App\Support\AttendancePresenter as P;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendancePresenterTest extends TestCase
{
    public function test_hours_are_read_as_hours_and_minutes(): void
    {
        $this->assertSame('8h 12m', P::hours(492));
        $this->assertSame('0h 07m', P::hours(7));
        $this->assertSame('12h 00m', P::hours(720));
    }

    public function test_no_recorded_time_is_a_dash_and_not_a_zero(): void
    {
        // A day with nothing recorded is not a day of zero hours, and drawing it
        // as "0h 00m" invites somebody to read it as one.
        $this->assertSame('—', P::hours(null));
        $this->assertSame('—', P::time('2026-09-03', null));
    }

    public function test_there_are_three_statuses_and_late_is_not_one_of_them(): void
    {
        // Removed 2026-09-03, concept and all.
        $this->assertNotContains('late', P::states());
        $this->assertNotContains('late', P::filterableStates());

        foreach (P::states() as $state) {
            $this->assertStringNotContainsStringIgnoringCase('late', P::state($state)['label']);
        }
    }

    public function test_how_long_a_day_has_been_open_is_said_in_hours(): void
    {
        // "Open 7 hr", not "Open 7h 12m" — the point is that it has been open a
        // long time, and minutes make it read as a measurement of work.
        $this->assertSame('Open 40 min', P::openFor(40));
        $this->assertSame('Open 7 hr', P::openFor(432));
        $this->assertSame('', P::openFor(null));
    }

    public function test_every_state_has_a_tone_a_label_and_a_dot(): void
    {
        foreach (P::states() as $state) {
            $meta = P::state($state);

            $this->assertNotSame('', $meta['label']);
            $this->assertStringStartsWith('pill-', $meta['tone']);
            $this->assertStringStartsWith('att-dot-', $meta['dot']);
        }
    }

    public function test_absent_and_weekly_off_are_not_the_same_colour(): void
    {
        // Nor is "not marked". The handover drew all three red, which makes a
        // calendar scarlet every Sunday and teaches people red means nothing.
        $tones = [
            P::state(P::ABSENT)['tone'],
            P::state(P::WEEK_OFF)['tone'],
            P::state(P::NOT_MARKED)['tone'],
        ];

        $this->assertSame(P::state(P::WEEK_OFF)['tone'], P::state(P::NOT_MARKED)['tone']);
        $this->assertNotContains(P::state(P::ABSENT)['tone'], [$tones[1]]);
    }

    public function test_an_unknown_state_still_renders(): void
    {
        // A record whose state has been renamed is a data problem, and hiding it
        // makes it an invisible one.
        $meta = P::state('some_new_thing');

        $this->assertSame('Some new thing', $meta['label']);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE CALENDAR
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_month_is_whole_weeks_of_seven_days(): void
    {
        $weeks = P::calendar(Carbon::parse('2026-09-01'), collect());

        foreach ($weeks as $week) {
            $this->assertCount(7, $week);
        }

        // Leading and trailing cells belong to the neighbouring months and are
        // drawn quiet rather than left blank — a blank cell reads as a missing
        // day.
        $this->assertFalse($weeks[0][0]['in_month']);
        $this->assertSame('Sunday', Carbon::parse($weeks[0][0]['date'])->format('l'));
    }

    public function test_every_day_of_the_month_appears_exactly_once(): void
    {
        $month = Carbon::parse('2026-09-01');
        $dates = collect(P::calendar($month, collect()))
            ->flatten(1)
            ->where('in_month', true)
            ->pluck('date');

        $this->assertSame($month->daysInMonth, $dates->count());
        $this->assertSame($dates->count(), $dates->unique()->count());
    }

    public function test_a_record_is_matched_to_its_day(): void
    {
        $date = Carbon::today()->subDays(2)->toDateString();

        $cells = collect(P::calendar(Carbon::today()->startOfMonth(), collect([
            ['id' => 'ATT-'.$date.'-EMP002', 'date' => $date, 'check_in' => '09:10', 'check_out' => '18:20', 'rejected_at' => null],
        ])))->flatten(1)->keyBy('date');

        $this->assertNotNull($cells[$date]['record']);
        $this->assertSame(P::PRESENT, $cells[$date]['state']);
    }

    public function test_the_month_parameter_is_clamped_rather_than_trusted(): void
    {
        // The arrows must not walk somebody into 1970 or into next November.
        $this->assertSame(
            Carbon::today()->startOfMonth()->format('Y-m'),
            P::month(Carbon::today()->addYear()->format('Y-m'))->format('Y-m'),
        );

        $this->assertSame(
            Carbon::today()->startOfMonth()->subYears(5)->format('Y-m'),
            P::month('1999-01')->format('Y-m'),
        );
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE DONUT
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_breakdown_drops_states_that_did_not_happen(): void
    {
        // A zero-width arc is invisible, and "Rejected 0 (0%)" in a legend is
        // noise.
        $segments = P::breakdown([P::PRESENT => 8, P::HALF_DAY => 2, P::ABSENT => 0], 10);

        $this->assertCount(2, $segments);
        $this->assertSame([P::PRESENT, P::HALF_DAY], array_column($segments, 'state'));
    }

    public function test_a_breakdown_of_nothing_is_empty_rather_than_a_division_by_zero(): void
    {
        $this->assertSame([], P::breakdown([P::PRESENT => 0], 0));
    }

    public function test_the_shares_add_up(): void
    {
        $segments = P::breakdown([P::PRESENT => 6, P::HALF_DAY => 3, P::ABSENT => 1], 10);

        $this->assertEqualsWithDelta(100.0, array_sum(array_column($segments, 'share')), 0.2);
    }
}
