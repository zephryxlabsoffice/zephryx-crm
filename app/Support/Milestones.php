<?php

namespace App\Support;

use App\Support\Demo\DemoEmployees;
use Illuminate\Support\Carbon;

/**
 * Birthdays and work anniversaries, computed rather than posted.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * A BIRTHDAY IS A DAY AND A MONTH
 *
 * Date of birth is stored in full — it belongs on an employee record — but this
 * class never lets the year out. A colleague needs to know when to say happy
 * birthday, not how old somebody is, and once everyone's age is on an internal
 * page it is much harder to take back than to have not put it there.
 *
 * `label()` and `dayAndMonth()` are the only ways a birthday reaches a page, and
 * a test asserts no birth year appears in any rendered announcement.
 *
 * A work anniversary is different: the year IS the point. "Three years today"
 * is the thing worth saying, and it comes from a joining date that is already
 * on every employee record.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ANYONE MAY OPT OUT OF THEIR OWN
 *
 * `announce_milestones` on the employee record. Not everyone wants their
 * birthday on a company board, and finding out you cannot turn it off is a bad
 * way to learn that. Opting out removes them from the feed entirely — it does
 * not post a quieter version.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class Milestones
{
    public const BIRTHDAY = 'birthday';
    public const ANNIVERSARY = 'anniversary';

    /**
     * Milestones falling within the window, soonest first.
     *
     * @param  string|null  $only  restrict to one kind
     * @return list<array<string, mixed>>
     */
    public static function upcoming(?string $only = null, ?int $days = null): array
    {
        $days ??= (int) config('announcements.milestones.window_days', 14);
        $today = Carbon::today();
        $found = [];

        foreach (DemoEmployees::all() as $employee) {
            // Somebody who has left is not celebrated, and somebody who opted
            // out is not celebrated either.
            if (($employee['status'] ?? null) === 'inactive') {
                continue;
            }

            if (! ($employee['announce_milestones'] ?? true)) {
                continue;
            }

            if ($only !== self::ANNIVERSARY && config('announcements.milestones.birthdays', true)) {
                $found[] = self::birthdayFor($employee, $today, $days);
            }

            if ($only !== self::BIRTHDAY && config('announcements.milestones.anniversaries', true)) {
                $found[] = self::anniversaryFor($employee, $today, $days);
            }
        }

        $found = array_values(array_filter($found));

        usort($found, fn (array $a, array $b) => $a['in_days'] <=> $b['in_days']);

        return $found;
    }

    /**
     * @param  array<string, mixed>  $employee
     * @return array<string, mixed>|null
     */
    protected static function birthdayFor(array $employee, Carbon $today, int $days): ?array
    {
        if (empty($employee['dob'])) {
            return null;
        }

        $next = self::nextOccurrence($employee['dob'], $today);
        $away = (int) $today->diffInDays($next, false);

        if ($away > $days) {
            return null;
        }

        return [
            'kind' => self::BIRTHDAY,
            'employee' => $employee,
            'date' => $next->toDateString(),
            'in_days' => $away,
            'label' => self::label($away),
            // Day and month only. The year is deliberately absent.
            'day_month' => self::dayAndMonth($employee['dob']),
            'title' => $away === 0
                ? $employee['name'].'’s birthday is today'
                : $employee['name'].'’s birthday',
            'body' => $away === 0
                ? 'Wish them a happy birthday.'
                : 'Coming up on '.self::dayAndMonth($employee['dob']).'.',
            'years' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $employee
     * @return array<string, mixed>|null
     */
    protected static function anniversaryFor(array $employee, Carbon $today, int $days): ?array
    {
        if (empty($employee['joined'])) {
            return null;
        }

        $joined = Carbon::parse($employee['joined']);
        $next = self::nextOccurrence($employee['joined'], $today);
        $away = (int) $today->diffInDays($next, false);

        if ($away > $days) {
            return null;
        }

        $years = $next->year - $joined->year;

        // Nobody celebrates a nought-year anniversary on their first day.
        if ($years < 1) {
            return null;
        }

        return [
            'kind' => self::ANNIVERSARY,
            'employee' => $employee,
            'date' => $next->toDateString(),
            'in_days' => $away,
            'label' => self::label($away),
            'day_month' => self::dayAndMonth($employee['joined']),
            'title' => $away === 0
                ? $employee['name'].' has been here '.self::years($years)
                : $employee['name'].'’s work anniversary',
            'body' => $away === 0
                ? 'Joined on '.Carbon::parse($employee['joined'])->format('d M Y').'.'
                : self::years($years).' on '.self::dayAndMonth($employee['joined']).'.',
            'years' => $years,
        ];
    }

    /**
     * The next time a day-and-month comes round, today included.
     *
     * 29 February is the awkward one: in a common year Carbon would roll it to
     * 1 March, which quietly moves somebody's birthday. It is pinned to 28
     * February instead, so the person is celebrated in the month they were
     * born.
     */
    protected static function nextOccurrence(string $date, Carbon $today): Carbon
    {
        $source = Carbon::parse($date);
        $month = (int) $source->format('n');
        $day = (int) $source->format('j');

        $make = function (int $year) use ($month, $day) {
            if ($month === 2 && $day === 29 && ! Carbon::create($year, 1, 1)->isLeapYear()) {
                return Carbon::create($year, 2, 28)->startOfDay();
            }

            return Carbon::create($year, $month, $day)->startOfDay();
        };

        $thisYear = $make($today->year);

        return $thisYear->greaterThanOrEqualTo($today) ? $thisYear : $make($today->year + 1);
    }

    /**
     * `14 Mar` — never the year.
     */
    public static function dayAndMonth(string $date): string
    {
        return Carbon::parse($date)->format('d M');
    }

    public static function label(int $away): string
    {
        return match (true) {
            $away === 0 => 'Today',
            $away === 1 => 'Tomorrow',
            $away <= 7 => "In {$away} days",
            default => "In {$away} days",
        };
    }

    public static function years(int $years): string
    {
        return $years === 1 ? '1 year' : $years.' years';
    }

    /**
     * Milestones happening today — the ones that become board posts.
     *
     * @return list<array<string, mixed>>
     */
    public static function today(): array
    {
        return array_values(array_filter(
            self::upcoming(),
            fn (array $m) => $m['in_days'] === 0
        ));
    }
}
