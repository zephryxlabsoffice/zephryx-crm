<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * The company's attendance policy, and the arithmetic over it.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE POLICY IS NOT OWNED HERE
 *
 * Working hours, the grace period, weekly offs and holidays are company policy,
 * configured in the Admin Panel (§12). This class reads them; it does not define
 * them. Today they come from `config/attendance.php`; when Settings ships they
 * come from a table and only the readers at the top of this file change.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THREE STATUSES, ONE COMPARISON
 *
 * Decided 2026-09-03. A day is PRESENT, HALF DAY or ABSENT, and which one it is
 * comes down to a single question: how many hours, against one threshold.
 *
 *     no check-in on a working day        → absent
 *     hours worked below the threshold    → half day
 *     otherwise                           → present
 *
 * There is no "late". Arriving at 09:47 is not a status: it produced a full
 * day's work or it did not, and that is already in the hours. A late flag adds
 * a fourth thing for people to argue about, needs a grace period to define it,
 * and then needs a policy about what three of them mean - none of which this
 * company has decided, and all of which would be a guess written into a record
 * that follows somebody around.
 *
 * The everything-else states - weekly off, holiday, on leave, not marked,
 * rejected - are not statuses in that sense. They are reasons a day is not
 * being judged at all.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THIS MODULE MEASURES. IT DOES NOT ADJUDICATE.
 *
 * No scoring, no penalty, no deduction, and no rule that turns a short day into
 * half a day off somebody's leave balance. Those are management decisions, and
 * encoding a guess at them produces numbers that quietly disagree with what
 * actually happened to people.
 *
 * What IS computed is how long somebody was here and which state that makes the
 * day. All of it is reproducible from two timestamps and the policy, so it can
 * be checked by hand when somebody disputes it - and somebody will.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class AttendancePolicy
{
    /* ══════════════════════════════════════════════════════════════════════
       THE POLICY, READ
       ══════════════════════════════════════════════════════════════════════ */

    public static function workStart(): string
    {
        return (string) config('attendance.work_start', '09:30');
    }

    public static function workEnd(): string
    {
        return (string) config('attendance.work_end', '18:30');
    }

    /**
     * The one threshold. At or above it a day is present; below it, half.
     */
    public static function halfDayHours(): float
    {
        return (float) config('attendance.half_day_hours', 4.0);
    }

    /**
     * How long a day stays open before it is written off as rejected.
     */
    public static function autoRejectAfterHours(): float
    {
        return (float) config('attendance.auto_reject_after_hours', 10);
    }

    /**
     * @return list<int>
     */
    public static function weekOff(): array
    {
        return array_map('intval', (array) config('attendance.week_off', [0, 6]));
    }

    /**
     * The first day attendance is tracked, or null when it always has been.
     *
     * A blank or unparseable value is null rather than an exception: this is
     * read for every cell of every calendar, and a typo in a setting must not
     * take the attendance pages down with it.
     */
    public static function startsOn(): ?Carbon
    {
        $value = config('attendance.starts_on');

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Whether a date falls before attendance was being tracked.
     */
    public static function beforeTracking(Carbon|string $date): bool
    {
        $start = self::startsOn();

        return $start !== null && Carbon::parse($date)->startOfDay()->lessThan($start);
    }

    /**
     * The days the office is shut, from the holiday announcements.
     *
     * NOT from this module's configuration. HR announces a holiday on the board
     * once and it closes the office - see App\Support\Holidays for why a second
     * list here would be the one that goes stale.
     *
     * @return array<string, string> date => name
     */
    public static function holidays(): array
    {
        return Holidays::all();
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE CALENDAR
       ══════════════════════════════════════════════════════════════════════ */

    public static function isWeekOff(Carbon|string $date): bool
    {
        return in_array(Carbon::parse($date)->dayOfWeek, self::weekOff(), true);
    }

    /**
     * The holiday falling on a date, or null.
     */
    public static function holidayOn(Carbon|string $date): ?string
    {
        return Holidays::on($date);
    }

    /**
     * A day somebody is expected to be here.
     *
     * Everything downstream turns on this: a missing record is only an absence
     * on a working day. Getting it wrong marks the whole company absent every
     * Sunday, which is exactly the kind of number that makes people stop
     * trusting a report.
     */
    public static function isWorkingDay(Carbon|string $date): bool
    {
        return ! self::isWeekOff($date) && self::holidayOn($date) === null;
    }

    /**
     * Working days in a month, up to and including today when the month is the
     * current one.
     *
     * Counting a whole month's working days on the 3rd would put everyone's
     * attendance percentage at single digits until the 28th.
     */
    public static function workingDaysInMonth(Carbon $month, ?Carbon $upTo = null): int
    {
        $cursor = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        // Days before tracking began were not days anybody could be expected
        // to check in on, so they are not in the denominator either.
        $start = self::startsOn();

        if ($start !== null && $cursor->lessThan($start)) {
            $cursor = $start->copy();
        }

        $upTo ??= Carbon::today();

        if ($end->greaterThan($upTo)) {
            $end = $upTo->copy();
        }

        $days = 0;

        while ($cursor->lessThanOrEqualTo($end)) {
            if (self::isWorkingDay($cursor)) {
                $days++;
            }

            $cursor->addDay();
        }

        return $days;
    }

    /* ══════════════════════════════════════════════════════════════════════
       ONE DAY'S RECORD
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * How long an open record has been open, in minutes. Null if it is closed
     * or was never opened.
     */
    public static function openMinutes(string $date, ?string $checkIn, ?string $checkOut): ?int
    {
        if ($checkIn === null || $checkOut !== null) {
            return null;
        }

        $in = Carbon::parse($date.' '.$checkIn);

        return Carbon::now()->greaterThan($in) ? (int) $in->diffInMinutes(Carbon::now()) : 0;
    }

    /**
     * Minutes between check-in and check-out.
     *
     * A record still open is measured to `now`, so somebody watching their own
     * day sees what they have worked so far rather than a dash.
     *
     * Once it has been open past the auto-reject window there are NO hours at
     * all. Running the clock on a day somebody forgot to close produces "51h
     * 46m", which is not a long day - it is a missing timestamp dressed up as a
     * number, and one that would then be counted as work. See `autoRejected`.
     *
     * Null when there is no check-in to measure from.
     */
    public static function workedMinutes(string $date, ?string $checkIn, ?string $checkOut): ?int
    {
        if ($checkIn === null) {
            return null;
        }

        if ($checkOut === null) {
            $open = self::openMinutes($date, $checkIn, $checkOut);

            return $open !== null && $open >= self::autoRejectAfterHours() * 60 ? null : $open;
        }

        $in = Carbon::parse($date.' '.$checkIn);
        $out = Carbon::parse($date.' '.$checkOut);

        // A record whose check-out is before its check-in is corrupt, not
        // negative. Reported as zero and left visible rather than hidden.
        return $out->greaterThan($in) ? (int) $in->diffInMinutes($out) : 0;
    }

    /**
     * Everything a page needs to draw one day, derived from the record.
     *
     * `$onLeave` comes from the Leave module - approved leave is the reason
     * somebody is not here, and a day the company already granted must never
     * be drawn as an absence. Attendance does not store it; it asks.
     *
     * `$rostered` comes from the roster (decided 2026-09-11): a Sunday or
     * holiday a Manager or Team Lead put this specific person on is a working
     * day FOR THEM, whatever the company calendar says about everyone else.
     * Rostered and absent reads as absent, not as a week off - see `state()`.
     *
     * @param  array<string, mixed>|null  $record
     * @return array{
     *     state: string, worked_minutes: int|null, open: bool, open_minutes: int|null,
     *     rejected: bool, auto_rejected: bool, working_day: bool, holiday: string|null
     * }
     */
    public static function evaluate(Carbon|string $date, ?array $record, bool $onLeave = false, bool $rostered = false): array
    {
        $day = Carbon::parse($date);
        $dateString = $day->toDateString();

        $checkIn = $record['check_in'] ?? null;
        $checkOut = $record['check_out'] ?? null;
        $rejectedByPerson = ($record['rejected_at'] ?? null) !== null;

        $holiday = self::holidayOn($day);
        $workingDay = self::isWorkingDay($day);

        $markedPresent = $record !== null && self::markedPresent($record);
        // A marked day has no hours: the clock never stopped, and is not run to now.
        $worked = $markedPresent ? null : self::workedMinutes($dateString, $checkIn, $checkOut);
        $open = $checkIn !== null && $checkOut === null && ! $markedPresent;
        $autoRejected = $record !== null && self::autoRejected($record);

        $state = self::state($day, $checkIn, $worked, $open, $rejectedByPerson || $autoRejected, $onLeave, $workingDay, $holiday, $rostered);

        if ($markedPresent && $state === AttendancePresenter::PRESENT) {
            $state = AttendancePresenter::WARNING;
        }

        return [
            'state' => $state,
            'worked_minutes' => $worked,
            'open' => $open,
            'open_minutes' => self::openMinutes($dateString, $checkIn, $checkOut),
            'rejected' => $rejectedByPerson || $autoRejected,
            // The two rejections are not the same act and the pages have to be
            // able to tell them apart: one was a person's judgement and can be
            // undone, the other is a missing timestamp and cannot.
            'auto_rejected' => $autoRejected,
            'marked_present' => $markedPresent,
            'working_day' => $workingDay,
            'holiday' => $holiday,
        ];
    }

    /**
     * The single state a day resolves to.
     *
     * Order matters, and it is this order because each rule answers a question
     * the ones below it cannot:
     *
     *  1. A rejected record is rejected whatever its times say - that is the
     *     point of rejecting it.
     *  2. Weekly offs and holidays are not working days, so nothing about them
     *     depends on whether anybody checked in. Somebody who did work a
     *     Saturday still shows as present, because the record exists and
     *     hiding it would erase work that was done.
     *  3. A ROSTERED day is a working day for that person, whatever the
     *     calendar says about everyone else - checked before the week-off and
     *     holiday branches below, because rostering exists precisely to
     *     override them for one person on one date.
     *  4. Approved leave beats absence. The company granted the day.
     *  5. A day before attendance tracking began is not tracked, not absent.
     *  6. No check-in on a working day is an absence.
     *  7. A day shorter than the threshold is a half day. Above it, present.
     *     That is the whole judgement.
     */
    protected static function state(
        Carbon $day,
        ?string $checkIn,
        ?int $worked,
        bool $open,
        bool $rejected,
        bool $onLeave,
        bool $workingDay,
        ?string $holiday,
        bool $rostered = false,
    ): string {
        if ($rejected) {
            return AttendancePresenter::REJECTED;
        }

        if ($checkIn === null) {
            if (! $rostered) {
                if ($holiday !== null) {
                    return AttendancePresenter::HOLIDAY;
                }

                if (! $workingDay) {
                    return AttendancePresenter::WEEK_OFF;
                }
            }

            if ($onLeave) {
                return AttendancePresenter::LEAVE;
            }

            // Before tracking began there was nothing to check in to. Only an
            // absence is hidden this way - a record made earlier still counts.
            if (self::beforeTracking($day)) {
                return AttendancePresenter::NOT_TRACKED;
            }

            // A working day still under way is not yet an absence - marking
            // somebody absent at 09:31 is how a system loses its credibility
            // before lunch. Nor is a day that has not arrived: a calendar that
            // draws next Tuesday red is a calendar nobody believes.
            return $day->isToday() || $day->isFuture()
                ? AttendancePresenter::NOT_MARKED
                : AttendancePresenter::ABSENT;
        }

        // An open record is measured to now, so it is short all morning by
        // definition. It reports as present until it is closed and can be
        // judged - or until the auto-reject window closes it, which is handled
        // by the rejection check above.
        if (! $open && $worked !== null && $worked < self::halfDayHours() * 60) {
            return AttendancePresenter::HALF_DAY;
        }

        return AttendancePresenter::PRESENT;
    }

    /**
     * A day left open past the auto-reject window.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * REJECTED BY THE RULE, NOT BY A PERSON
     *
     * Decided 2026-09-03. After the window there is no honest way to say how
     * long somebody worked, and the alternatives are both worse than saying so:
     * inventing a check-out time, or leaving the day counted as present on
     * evidence that stops at 09:18.
     *
     * This is DERIVED, not stamped. No job runs at midnight to write a flag -
     * the record simply reads as rejected once the clock passes the window,
     * which means it is right even if nothing was running that night. It also
     * means it cannot be undone by lifting a flag, because there is no flag:
     * see AttendanceController for why an auto-rejection is not restorable.
     *
     * A record a PERSON rejected is a different thing, with a reason attached.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @param  array<string, mixed>  $record
     */
    public static function autoRejected(array $record): bool
    {
        // HR marked it present (v1.2.1): it counts, with a warning, not hours.
        if (self::markedPresent($record)) {
            return false;
        }

        $open = self::openMinutes($record['date'], $record['check_in'] ?? null, $record['check_out'] ?? null);

        return $open !== null && $open >= self::autoRejectAfterHours() * 60;
    }

    /**
     * A day that was checked into and never checked out of.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THE THING THE HANDOVER LEFT OUT
     *
     * Its screens had no idea this state existed: a record with a check-in and
     * no check-out simply showed a blank cell. It is the single most common
     * attendance data problem there is - somebody closes their laptop and goes
     * home.
     *
     * It is the same condition as `autoRejected`, named separately because the
     * pages say two different things about it. "Rejected" is the STATUS; "never
     * checked out" is WHY, and the why is what somebody needs in order to stop
     * it happening again. Surfaced as its own list on the management page,
     * because it is a conversation with one named person rather than a report
     * nobody reads.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @param  array<string, mixed>  $record
     */
    public static function missingCheckOut(array $record): bool
    {
        return self::autoRejected($record);
    }

    /**
     * A never-closed day HR marked present (v1.2.1).
     *
     * The check-out is still missing and nobody types one in, so the day has
     * no hours. It counts as attended and reads "Present (warning)", which is
     * the honest version of "they were here, the clock does not say for how
     * long". A person's rejection still beats it.
     *
     * @param  array<string, mixed>  $record
     */
    public static function markedPresent(array $record): bool
    {
        return ($record['marked_present_at'] ?? null) !== null
            && ($record['check_in'] ?? null) !== null
            && ($record['check_out'] ?? null) === null;
    }

    /* ══════════════════════════════════════════════════════════════════════
       SUMS OVER MANY DAYS
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * A month's attendance for one person: counts per state, and average hours.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THIS WALKS DAYS, NOT RECORDS
     *
     * The obvious implementation iterates the records and counts their states,
     * and it is wrong in the one way that matters: an absence has no record, so
     * a summary built from records reports zero absences forever. Weekly offs
     * and holidays vanish the same way.
     *
     * So the calendar is the spine and the records are looked up against it.
     * The month is walked only as far as today - counting the whole of
     * September on the 3rd puts everybody's attendance at eleven per cent.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * Counts only. There is deliberately no average working day here (removed
     * 2026-09-03): an average over a handful of days moves wildly on one short
     * afternoon, it hides the days that actually differ, and it is the number
     * people start managing to. The calendar shows every day individually,
     * which is the honest version of the same information.
     *
     * @param  iterable<int, array<string, mixed>>  $records  that person's records
     * @param  array<int, string>  $leaveDates  dates covered by approved leave
     * @param  array<int, string>  $rosteredDates  Sundays/holidays this person is rostered for
     * @return array<string, int|float>
     */
    public static function monthSummary(Carbon $month, iterable $records, array $leaveDates = [], array $rosteredDates = []): array
    {
        $byDate = collect($records)->keyBy('date');

        $counts = array_fill_keys(AttendancePresenter::states(), 0);

        $cursor = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $today = Carbon::today();

        if ($end->greaterThan($today)) {
            $end = $today->copy();
        }

        while ($cursor->lessThanOrEqualTo($end)) {
            $date = $cursor->toDateString();
            $record = $byDate->get($date);

            $evaluated = self::evaluate(
                $cursor,
                $record,
                in_array($date, $leaveDates, true),
                in_array($date, $rosteredDates, true),
            );

            $counts[$evaluated['state']] = ($counts[$evaluated['state']] ?? 0) + 1;

            $cursor->addDay();
        }

        return $counts + [
            'working_days' => self::workingDaysInMonth($month),
            // A half day is a day somebody attended. It is counted here and
            // shown separately in its own tile, rather than being rounded to
            // half a day in a figure captioned "days" - which is the sort of
            // arithmetic nobody can reproduce from their own calendar.
            'attended' => $counts[AttendancePresenter::PRESENT] + $counts[AttendancePresenter::WARNING]
                + $counts[AttendancePresenter::HALF_DAY],
            // Every day the month was walked over, which is what the donut's
            // shares are out of.
            'days_counted' => array_sum($counts),
        ];
    }
}
