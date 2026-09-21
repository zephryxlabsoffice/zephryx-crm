<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns an attendance record into the things its pages need to draw it.
 */
class AttendancePresenter
{
    /*
     * Three statuses, and then the reasons a day is not being judged.
     *
     * There is no LATE (removed 2026-09-03). Arriving at 09:47 is not a status:
     * it produced a full day's work or it did not, and that is already in the
     * hours. See App\Support\AttendancePolicy.
     */
    public const PRESENT = 'present';
    public const HALF_DAY = 'half_day';
    public const ABSENT = 'absent';
    public const LEAVE = 'leave';
    public const WEEK_OFF = 'week_off';
    public const HOLIDAY = 'holiday';
    public const NOT_MARKED = 'not_marked';
    public const REJECTED = 'rejected';

    /**
     * ─────────────────────────────────────────────────────────────────────────
     * ABSENT AND WEEKLY OFF ARE NOT THE SAME COLOUR, AND NEITHER IS "NOT YET"
     *
     * The handover drew every non-present day the same red. They are three
     * different events: absent is a working day somebody did not turn up for,
     * weekly off is a day nobody was expected, and "not marked" is today, still
     * in progress. Red on all three means a calendar is scarlet every Sunday
     * and every morning before 09:30, which teaches people that red means
     * nothing.
     *
     * Feedback colours are reserved for feedback (§7): green for a day worked,
     * amber for one that needs a look, red only for a real absence and for a
     * rejected record. Weekly offs and holidays get neutral and categorical
     * tones, because neither is good news or bad news.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string}> tone, label, dot, meaning
     */
    protected const STATES = [
        self::PRESENT => ['pill-green', 'Present', 'att-dot-present', 'A full day’s hours'],
        self::HALF_DAY => ['pill-amber', 'Half day', 'att-dot-half', 'Under the half-day threshold'],
        self::ABSENT => ['pill-red', 'Absent', 'att-dot-absent', 'A working day with no check-in'],
        self::LEAVE => ['pill-purple', 'On leave', 'att-dot-leave', 'Approved leave — not an absence'],
        self::WEEK_OFF => ['pill-gray', 'Weekly off', 'att-dot-off', 'Not a working day'],
        self::HOLIDAY => ['pill-indigo', 'Holiday', 'att-dot-holiday', 'A company holiday'],
        self::NOT_MARKED => ['pill-gray', 'Not marked', 'att-dot-pending', 'Today, and nobody has checked in yet'],
        // Rejected is its own state rather than a badge on top of another one:
        // a rejected record does not count as present, and drawing it as
        // "Present (rejected)" is how it ends up in a total that it should not.
        // One state, two ways to reach it: a person rejected the record with a
        // reason, or it was left open past the auto-reject window. The pages
        // tell those apart; the counts do not need to.
        self::REJECTED => ['pill-red', 'Rejected', 'att-dot-rejected', 'Recorded, then rejected — this day does not count'],
    ];

    /**
     * @return list<string>
     */
    public static function states(): array
    {
        return array_keys(self::STATES);
    }

    /**
     * The states worth offering as a filter on a list of one day's roll.
     *
     * "Not marked" is excluded: it is a state a day passes through, not one
     * anybody wants a filtered report of.
     *
     * @return list<string>
     */
    public static function filterableStates(): array
    {
        return [self::PRESENT, self::HALF_DAY, self::ABSENT, self::LEAVE, self::REJECTED];
    }

    /**
     * @return array{tone: string, label: string, dot: string, meaning: string}
     */
    public static function state(string $state): array
    {
        [$tone, $label, $dot, $meaning] = self::STATES[$state]
            ?? ['pill-gray', ucfirst(str_replace('_', ' ', $state)), 'att-dot-off', ''];

        return ['tone' => $tone, 'label' => $label, 'dot' => $dot, 'meaning' => $meaning];
    }

    /**
     * The share of the company that has to turn up on a "holiday" before the
     * holiday itself is the thing in doubt.
     *
     * A third. One or two people working a closure is ordinary — somebody
     * finishing a release, somebody who prefers the empty office. A third of
     * the company is not a dedicated few, it is a day that was not actually a
     * holiday.
     */
    public const HOLIDAY_DOUBT_SHARE = 1 / 3;

    /**
     * Whether a day marked as a holiday looks like it was marked wrong.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THE CHECK THAT COSTS NOTHING TO MAINTAIN
     *
     * Holidays come from the announcements (App\Support\Holidays), which means a
     * mis-typed date on a notice closes the office on a day it was open. The
     * damage is contained — a wrong holiday can only hide ABSENCES, never touch
     * a recorded time, because the holiday branch in AttendancePolicy::state()
     * only runs when there is no check-in at all — but hidden absences are
     * exactly the thing nobody notices.
     *
     * Rather than a second holiday list to reconcile against, the data checks
     * itself: a real closure has a handful of records, a wrong date has most of
     * the company. It needs nothing maintained, and it surfaces the mistake the
     * same day rather than at the end of the month.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public static function holidayLooksWrong(int $withRecords, int $headcount): bool
    {
        if ($headcount === 0 || $withRecords === 0) {
            return false;
        }

        return $withRecords / $headcount >= self::HOLIDAY_DOUBT_SHARE;
    }

    /**
     * Counts per state, as donut segments.
     *
     * One implementation for both donuts — the day's roll and a person's month —
     * so the arcs in a chart and the figures in its legend cannot come from two
     * different sums. Empty states are dropped: a zero-width arc is invisible
     * and a legend entry reading "Rejected 0 (0%)" is noise.
     *
     * @param  array<string, int>  $counts
     * @return list<array{state: string, name: string, count: int, share: float, dot: string}>
     */
    public static function breakdown(array $counts, int $total): array
    {
        if ($total <= 0) {
            return [];
        }

        $segments = [];

        foreach (self::states() as $state) {
            $count = $counts[$state] ?? 0;

            if ($count === 0) {
                continue;
            }

            $meta = self::state($state);

            $segments[] = [
                'state' => $state,
                'name' => $meta['label'],
                'count' => $count,
                'share' => round($count / $total * 100, 1),
                'dot' => $meta['dot'],
            ];
        }

        return $segments;
    }

