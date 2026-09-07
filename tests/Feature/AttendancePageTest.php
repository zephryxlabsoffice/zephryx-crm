<?php

namespace Tests\Feature;

use App\Support\AttendancePolicy;
use App\Support\AttendancePresenter as P;
use App\Support\Demo\DemoAttendance;
use App\Support\Holidays;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendancePageTest extends TestCase
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
    protected function withDemoData(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config(['app.debug' => true]);
    }

    /**
     * The viewer's most recent closed day — one they own, so the "nobody
     * corrects their own record" rule can be checked against it.
     *
     * Found rather than hardcoded: a fixed date drifts onto a Saturday within a
     * week of being written.
     *
     * @return array<string, mixed>
     */
    protected function viewerRecord(): array
    {
        $record = DemoAttendance::forEmployee(DemoAttendance::VIEWER)
            ->first(fn (array $r) => $r['check_out'] !== null && $r['rejected_at'] === null);

        $this->assertNotNull($record, 'no closed record for the viewer');

        return $record;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rejectedRecord(): array
    {
        $record = DemoAttendance::rejected()->first();

        $this->assertNotNull($record, 'no rejected sample record to prove the point');

        return $record;
    }

    /**
     * Somebody else's record, which HR could act on.
     *
     * @return array<string, mixed>
     */
    protected function someoneElsesRecord(): array
    {
        $row = DemoAttendance::forDate(Carbon::today())
            ->first(fn (array $r) => $r['id'] !== null
                && $r['employee'] !== DemoAttendance::VIEWER
                && ! $r['rejected']);

        $this->assertNotNull($row, 'nobody else has an open-to-rejection record today');

        return $row;
    }

    public function test_the_three_pages_render(): void
    {
        $this->withDemoData();

        $this->get('/attendance')->assertOk()->assertSee('Attendance', false);
        $this->get('/attendance/mine')->assertOk()->assertSee('My Attendance', false);
        $this->get('/attendance/'.$this->viewerRecord()['id'])->assertOk()->assertSee('Checked in', false);
    }

    public function test_mine_is_not_read_as_a_record_reference(): void
    {
        $this->withDemoData();

        $this->get('/attendance/mine')->assertOk()->assertSee('Attendance calendar', false);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THERE IS NO APPROVAL — THE DECISION THE MODULE IS BUILT ON
       ══════════════════════════════════════════════════════════════════════ */

    public function test_no_route_approves_attendance(): void
    {
        // The handover had a Mark Attendance screen with a Pending / Approved /
        // Rejected queue. A check-in is a fact, not a request: the record counts
        // the moment it is made. If an approve route ever appears here, the
        // module has quietly grown the queue back.
        $this->assertFalse(app('router')->has('attendance.approve'));

        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'attendance')) {
                continue;
            }

            $this->assertStringNotContainsString('approve', $route->uri(), "an approval route exists at {$route->uri()}");
        }
    }

    public function test_no_page_offers_to_approve_or_to_mark_somebody_present(): void
    {
        // Not a word search — "Approved leave is not counted here" is a sentence
        // this module needs to be able to say. What must not exist is a CONTROL:
        // a form that submits a decision, or a queue of days waiting for one.
        $this->withDemoData();

        foreach (['/attendance', '/attendance/mine', '/attendance/'.$this->rejectedRecord()['id']] as $url) {
            $html = $this->get($url)->getContent();

            $this->assertSame(
                0,
                preg_match_all('/<(?:button|a|form)[^>]*>[^<]*Approve/i', $html),
                "an approve control on {$url}",
            );
            $this->assertStringNotContainsStringIgnoringCase('Awaiting approval', $html, "an approval queue on {$url}");
            $this->assertStringNotContainsStringIgnoringCase('Bulk Mark Attendance', $html, "bulk marking on {$url}");
            $this->assertStringNotContainsStringIgnoringCase('Mark attendance for', $html, "marking on somebody's behalf on {$url}");
        }
    }

    public function test_checking_in_and_out_take_no_parameters(): void
    {
        // No employee, no date, no time. The person comes from the session and
        // the clock from the server, so there is nothing to tamper with.
        foreach (['attendance.check-in', 'attendance.check-out'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);

            $this->assertNotNull($route);
            $this->assertSame([], $route->parameterNames(), "{$name} takes a parameter");
        }
    }

    public function test_the_clock_form_posts_nothing_but_a_token(): void
    {
        // A form field is something anyone can type into. The check-in form must
        // carry a CSRF token and no other input at all.
        $this->withDemoData();

        $html = $this->get('/attendance/mine')->getContent();

        $this->assertMatchesRegularExpression(
            '#<form[^>]+action="[^"]*attendance/check-(in|out)"[^>]*>(?:(?!</form>).)*?</form>#s',
            $html,
        );

        preg_match('#<form[^>]+action="[^"]*attendance/check-(?:in|out)"[^>]*>(.*?)</form>#s', $html, $form);

        $inputs = [];
        preg_match_all('/<input[^>]*name="([^"]+)"/', $form[1] ?? '', $inputs);

        $this->assertSame(['_token'], $inputs[1]);
    }

    /* ══════════════════════════════════════════════════════════════════════
       REJECTION — A CORRECTION, NOT A GATE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_rejected_record_shows_the_reason_it_was_rejected(): void
    {
        $this->withDemoData();

        $record = $this->rejectedRecord();

        $this->assertNotNull($record['rejection_reason']);
        $this->get('/attendance/'.$record['id'])->assertSee($record['rejection_reason'], false);
    }

    public function test_a_persons_reason_is_shown_even_when_the_window_also_closed(): void
    {
        /*
         * A record can be rejected by HR AND left open past the ten-hour
         * window. Both are true; the human reason is the one that explains
         * anything, and it used to be the one that was hidden — the page
         * checked `auto_rejected` first and showed only "no check-out was
         * recorded".
         *
         * Requiring a reason and then never displaying it is worse than not
         * requiring one.
         */
        $this->withDemoData();

        $record = DemoAttendance::rejected()
            ->first(fn (array $r) => $r['check_out'] === null && $r['rejection_reason'] !== null);

        if ($record === null) {
            $this->markTestSkipped('no sample record that is both rejected and left open');
        }

        $this->get('/attendance/'.$record['id'])
            ->assertSee($record['rejection_reason'], false)
            // And the window is still mentioned, because it is also true.
            ->assertSee('would not have counted either way', false);
    }

    public function test_a_rejected_record_keeps_its_recorded_times(): void
    {
        // Rejecting is not editing. The times stay on screen, next to the reason
        // they are not being counted.
        $this->withDemoData();

        $record = $this->rejectedRecord();
        $html = $this->get('/attendance/'.$record['id'])->getContent();

        $this->assertStringContainsString(P::time($record['date'], $record['check_in']), $html);
    }

    public function test_a_rejected_day_does_not_count_as_attended(): void
    {
        $this->withDemoData();

        $record = DemoAttendance::find($this->rejectedRecord()['id']);

        $this->assertSame(P::REJECTED, $record['state']);
    }

    public function test_nobody_rejects_their_own_record(): void
    {
        // The same rule, for the same reason, as nobody approving their own
        // leave: a correction somebody can apply to themselves is not a control.
        $this->withDemoData();

        $id = $this->viewerRecord()['id'];
        $html = $this->get('/attendance/'.$id)->getContent();

        $this->assertStringNotContainsString('Reject this record', $html);
        $this->assertStringNotContainsString(route('attendance.reject', ['record' => $id]), $html);
    }

    public function test_somebody_elses_record_does_offer_a_rejection(): void
    {
        // The counterpart: the guard is about who is asking, not about rejection
        // being switched off everywhere.
        $this->withDemoData();

        $this->get('/attendance/'.$this->someoneElsesRecord()['id'])->assertSee('Reject this record', false);
    }

    public function test_rejecting_asks_for_a_reason(): void
    {
        $this->withDemoData();

        $html = $this->get('/attendance/'.$this->someoneElsesRecord()['id'])->getContent();

        $this->assertStringContainsString('Why is this record wrong?', $html);
        $this->assertStringContainsString('name="reason"', $html);
    }

    public function test_no_route_edits_a_recorded_time_or_deletes_a_record(): void
    {
        // A wrong record is rejected with a reason and stays legible. Editing the
        // times would make the record a log of the last person to touch it.
        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'attendance')) {
                continue;
            }

            $this->assertNotContains('DELETE', $route->methods(), "a DELETE route exists at {$route->uri()}");
            $this->assertNotContains('PUT', $route->methods(), "a PUT route exists at {$route->uri()}");
            $this->assertNotContains('PATCH', $route->methods(), "a PATCH route exists at {$route->uri()}");
        }
    }

    public function test_the_write_routes_exist_so_the_forms_are_real(): void
    {
        foreach (['attendance.check-in', 'attendance.check-out', 'attendance.reject', 'attendance.restore'] as $name) {
            $this->assertTrue(app('router')->has($name), "{$name} is missing");
        }
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE CALENDAR
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_calendar_shows_the_current_month_by_default(): void
    {
        // The handover's was thirty-five hand-written cells reading "May 2024".
        $this->withDemoData();

        $this->get('/attendance/mine')->assertSee(Carbon::today()->format('F Y'), false);
    }

    public function test_the_month_arrows_are_links_and_carry_the_month(): void
    {
        $this->withDemoData();

        $previous = Carbon::today()->subMonth()->format('Y-m');

        $html = $this->get('/attendance/mine')->getContent();
        $this->assertStringContainsString(route('attendance.mine', ['month' => $previous]), $html);

        $this->get('/attendance/mine?month='.$previous)
            ->assertOk()
            ->assertSee(Carbon::today()->subMonth()->format('F Y'), false);
    }

    public function test_the_calendar_cannot_be_walked_into_the_future(): void
    {
        // There is nothing to show in a month that has not happened, and an arrow
        // leading to an empty grid looks broken.
        $this->withDemoData();

        $next = Carbon::today()->addMonth()->format('Y-m');

        $this->get('/attendance/mine?month='.$next)
            ->assertOk()
            ->assertSee(Carbon::today()->format('F Y'), false);

        $this->assertStringNotContainsString(
            route('attendance.mine', ['month' => $next]),
            $this->get('/attendance/mine')->getContent(),
        );
    }

    public function test_a_mistyped_month_shows_this_month_rather_than_erroring(): void
    {
        $this->withDemoData();

        // A mistyped URL should show somebody this month, not a stack trace.
        $this->get('/attendance/mine?month=2026-13')
            ->assertOk()
            ->assertSee(Carbon::today()->format('F Y'), false);

        $this->get('/attendance/mine?month=nonsense')->assertSessionHasErrors('month');

        $this->assertSame(Carbon::today()->startOfMonth()->format('Y-m'), P::month('not-a-month')->format('Y-m'));
        $this->assertSame(Carbon::today()->startOfMonth()->format('Y-m'), P::month(null)->format('Y-m'));
    }

    public function test_future_days_are_not_drawn_as_absences(): void
    {
        $this->withDemoData();

        $weeks = P::calendar(Carbon::today()->startOfMonth(), DemoAttendance::forEmployee(DemoAttendance::VIEWER));

        foreach ($weeks as $week) {
            foreach ($week as $cell) {
                if ($cell['future']) {
                    $this->assertNotSame(P::ABSENT, $cell['state'], "{$cell['date']} is drawn absent in the future");
                }
            }
        }
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHAT COUNTS, AND WHAT DOES NOT
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_weekly_off_is_never_an_absence(): void
    {
        $this->withDemoData();

        $sunday = Carbon::today()->startOfWeek(Carbon::SUNDAY);

        while ($sunday->isFuture()) {
            $sunday->subWeek();
        }

        $this->assertFalse(AttendancePolicy::isWorkingDay($sunday));
        $this->assertSame(P::WEEK_OFF, AttendancePolicy::evaluate($sunday, null)['state']);
    }

    public function test_a_holiday_is_never_an_absence(): void
    {
        $this->withDemoData();

        $holiday = array_key_first(AttendancePolicy::holidays());

        $this->assertNotNull($holiday);
        $this->assertSame(P::HOLIDAY, AttendancePolicy::evaluate($holiday, null)['state']);
    }

    public function test_approved_leave_is_not_an_absence(): void
    {
        // The day the company granted must never come back as an absence.
        $this->withDemoData();

        $past = Carbon::today()->subDays(3);

        $this->assertSame(P::LEAVE, AttendancePolicy::evaluate($past, null, true)['state']);
        $this->assertSame(P::ABSENT, AttendancePolicy::evaluate($past, null, false)['state']);
    }

    public function test_nobody_is_absent_before_the_day_is_over(): void
    {
        // Marking somebody absent at 09:31 is how a system loses its credibility
        // before lunch.
        $this->withDemoData();

        if (! AttendancePolicy::isWorkingDay(Carbon::today())) {
            $this->markTestSkipped('today is not a working day');
        }

        $this->assertSame(P::NOT_MARKED, AttendancePolicy::evaluate(Carbon::today(), null)['state']);
    }

    public function test_the_roll_lists_every_active_employee_not_just_the_ones_with_records(): void
    {
        // Absence is what the page exists to show, so the people with no record
        // have to be on it.
        $this->withDemoData();

        $roll = DemoAttendance::forDate(Carbon::today());

        $this->assertGreaterThan(0, $roll->whereNull('id')->count(), 'nobody without a record is on the roll');
        $this->assertSame(0, $roll->where('employee_record.status', 'inactive')->count());
    }

    public function test_a_month_summary_counts_days_not_records(): void
    {
        // Built from records, a summary reports zero absences forever.
        $this->withDemoData();

        $summary = AttendancePolicy::monthSummary(
            Carbon::today()->startOfMonth(),
            DemoAttendance::forEmployee(DemoAttendance::VIEWER),
            DemoAttendance::leaveDates(DemoAttendance::VIEWER),
        );

        $this->assertSame(Carbon::today()->day, (int) $summary['days_counted']);
        $this->assertSame(
            AttendancePolicy::workingDaysInMonth(Carbon::today()->startOfMonth()),
            (int) $summary['working_days'],
        );
    }

    public function test_the_month_is_only_counted_as_far_as_today(): void
    {
        // Counting the whole of a month on the 3rd puts everybody's attendance
        // at eleven per cent.
        $this->withDemoData();

        $this->assertLessThanOrEqual(
            Carbon::today()->day,
            AttendancePolicy::workingDaysInMonth(Carbon::today()->startOfMonth()),
        );
    }

    public function test_no_page_says_anything_about_being_late(): void
    {
        // The concept is gone, not just the column. If the word survives
        // anywhere a reader can see it, the module is still making a judgement
        // it no longer has a rule for.
        $this->withDemoData();

        foreach (['/attendance', '/attendance/mine', '/attendance/'.$this->viewerRecord()['id']] as $url) {
            $html = $this->get($url)->getContent();

            $this->assertStringNotContainsStringIgnoringCase('m late', $html, "lateness on {$url}");
            $this->assertStringNotContainsStringIgnoringCase('On time', $html, "an on-time marker on {$url}");
            $this->assertStringNotContainsStringIgnoringCase('Grace period', $html, "a grace period on {$url}");
        }
    }

    public function test_a_short_day_is_a_half_day_however_early_it_started(): void
    {
        $this->withDemoData();

        $date = Carbon::today()->subDays(8)->toDateString();

        $evaluated = AttendancePolicy::evaluate($date, [
            'date' => $date, 'check_in' => '09:00', 'check_out' => '11:30', 'rejected_at' => null,
        ]);

        $this->assertSame(P::HALF_DAY, $evaluated['state']);
    }

    public function test_a_day_still_running_is_not_called_short(): void
    {
        // An open record is measured to now, so it is short all morning by
        // definition.
        $this->withDemoData();

        $evaluated = AttendancePolicy::evaluate(Carbon::today(), [
            'date' => Carbon::today()->toDateString(),
            'check_in' => Carbon::now()->subMinutes(20)->format('H:i'),
            'check_out' => null,
            'rejected_at' => null,
        ]);

        $this->assertTrue($evaluated['open']);
        $this->assertNotSame(P::HALF_DAY, $evaluated['state']);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE FORGOTTEN CHECK-OUT
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_day_never_checked_out_of_is_surfaced_by_name(): void
    {
        // The handover showed this as a blank cell. It is the most common
        // attendance data problem there is.
        $this->withDemoData();

        $open = DemoAttendance::missingCheckOuts();

        $this->assertNotEmpty($open, 'no sample open record to prove the point');

        $html = $this->get('/attendance')->getContent();
        $this->assertStringContainsString('Never checked out', $html);
        $this->assertStringContainsString($open->first()['employee_record']['name'], $html);
    }

    public function test_an_open_day_inside_the_window_is_not_reported_as_forgotten(): void
    {
        // Somebody still at their desk has not forgotten anything.
        $this->withDemoData();

        foreach (DemoAttendance::missingCheckOuts() as $record) {
            $this->assertTrue(AttendancePolicy::autoRejected($record));
        }
    }

    public function test_a_day_left_open_past_the_window_reads_as_rejected(): void
    {
        $this->withDemoData();

        /*
         * An open day that NOBODY rejected — the point is that the clock alone
         * is enough.
         *
         * Selected rather than taking the first, because a record can be both:
         * HR may have rejected a day that was also left open, and which one the
         * list happens to return first depends on the time of day the suite
         * runs. This test failed for the first time at half past five one
         * afternoon, when today's records stopped being the newest open ones.
         */
        $record = DemoAttendance::missingCheckOuts()
            ->first(fn (array $r) => $r['rejected_at'] === null);

        $this->assertNotNull($record, 'no sample open record to prove the point');

        $found = DemoAttendance::find($record['id']);

        $this->assertSame(P::REJECTED, $found['state']);
        $this->assertTrue($found['auto_rejected']);
        // Rejected by the rule, not by a person — nothing was stamped on it.
        $this->assertNull($found['rejected_at']);
    }

    public function test_the_reason_it_stopped_counting_is_on_the_record(): void
    {
        // "Rejected" on its own would send somebody to HR to ask why. The page
        // says what happened and states the window.
        $this->withDemoData();

        // Auto-rejected ONLY. On a record a person also rejected, their written
        // reason is shown instead — see the test above.
        $record = DemoAttendance::missingCheckOuts()
            ->first(fn (array $r) => $r['rejected_at'] === null);

        $this->assertNotNull($record, 'no sample open record to prove the point');

        $response = $this->get('/attendance/'.$record['id']);

        $response->assertSee('no check-out was recorded', false);
        $response->assertSee((string) (int) AttendancePolicy::autoRejectAfterHours(), false);
    }

    public function test_an_auto_rejected_day_cannot_be_restored_or_rejected_again(): void
    {
        // There is no flag to lift: the state comes from a check-out that is
        // still missing, so "restoring" it would mean inventing the time.
        $this->withDemoData();

        $record = DemoAttendance::missingCheckOuts()
            ->first(fn (array $r) => $r['employee'] !== DemoAttendance::VIEWER);

        $this->assertNotNull($record);

        $html = $this->get('/attendance/'.$record['id'])->getContent();

        $this->assertStringNotContainsString('Restore the record', $html);
        $this->assertStringNotContainsString('Reject this record', $html);
    }

    public function test_the_check_out_button_warns_before_the_window_closes(): void
    {
        // Said before it happens rather than discovered at the end of the month.
        // It is the whole reason the rule is safe to have.
        $this->withDemoData();

        $this->get('/attendance/mine')->assertSee('stops counting', false);
    }

    /* ══════════════════════════════════════════════════════════════════════
       HOLIDAYS COME FROM THE BOARD
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_holiday_announcement_closes_the_office(): void
    {
        $this->withDemoData();

        $next = Holidays::next();

        $this->assertNotNull($next, 'no announced holiday to prove the point');
        $this->assertFalse(AttendancePolicy::isWorkingDay($next['date']));
        $this->assertSame(P::HOLIDAY, AttendancePolicy::evaluate($next['date'], null)['state']);
    }

    public function test_the_policy_card_links_to_the_announcement_rather_than_restating_it(): void
    {
        // Somebody who wants to know why the office is shut should land on what
        // HR actually wrote.
        $this->withDemoData();

        $next = Holidays::next();

        $this->assertNotNull($next['announcement']);

        $this->get('/attendance')->assertSee(
            route('announcements.show', ['announcement' => $next['announcement']]),
            false,
        );
    }

    public function test_attendance_keeps_no_holiday_list_of_its_own(): void
    {
        // Two lists is one list that goes stale, and the failure mode is
        // everybody who stayed home on a closed day being marked absent.
        $this->assertNull(config('attendance.holidays'));
    }

    public function test_a_holiday_whose_notice_has_expired_is_still_a_holiday(): void
    {
        // The office was shut that day whatever the board says about it now.
        $this->withDemoData();

        $past = collect(Holidays::all())->keys()->first(fn (string $date) => $date < Carbon::today()->toDateString());

        $this->assertNotNull($past, 'no past holiday to prove the point');
        $this->assertFalse(AttendancePolicy::isWorkingDay($past));
    }

    public function test_the_notice_window_is_not_the_closure(): void
    {
        // A week's warning about one Friday must not close the office for the
        // week. `observed` is the closure; `from`/`to` is only the notice.
        $this->withDemoData();

        $this->assertLessThanOrEqual(2, count(Holidays::all()));
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHAT PAYS FOR READING HOLIDAYS OFF THE BOARD
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_wrong_holiday_can_only_ever_hide_absences(): void
    {
        // The containment that makes one list safe. A mis-typed closure date
        // must not be able to touch a recorded time, and this is enforced by
        // construction: the holiday branch only runs when there is no check-in.
        $this->withDemoData();

        $next = Holidays::next();
        $date = $next['date'];

        foreach ([['09:00', '18:30', P::PRESENT], ['09:00', '11:00', P::HALF_DAY]] as [$in, $out, $expected]) {
            $evaluated = AttendancePolicy::evaluate($date, [
                'date' => $date, 'check_in' => $in, 'check_out' => $out, 'rejected_at' => null,
            ]);

            $this->assertSame($expected, $evaluated['state'], 'a holiday overrode a recorded day');
            $this->assertNotNull($evaluated['worked_minutes'], 'a holiday erased recorded hours');
        }

        // With no record, and only then, the day reads as a holiday.
        $this->assertSame(P::HOLIDAY, AttendancePolicy::evaluate($date, null)['state']);
    }

    public function test_the_roll_doubts_a_holiday_most_of_the_company_worked(): void
    {
        // The detector that costs nothing to maintain: a real closure has a
        // handful of records, a wrong date has most of the company.
        $this->assertTrue(P::holidayLooksWrong(9, 11));
        $this->assertTrue(P::holidayLooksWrong(4, 11));
        // One or two people working a closure is ordinary, not evidence.
        $this->assertFalse(P::holidayLooksWrong(2, 11));
        $this->assertFalse(P::holidayLooksWrong(0, 11));
        // And it never divides by zero on an empty roll.
        $this->assertFalse(P::holidayLooksWrong(0, 0));
    }

    /**
     * The day-notice partial, rendered on its own.
     *
     * The doubt branch cannot occur in the demo data by construction — the
     * generator writes no records on a non-working day, so nobody ever has one
     * on a holiday — and a branch that only appears when something has gone
     * wrong is exactly the one that ships broken.
     *
     * @param  array<string, mixed>  $data
     */
    protected function dayNotice(array $data): string
    {
        return view('attendance.partials.day-notice', $data + [
            'holiday' => null,
            'workingDay' => true,
            'headcount' => 11,
            'holidayWorked' => 0,
            'holidayAnnouncement' => null,
        ])->render();
    }

    public function test_a_holiday_most_of_the_company_worked_is_flagged_on_the_roll(): void
    {
        $html = $this->dayNotice([
            'holiday' => 'Office closed for the festival holiday',
            'workingDay' => false,
            'holidayWorked' => 9,
            'holidayAnnouncement' => 'ANN-2026-036',
        ]);

        $this->assertStringContainsString('9 of 11 people checked in', $html);
        $this->assertStringContainsString('may be wrong rather than the attendance', $html);
        // And it sends somebody straight to the notice that caused it.
        $this->assertStringContainsString(route('announcements.show', ['announcement' => 'ANN-2026-036']), $html);
    }

    public function test_an_ordinary_holiday_is_not_flagged_as_suspicious(): void
    {
        // One person finishing a release on a closure is not evidence of
        // anything, and crying wolf about it is how the warning gets ignored
        // on the day it matters.
        $html = $this->dayNotice([
            'holiday' => 'Office closed for the festival holiday',
            'workingDay' => false,
            'holidayWorked' => 1,
        ]);

        $this->assertStringNotContainsString('may be wrong rather than the attendance', $html);
        $this->assertStringContainsString('1 person checked in anyway', $html);
    }

    public function test_a_quiet_holiday_says_nothing_about_anybody_checking_in(): void
    {
        $html = $this->dayNotice([
            'holiday' => 'Office closed for the festival holiday',
            'workingDay' => false,
        ]);

        $this->assertStringContainsString('nobody is counted absent.', $html);
        $this->assertStringNotContainsString('checked in anyway', $html);
    }

    public function test_a_weekly_off_is_explained_rather_than_shown_as_eleven_absences(): void
    {
        $html = $this->dayNotice(['workingDay' => false]);

        $this->assertStringContainsString('A weekly off', $html);
    }

    public function test_an_ordinary_working_day_gets_no_notice_at_all(): void
    {
        $this->assertSame('', trim($this->dayNotice([])));
    }

    public function test_declaring_a_closure_is_its_own_permission(): void
    {
        // Posting is broad — HR, project managers, the owner — because a board
        // nobody can post to is a board nobody reads. Closing the office is not.
        $this->assertNotSame(
            config('announcements.post_permission'),
            \App\Support\AnnouncementPresenter::holidayPermission(),
        );
        $this->assertSame('announcements.holiday', \App\Support\AnnouncementPresenter::holidayPermission());
    }

    public function test_the_compose_form_says_what_closure_dates_do(): void
    {
        // A date field labelled "Closed from" is a form. The sentence under it
        // is what makes somebody check the weekday before posting.
        $this->withDemoData();

        $response = $this->get('/announcements/compose');

        $response->assertSee('name="observed_from"', false);
        $response->assertSee('name="observed_to"', false);
        $response->assertSee('Nobody is marked absent on these days', false);
    }

    public function test_the_closure_dates_are_separate_from_the_notice_window(): void
    {
        // The two get confused constantly, and confusing them shuts the office
        // for the whole week a one-day notice was up.
        $this->withDemoData();

        $html = $this->get('/announcements/compose')->getContent();

        $this->assertStringContainsString('Closing the office', $html);
        $this->assertStringContainsString('How long it runs', $html);
        // Still two distinct pairs of date inputs, not one reused for both.
        foreach (['published_at', 'expires_at', 'observed_from', 'observed_to'] as $field) {
            $this->assertSame(1, substr_count($html, 'name="'.$field.'"'), "{$field} is not a single field");
        }
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE USUAL GUARDS
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_demo_source_is_inert_outside_local_debug(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.debug' => false]);

        $this->assertFalse(DemoAttendance::enabled());
        $this->assertTrue(DemoAttendance::forDate(Carbon::today())->isEmpty());
        $this->assertTrue(DemoAttendance::forEmployee(DemoAttendance::VIEWER)->isEmpty());
        $this->assertTrue(DemoAttendance::missingCheckOuts()->isEmpty());
        $this->assertNull(DemoAttendance::find('ATT-2026-09-03-EMP002'));
    }

    public function test_no_figure_is_written_into_the_markup(): void
    {
        // The handover hardcoded 128 / 96 / 12 / 8 / 7 / 5 on the roll and
        // 20 / 2 / 1 / 8h 15m on My Attendance.
        $this->withDemoData();

        $response = $this->get('/attendance');
        $response->assertDontSee('>128<', false);
        $response->assertDontSee('>96<', false);

        $this->get('/attendance/mine')->assertDontSee('8h 15m', false);
    }

    public function test_an_invalid_filter_is_rejected(): void
    {
        $this->get('/attendance?state=approved')->assertSessionHasErrors('state');
        $this->get('/attendance?date=tomorrow')->assertSessionHasErrors('date');
        $this->get('/attendance?date='.Carbon::tomorrow()->toDateString())->assertSessionHasErrors('date');
    }

    public function test_the_filters_narrow_the_roll(): void
    {
        $this->withDemoData();

        $roll = DemoAttendance::forDate(Carbon::today());

        foreach ([P::PRESENT, P::ABSENT, P::HALF_DAY] as $state) {
            $expected = $roll->where('state', $state)->count();

            $this->get('/attendance?state='.$state)->assertSee(
                // The pagination bar is hidden entirely on an empty list, so an
                // empty result proves itself through the empty state instead.
                $expected === 0 ? 'Nobody matches that.' : 'of '.$expected.' employees',
                false,
            );
        }
    }

    public function test_an_unknown_record_is_not_found(): void
    {
        $this->withDemoData();

        $this->get('/attendance/ATT-2019-01-01-EMP002')->assertNotFound();
        $this->get('/attendance/ATT-2026-09-03-EMP999')->assertNotFound();
        $this->get('/attendance/'.urlencode('<script>'))->assertNotFound();
    }

    public function test_the_pages_render_nothing_the_content_security_policy_would_block(): void
    {
        // The handover wired its month arrows and its status dropdowns with
        // inline <script>, and coloured its donut with style="--dot:#15A848" —
        // none of it would have worked.
        $this->withDemoData();

        foreach (['/attendance', '/attendance/mine', '/attendance/'.$this->viewerRecord()['id']] as $url) {
            $html = $this->get($url)->getContent();

            $this->assertSame(0, preg_match_all('/<style[\s>]/i', $html), "inline <style> in {$url}");
            $this->assertSame(0, preg_match_all('/\sstyle="/i', $html), "inline style attribute in {$url}");
            $this->assertSame(0, preg_match_all('/\son[a-z]+="/i', $html), "inline event handler in {$url}");
            $this->assertSame(0, preg_match_all('/<script(?![^>]*\ssrc=)/i', $html), "inline <script> in {$url}");
        }
    }

    public function test_the_sidebar_marks_attendance_as_current(): void
    {
        $this->withDemoData();

        foreach (['/attendance', '/attendance/mine', '/attendance/'.$this->viewerRecord()['id']] as $url) {
            $this->assertSame(
                1,
                substr_count($this->get($url)->getContent(), 'class="sb-link active"'),
                "sidebar current marker wrong on {$url}"
            );
        }
    }
}
