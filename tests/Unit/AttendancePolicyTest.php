<?php

namespace Tests\Unit;

use App\Support\AttendancePolicy;
use App\Support\AttendancePresenter as P;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendancePolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pinned, so these assertions test the arithmetic rather than whatever
        // the company policy happens to say this week.
        config([
            'attendance.work_start' => '09:30',
            'attendance.work_end' => '18:30',
            'attendance.half_day_hours' => 4.0,
            'attendance.auto_reject_after_hours' => 10,
            'attendance.week_off' => [0],
        ]);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE CALENDAR
       ══════════════════════════════════════════════════════════════════════ */

    public function test_sunday_is_the_only_weekly_off(): void
    {
        // This company works Saturdays. Assuming otherwise marks a sixth of the
        // month absent for everybody.
        $this->assertFalse(AttendancePolicy::isWorkingDay('2026-09-06'));  // Sunday
        $this->assertTrue(AttendancePolicy::isWorkingDay('2026-09-05'));   // Saturday
        $this->assertTrue(AttendancePolicy::isWorkingDay('2026-09-07'));   // Monday
    }

    public function test_the_weekly_off_list_is_configuration_and_not_an_assumption(): void
    {
        config(['attendance.week_off' => [0, 6]]);

        $this->assertFalse(AttendancePolicy::isWorkingDay('2026-09-05'));
    }

    public function test_there_is_no_late_status(): void
    {
        // Removed 2026-09-03. Arriving at 09:47 produced a full day's work or it
        // did not, and that is already in the hours.
        $this->assertNotContains('late', P::states());
        $this->assertFalse(method_exists(AttendancePolicy::class, 'lateMinutes'));
        $this->assertFalse(method_exists(AttendancePolicy::class, 'graceMinutes'));
        $this->assertFalse(method_exists(P::class, 'lateness'));
    }

    public function test_only_three_statuses_can_come_out_of_a_working_day(): void
    {
        $date = '2026-09-02';

        $states = [
            AttendancePolicy::evaluate($date, ['date' => $date, 'check_in' => '09:00', 'check_out' => '18:30', 'rejected_at' => null])['state'],
            AttendancePolicy::evaluate($date, ['date' => $date, 'check_in' => '09:00', 'check_out' => '11:00', 'rejected_at' => null])['state'],
            AttendancePolicy::evaluate($date, null)['state'],
        ];

        $this->assertSame([P::PRESENT, P::HALF_DAY, P::ABSENT], $states);
    }

    public function test_an_arrival_time_on_its_own_changes_nothing(): void
    {
        // Same hours, four hours apart. One logic: hours against one threshold.
        $date = '2026-09-02';

        $early = AttendancePolicy::evaluate($date, ['date' => $date, 'check_in' => '08:00', 'check_out' => '17:00', 'rejected_at' => null]);
        $later = AttendancePolicy::evaluate($date, ['date' => $date, 'check_in' => '12:00', 'check_out' => '21:00', 'rejected_at' => null]);

        $this->assertSame($early['state'], $later['state']);
        $this->assertSame($early['worked_minutes'], $later['worked_minutes']);
    }

    public function test_changing_the_threshold_corrects_history(): void
    {
        // Nothing stores a status, so the whole record re-derives. This is the
        // reason there is no `status` column.
        $record = ['date' => '2026-09-02', 'check_in' => '09:00', 'check_out' => '14:00', 'rejected_at' => null];

        $this->assertSame(P::PRESENT, AttendancePolicy::evaluate('2026-09-02', $record)['state']);

        config(['attendance.half_day_hours' => 6.0]);

        $this->assertSame(P::HALF_DAY, AttendancePolicy::evaluate('2026-09-02', $record)['state']);
    }

    /* ══════════════════════════════════════════════════════════════════════
       HOURS
       ══════════════════════════════════════════════════════════════════════ */

    public function test_hours_are_the_gap_between_the_two_times(): void
    {
        $this->assertSame(540, AttendancePolicy::workedMinutes('2026-09-03', '09:00', '18:00'));
        $this->assertSame(95, AttendancePolicy::workedMinutes('2026-09-03', '09:05', '10:40'));
    }

    public function test_a_corrupt_record_reports_zero_rather_than_a_negative(): void
    {
        // A check-out before its check-in is corrupt, not negative. It is left
        // visible rather than hidden.
        $this->assertSame(0, AttendancePolicy::workedMinutes('2026-09-03', '18:00', '09:00'));
    }

    public function test_no_check_in_means_no_hours_rather_than_zero_hours(): void
    {
        $this->assertNull(AttendancePolicy::workedMinutes('2026-09-03', null, null));
    }

    public function test_a_day_never_checked_out_of_has_no_hours_at_all(): void
    {
        // Measured to `now`, a day somebody forgot to close reads "51h 46m" —
        // a missing timestamp dressed up as a number, and one that would then be
        // counted as work.
        $yesterday = Carbon::today()->subDay()->toDateString();

        $this->assertNull(AttendancePolicy::workedMinutes($yesterday, '09:33', null));
        $this->assertNull(AttendancePolicy::evaluate($yesterday, [
            'date' => $yesterday, 'check_in' => '09:33', 'check_out' => null, 'rejected_at' => null,
        ])['worked_minutes']);
    }

    public function test_a_day_still_inside_the_window_is_measured_to_now(): void
    {
        // Somebody watching their own day wants what they have worked so far,
        // not a dash.
        $today = Carbon::today()->toDateString();
        $minutes = AttendancePolicy::workedMinutes($today, Carbon::now()->subMinutes(90)->format('H:i'), null);

        $this->assertNotNull($minutes);
        $this->assertEqualsWithDelta(90, $minutes, 2);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE TEN-HOUR RULE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_day_left_open_past_the_window_is_rejected(): void
    {
        $today = Carbon::today()->toDateString();

        $record = [
            'date' => $today,
            'check_in' => Carbon::now()->subHours(11)->format('H:i'),
            'check_out' => null,
            'rejected_at' => null,
        ];

        if (Carbon::now()->subHours(11)->isYesterday()) {
            $this->markTestSkipped('eleven hours ago was yesterday');
        }

        $evaluated = AttendancePolicy::evaluate($today, $record);

        $this->assertTrue($evaluated['auto_rejected']);
        $this->assertTrue($evaluated['rejected']);
        $this->assertSame(P::REJECTED, $evaluated['state']);
    }

    public function test_a_day_still_inside_the_window_is_not_rejected(): void
    {
        // Nine hours at a desk is a long day, not a fault. The window has to be
        // clear of a genuinely long one or it punishes the wrong thing.
        $today = Carbon::today()->toDateString();
        $checkIn = Carbon::now()->subHours(9);

        if ($checkIn->isYesterday()) {
            $this->markTestSkipped('nine hours ago was yesterday');
        }

        $evaluated = AttendancePolicy::evaluate($today, [
            'date' => $today, 'check_in' => $checkIn->format('H:i'), 'check_out' => null, 'rejected_at' => null,
        ]);

        $this->assertFalse($evaluated['auto_rejected']);
        $this->assertSame(P::PRESENT, $evaluated['state']);
    }

    public function test_the_window_is_configuration(): void
    {
        $yesterday = Carbon::today()->subDay()->toDateString();
        $record = ['date' => $yesterday, 'check_in' => '09:00', 'check_out' => null, 'rejected_at' => null];

        $this->assertTrue(AttendancePolicy::autoRejected($record));

        // Absurd, but it proves the rule reads the policy rather than a
        // hardcoded ten.
        config(['attendance.auto_reject_after_hours' => 24 * 365]);

        $this->assertFalse(AttendancePolicy::autoRejected($record));
    }

    public function test_a_closed_day_is_never_auto_rejected_however_long_it_was(): void
    {
        // Fourteen hours, checked out properly. The rule is about a MISSING
        // check-out, not about long days.
        $yesterday = Carbon::today()->subDay()->toDateString();

        $this->assertFalse(AttendancePolicy::autoRejected([
            'date' => $yesterday, 'check_in' => '08:00', 'check_out' => '22:00', 'rejected_at' => null,
        ]));

        $this->assertSame(P::PRESENT, AttendancePolicy::evaluate($yesterday, [
            'date' => $yesterday, 'check_in' => '08:00', 'check_out' => '22:00', 'rejected_at' => null,
        ])['state']);
    }

    public function test_the_auto_rejection_is_derived_and_needs_no_stored_flag(): void
    {
        // No nightly job writes this. The record simply reads as rejected once
        // the clock passes the window, so it is right even if nothing ran.
        $yesterday = Carbon::today()->subDay()->toDateString();

        $evaluated = AttendancePolicy::evaluate($yesterday, [
            'date' => $yesterday, 'check_in' => '09:00', 'check_out' => null, 'rejected_at' => null,
        ]);

        $this->assertTrue($evaluated['auto_rejected']);
        // Nothing was stamped on the record to make that true.
        $this->assertSame(P::REJECTED, $evaluated['state']);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE STATE OF A DAY
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_short_day_is_a_half_day_however_early_it_started(): void
    {
        $state = AttendancePolicy::evaluate('2026-09-02', [
            'date' => '2026-09-02', 'check_in' => '09:00', 'check_out' => '12:15', 'rejected_at' => null,
        ])['state'];

        $this->assertSame(P::HALF_DAY, $state);
    }

    public function test_the_threshold_is_inclusive_at_the_bottom_of_a_full_day(): void
    {
        // Exactly four hours is present, not half. An off-by-one here is the
        // difference between somebody's day counting and not.
        $date = '2026-09-02';

        $this->assertSame(P::PRESENT, AttendancePolicy::evaluate($date, [
            'date' => $date, 'check_in' => '09:00', 'check_out' => '13:00', 'rejected_at' => null,
        ])['state']);

        $this->assertSame(P::HALF_DAY, AttendancePolicy::evaluate($date, [
            'date' => $date, 'check_in' => '09:00', 'check_out' => '12:59', 'rejected_at' => null,
        ])['state']);
    }

    public function test_a_rejected_record_is_rejected_whatever_its_times_say(): void
    {
        $state = AttendancePolicy::evaluate('2026-09-02', [
            'date' => '2026-09-02', 'check_in' => '09:00', 'check_out' => '18:30', 'rejected_at' => '2026-09-02 17:40',
        ])['state'];

        $this->assertSame(P::REJECTED, $state);
        $this->assertNotSame(P::PRESENT, $state);
    }

    public function test_somebody_who_works_a_weekly_off_is_still_shown_as_present(): void
    {
        // Erasing work that was done because nobody was expected in is not a
        // policy, it is a bug.
        $state = AttendancePolicy::evaluate('2026-09-06', [
            'date' => '2026-09-06', 'check_in' => '09:10', 'check_out' => '18:20', 'rejected_at' => null,
        ])['state'];

        $this->assertSame(P::PRESENT, $state);
    }

    public function test_a_holiday_comes_from_the_announcement_board(): void
    {
        // Not from this module's configuration. HR announces a holiday once and
        // it closes the office; a second list here is the one that goes stale.
        $this->app->detectEnvironment(fn () => 'local');
        config(['app.debug' => true]);

        $next = \App\Support\Holidays::next();

        $this->assertNotNull($next, 'no announced holiday to prove the point');
        $this->assertSame($next['name'], AttendancePolicy::holidayOn($next['date']));
        $this->assertFalse(AttendancePolicy::isWorkingDay($next['date']));

        // And there is no holiday list in the attendance config to disagree
        // with it.
        $this->assertNull(config('attendance.holidays'));
    }

    public function test_approved_leave_outranks_absence(): void
    {
        // A past working day: today is never an absence, and neither is a day
        // that has not arrived.
        $past = '2026-08-26';

        $this->assertSame(P::LEAVE, AttendancePolicy::evaluate($past, null, true)['state']);
        $this->assertSame(P::ABSENT, AttendancePolicy::evaluate($past, null, false)['state']);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE FORGOTTEN CHECK-OUT
       ══════════════════════════════════════════════════════════════════════ */

    public function test_an_open_record_on_a_past_day_is_a_forgotten_check_out(): void
    {
        $yesterday = Carbon::today()->subDay()->toDateString();

        $this->assertTrue(AttendancePolicy::missingCheckOut([
            'date' => $yesterday, 'check_in' => '09:10', 'check_out' => null, 'rejected_at' => null,
        ]));
    }

    public function test_an_open_record_inside_the_window_is_somebody_at_their_desk(): void
    {
        $checkIn = Carbon::now()->subMinutes(45);

        if ($checkIn->isYesterday()) {
            $this->markTestSkipped('45 minutes ago was yesterday');
        }

        $this->assertFalse(AttendancePolicy::missingCheckOut([
            'date' => Carbon::today()->toDateString(),
            'check_in' => $checkIn->format('H:i'),
            'check_out' => null,
            'rejected_at' => null,
        ]));
    }

    /* ══════════════════════════════════════════════════════════════════════
       SUMS OVER A MONTH
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_month_is_only_counted_as_far_as_today(): void
    {
        // Counting a whole month's working days on the 3rd puts everybody's
        // attendance in single digits until the 28th.
        $summary = AttendancePolicy::monthSummary(Carbon::today()->startOfMonth(), []);

        $this->assertSame(Carbon::today()->day, (int) $summary['days_counted']);
        $this->assertLessThanOrEqual(Carbon::today()->day, (int) $summary['working_days']);
    }

    public function test_absences_are_counted_even_though_they_have_no_record(): void
    {
        // The whole reason the summary walks days rather than records.
        $summary = AttendancePolicy::monthSummary(Carbon::today()->startOfMonth(), []);

        $this->assertSame((int) $summary['working_days'], (int) $summary[P::ABSENT] + (int) $summary[P::NOT_MARKED]);
        $this->assertSame(0, (int) $summary['attended']);
    }

    public function test_a_rejected_day_counts_as_rejected_and_not_as_attended(): void
    {
        $month = Carbon::today()->startOfMonth();
        $day = Carbon::today()->subDay();

        while (! AttendancePolicy::isWorkingDay($day) || ! $day->isSameMonth($month)) {
            $day->subDay();

            if ($day->lessThan($month)) {
                $this->markTestSkipped('no working day earlier in this month');
            }
        }

        $summary = AttendancePolicy::monthSummary($month, [[
            'date' => $day->toDateString(), 'check_in' => '09:00', 'check_out' => '18:00',
            'rejected_at' => '2026-01-01 12:00',
        ]]);

        $this->assertSame(1, (int) $summary[P::REJECTED]);
        $this->assertSame(0, (int) $summary['attended']);
    }

    public function test_there_is_no_average_working_day(): void
    {
        // Removed 2026-09-03. An average over a handful of days swings on one
        // short afternoon and is the number people start managing to.
        $summary = AttendancePolicy::monthSummary(Carbon::today()->startOfMonth(), []);

        $this->assertArrayNotHasKey('average_minutes', $summary);
        $this->assertArrayNotHasKey('attendance_rate', $summary);
    }

    public function test_a_half_day_counts_as_a_day_attended(): void
    {
        $month = Carbon::today()->startOfMonth();
        $day = Carbon::today()->subDay();

        while (! AttendancePolicy::isWorkingDay($day) || ! $day->isSameMonth($month)) {
            $day->subDay();

            if ($day->lessThan($month)) {
                $this->markTestSkipped('no working day earlier in this month');
            }
        }

        $summary = AttendancePolicy::monthSummary($month, [[
            'date' => $day->toDateString(), 'check_in' => '09:00', 'check_out' => '11:00', 'rejected_at' => null,
        ]]);

        $this->assertSame(1, (int) $summary[P::HALF_DAY]);
        $this->assertSame(1, (int) $summary['attended']);
    }
}