    /* ══════════════════════════════════════════════════════════════════════
       FORMATTING
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * `09:34 AM`, or an em dash.
     *
     * Twelve-hour with a meridiem, because that is how everyone in the office
     * says it and a check-in time is read far more often than it is sorted.
     */
    public static function time(?string $date, ?string $time): string
    {
        if ($time === null) {
            return '—';
        }

        return Carbon::parse(($date ?? Carbon::today()->toDateString()).' '.$time)->format('h:i A');
    }

    /**
     * `8h 12m`. Null minutes means nothing was recorded, which is not `0h 00m`.
     */
    public static function hours(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }

        return intdiv($minutes, 60).'h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).'m';
    }

    /**
     * How long a day has been open, for a record nobody has closed.
     *
     * Rounded to hours past the first one — the point of the sentence is "this
     * has been open a long time", and minutes make it read like a measurement
     * of work, which it is not.
     */
    public static function openFor(?int $minutes): string
    {
        if ($minutes === null) {
            return '';
        }

        return $minutes < 60
            ? 'Open '.$minutes.' min'
            : 'Open '.intdiv($minutes, 60).' hr';
    }

    public static function date(Carbon|string|null $when): string
    {
        return $when === null ? '—' : Carbon::parse($when)->format('d M Y');
    }

    public static function dateTime(Carbon|string|null $when): string
    {
        return $when === null ? '—' : Carbon::parse($when)->format('d M Y, g:i A');
    }

    /**
     * `Thursday, 03 Sep 2026` — the heading on one day's record.
     */
    public static function longDate(Carbon|string $when): string
    {
        return Carbon::parse($when)->format('l, d M Y');
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE CALENDAR
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * A month laid out as weeks of seven cells, ready to render.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * BUILT ON THE SERVER, NOT DRAWN BY HAND
     *
     * The handover's calendar was 35 hand-written <div>s for May 2024 with the
     * dots typed in, and its month arrows were wired to an inline <script> our
     * CSP blocks — so it showed the wrong month, for the wrong year, and could
     * not be moved off it. This builds the grid from the records, and the month
     * arrows are links carrying `?month=YYYY-MM`, so they work with JavaScript
     * switched off and every month is a URL somebody can bookmark.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * Weeks start on Sunday to match how the office reads a calendar. Leading
     * and trailing cells belong to the neighbouring months and are marked
     * `in_month => false` so they can be drawn quiet rather than left blank —
     * a blank cell reads as a missing day.
     *
     * @param  Collection<int, array<string, mixed>>  $records  one person's records, keyed by nothing in particular
     * @param  array<int, string>  $leaveDates
     * @return list<list<array<string, mixed>>>
     */
    public static function calendar(Carbon $month, Collection $records, array $leaveDates = [], array $rosteredDates = []): array
    {
        $byDate = $records->keyBy('date');

        $cursor = $month->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $end = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $weeks = [];
        $week = [];

        while ($cursor->lessThanOrEqualTo($end)) {
            $date = $cursor->toDateString();
            $record = $byDate->get($date);

            $evaluated = AttendancePolicy::evaluate(
                $cursor,
                $record,
                in_array($date, $leaveDates, true),
                in_array($date, $rosteredDates, true),
            );

            $week[] = [
                'date' => $date,
                'day' => $cursor->day,
                'in_month' => $cursor->isSameMonth($month) && $cursor->isSameYear($month),
                'today' => $cursor->isToday(),
                // A future day has no state worth colouring. Marking one
                // "absent" because nobody has checked in on a date that has not
                // happened is the fastest way to make a calendar untrustworthy.
                'future' => $cursor->isFuture(),
                'state' => $evaluated['state'],
                'record' => $record,
                'holiday' => $evaluated['holiday'],
            ];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }

            $cursor->addDay();
        }

        return $weeks;
    }

    /**
     * The month a `?month=YYYY-MM` parameter refers to.
     *
     * Anything unparseable falls back to the current month rather than erroring:
     * a mistyped URL should show somebody this month, not a stack trace. The
     * range is clamped so the arrows cannot walk somebody into 1970.
     */
    public static function month(?string $value, int $yearsBack = 5): Carbon
    {
        $month = Carbon::today()->startOfMonth();

        if ($value !== null && preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            $parsed = Carbon::createFromFormat('Y-m-d', $value.'-01') ?: null;

            if ($parsed !== null) {
                $month = $parsed->startOfMonth();
            }
        }

        $floor = Carbon::today()->startOfMonth()->subYears($yearsBack);
        $ceiling = Carbon::today()->startOfMonth();

        return $month->lessThan($floor) ? $floor : ($month->greaterThan($ceiling) ? $ceiling : $month);
    }
}
