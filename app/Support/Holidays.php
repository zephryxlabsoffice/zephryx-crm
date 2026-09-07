<?php

namespace App\Support;

use App\Support\Demo\DemoAnnouncements;
use Illuminate\Support\Carbon;

/**
 * The days the office is closed, read from the holiday announcements.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ONE LIST, NOT TWO
 *
 * Decided 2026-09-03. A holiday is announced on the board — "office closed on
 * the 14th" — and that announcement IS the holiday. Attendance reads it rather
 * than keeping its own calendar.
 *
 * The alternative is a holiday list in the Admin Panel that has to be kept in
 * step with the announcements people actually read, and the failure mode is
 * specific and bad: HR posts the notice, forgets the list, and everybody who
 * stayed home on a day the company closed is marked absent for it. One list
 * cannot disagree with itself.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * A HOLIDAY HAS TWO DATE RANGES AND THEY ARE NOT THE SAME
 *
 * `from`/`to` on an announcement is when the NOTICE is on the board — a week's
 * warning about one Friday. `observed_from`/`observed_to` is when the OFFICE IS
 * SHUT. Attendance wants the second one, and reading the first would close the
 * office for the whole week the notice was up.
 *
 * A holiday announcement with no observed dates is a notice about a holiday
 * rather than a holiday — a policy change, a reminder — and is skipped rather
 * than guessed at.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * DRAFTS ARE NOT HOLIDAYS
 *
 * An unpublished announcement closes nothing. Somebody drafting "office closed
 * for Diwali" while the dates are still being confirmed must not have already
 * changed everyone's attendance record.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHAT THIS COSTS, AND WHAT PAYS FOR IT
 *
 * Reading holidays from the board means a mis-typed date on a notice closes the
 * office on a day it was open. That is the price of one list, and it is worth
 * paying — but it is a real cost and three things carry it:
 *
 * 1. THE DAMAGE IS CONTAINED BY CONSTRUCTION. The holiday branch in
 *    AttendancePolicy::state() only runs when there is no check-in, so a wrong
 *    holiday can only ever hide ABSENCES. It cannot alter a recorded time, turn
 *    a half day into a full one, or change anybody's hours. Keep it that way:
 *    a holiday must never outrank a record.
 *
 * 2. SETTING THE DATES IS ITS OWN PERMISSION. `announcements.holiday` — HR and
 *    the owner, not everyone who may post. See config/announcements.php.
 *
 * 3. THE ATTENDANCE ROLL CHECKS IT AGAINST REALITY. A day marked a holiday that
 *    most of the company checked in on is a wrong date, and the roll says so —
 *    see AttendancePresenter::holidayLooksWrong. It needs nothing maintained,
 *    which is the whole reason it is a detector and not a second list.
 *
 * TODO (backend phase): editing or removing observed dates on a PAST holiday
 * retroactively changes whether people were absent, and needs an audit entry
 * naming who and when (§6).
 * ─────────────────────────────────────────────────────────────────────────────
 */
class Holidays
{
    /**
     * Memoised per request, keyed on whether the source is live.
     *
     * `AttendancePolicy::isWorkingDay()` asks this for every cell of a calendar
     * and every day of a hundred-day history — rebuilding the announcement
     * board each time turns one page render into a few thousand of them. The
     * key rather than a plain flag is so a test that switches the environment
     * off does not read a list built while it was on.
     *
     * @var array<string, array<string, string>>
     */
    protected static array $cache = [];

    /**
     * Every closed day, as `date => name`.
     *
     * Named by the announcement's title, so the calendar says "Office closed
     * for Independence Day" in the same words the board did.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        $key = DemoAnnouncements::enabled() ? 'live' : 'inert';

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $holidays = [];

        foreach (self::announcements() as $announcement) {
            $from = $announcement['observed_from'] ?? null;
            $to = $announcement['observed_to'] ?? $from;

            if ($from === null) {
                continue;
            }

            $cursor = Carbon::parse($from);
            $end = Carbon::parse($to);

            // A range written backwards is a data problem, not a year-long
            // holiday. Treated as the single day it starts on.
            if ($end->lessThan($cursor)) {
                $end = $cursor->copy();
            }

            while ($cursor->lessThanOrEqualTo($end)) {
                $holidays[$cursor->toDateString()] = $announcement['title'];
                $cursor->addDay();
            }
        }

        ksort($holidays);

        return self::$cache[$key] = $holidays;
    }

    public static function on(Carbon|string $date): ?string
    {
        return self::all()[Carbon::parse($date)->toDateString()] ?? null;
    }

    /**
     * The next closed day from today, with the announcement that declared it.
     *
     * The announcement id comes back so the policy card can link to the notice
     * rather than restating it — somebody who wants to know why the office is
     * shut should land on what HR actually wrote.
     *
     * @return array{date: string, name: string, announcement: string|null}|null
     */
    public static function next(): ?array
    {
        $today = Carbon::today()->toDateString();

        foreach (self::all() as $date => $name) {
            if ($date >= $today) {
                return [
                    'date' => $date,
                    'name' => $name,
                    'announcement' => self::announcementFor($date),
                ];
            }
        }

        return null;
    }

    /**
     * The announcement that closed the office on a date, if any.
     *
     * So a page can link to what HR actually wrote rather than restating it —
     * and, when a closure looks wrong, send somebody straight to the notice
     * that caused it instead of making them go and find it.
     */
    public static function announcementOn(Carbon|string $date): ?string
    {
        return self::announcementFor(Carbon::parse($date)->toDateString());
    }

    protected static function announcementFor(string $date): ?string
    {
        foreach (self::announcements() as $announcement) {
            $from = $announcement['observed_from'] ?? null;

            if ($from === null) {
                continue;
            }

            if ($date >= $from && $date <= ($announcement['observed_to'] ?? $from)) {
                return $announcement['id'];
            }
        }

        return null;
    }

    /**
     * Published holiday announcements.
     *
     * Today this is the demo board, which is inert outside local + debug — so
     * a deployed site has no holidays until somebody announces one, which is
     * the correct answer rather than an invented calendar. When Announcements
     * gets its table, only this method changes.
     *
     * @return iterable<int, array<string, mixed>>
     */
    protected static function announcements(): iterable
    {
        return DemoAnnouncements::holidays();
    }
}
