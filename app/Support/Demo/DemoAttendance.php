<?php

namespace App\Support\Demo;

use App\Support\AttendancePolicy;
use App\Support\AttendancePresenter as P;
use App\Support\LeavePresenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sample attendance, for reviewing the Attendance pages before the database
 * exists. Local + debug only, like the other demo sources.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHAT AN ATTENDANCE RECORD IS
 *
 * One person, one date, two times. That is the whole thing:
 *
 *     id, employee, date, check_in, check_out
 *     rejected_at, rejected_by, rejection_reason
 *
 * There is no status column. Present, late, half day and absent are DERIVED
 * from those two times and the policy (see App\Support\AttendancePolicy), so a
 * change to the grace period corrects history instead of leaving it disagreeing
 * with the rule that produced it. A stored status is a number that can drift
 * away from the facts underneath it, and attendance is exactly the kind of
 * record somebody eventually argues about.
 *
 * The id is `ATT-{date}-{employee}` rather than a sequence, because a person
 * has at most one record per day and a compound key says so. Two records for
 * one person on one day is the duplicate that HR rejects — and with this key it
 * cannot happen silently.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHY THIS IS GENERATED AND NOT TYPED OUT
 *
 * The calendar needs three months of days for one person and the management
 * roll needs every employee for today. Typed by hand that is several hundred
 * rows nobody would keep consistent — and the handover's were all in May 2024,
 * which by now makes every screen empty.
 *
 * So days are generated from a seed derived from the employee and the date:
 * stable across requests, different per person, and reproducible in a test. The
 * days that are worth *looking at* — the absence, the forgotten check-out, the
 * duplicate that got rejected — are written out by hand in `overrides()`, so
 * every interesting state is on screen on purpose rather than by luck.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class DemoAttendance
{
    /** The person the "my" pages stand in for until authentication lands. */
    public const VIEWER = 'EMP002';

    /** How far back records are generated. Three months fills the calendar. */
    protected const HISTORY_DAYS = 100;

    /** @var array<string, list<string>> */
    protected static array $leaveCache = [];

    public static function enabled(): bool
    {
        return app()->environment('local') && config('app.debug');
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE DAYS WORTH LOOKING AT
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * Hand-written days, keyed by `EMPxxx@n` where n counts WORKING DAYS back
     * from today — 0 is today, 1 is the last working day before it.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * WORKING DAYS BACK, NOT CALENDAR DAYS BACK
     *
     * Keyed by calendar offset, every one of these lands on a Saturday within a
     * week of being written, and a hand-written absence on a weekly off is a
     * demo of nothing. Counting working days means the interesting states are
     * on screen whatever day the pages are opened.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * `null` check-in means the person did not turn up. A `reject` entry is a
     * record HR rejected after the fact — with the reason, because a rejection
     * nobody explained is one the person has to come and ask about.
     *
     * @return array<string, array{in: ?string, out: ?string, reject: ?string, by: ?string}>
     */
    protected static function overrides(): array
    {
        return [
            // ── the viewer's own month ──

            // Today, still checked in. Deliberately open so the check-out
            // button has something to do — and recent enough that it has not
            // tripped the auto-reject window.
            'EMP002@0' => ['in' => '09:52', 'out' => null, 'reject' => null, 'by' => null],
            'EMP002@1' => ['in' => '09:21', 'out' => '18:44', 'reject' => null, 'by' => null],
            // Checked in, went home, never checked out — so the ten-hour window
            // passed and the day reads as rejected. The single most common
            // attendance data problem there is, and the one the handover's
            // screens had no way of showing.
            'EMP002@3' => ['in' => '09:18', 'out' => null, 'reject' => null, 'by' => null],
            // A genuine absence on a working day.
            'EMP002@5' => ['in' => null, 'out' => null, 'reject' => null, 'by' => null],
            // Came in, left before lunch — under the half-day threshold.
            'EMP002@6' => ['in' => '09:12', 'out' => '12:34', 'reject' => null, 'by' => null],
            // A long day, and nothing is made of it. Eleven hours is present,
            // the same as eight — this module counts, it does not reward.
            'EMP002@9' => ['in' => '08:40', 'out' => '19:50', 'reject' => null, 'by' => null],
            // A rejection by a PERSON, which is a different thing from the
            // window closing on an open day. Note what it is NOT: a punishment,
            // or a refusal to let somebody be present. It is a correction to a
            // record that was wrong, and it says what was wrong with it.
            'EMP002@14' => [
                'in' => '09:04', 'out' => '18:02',
                'reject' => 'Duplicate. This day was also recorded from the reception tablet at 09:04 under a shared login. The tablet record is the one being kept.',
                'by' => 'EMP005',
            ],

            // ── other people, so the management roll has every state on it ──

            'EMP004@0' => ['in' => '10:24', 'out' => null, 'reject' => null, 'by' => null],
            'EMP006@0' => ['in' => null, 'out' => null, 'reject' => null, 'by' => null],
            'EMP008@0' => ['in' => '09:08', 'out' => null, 'reject' => null, 'by' => null],
            // Rejected by a person AND left open past the window — both facts
            // true of one record, which is what the detail page's "the window
            // also closed" line exists to say.
            //
            // NOT ON TODAY, and that is load-bearing. `autoRejected` measures
            // check-in against `now`, so a day that started at 08:02 does not
            // trip the ten-hour window until 18:02 — see EMP002@0 above, which
            // relies on the same fact to stay merely open. Parked on today, this
            // record showed the rejection alone for most of the working day and
            // grew the second half of its meaning at six in the evening.
            'EMP010@4' => [
                'in' => '08:02', 'out' => null,
                'reject' => 'Recorded an hour before the office opened and Arjun was on a flight. Raised with IT — the door reader is double-firing.',
                'by' => 'EMP005',
            ],
            // Two more open days that the window has already closed on, so the
            // "never checked out" list is not one row.
            'EMP003@2' => ['in' => '09:33', 'out' => null, 'reject' => null, 'by' => null],
            'EMP007@2' => ['in' => '09:44', 'out' => null, 'reject' => null, 'by' => null],
            // A half day somebody else took, so the roll shows one.
            'EMP001@1' => ['in' => '13:40', 'out' => '17:05', 'reject' => null, 'by' => null],
        ];
    }

    /**
     * How many working days back a date is, or null if it is outside the
     * generated window.
     *
     * Built once and cached: the calendar asks this for every cell of every
     * month, and walking the year back from each one would be quadratic.
     *
     * @var array<string, int>|null
     */
    protected static ?array $workingDayIndex = null;

    protected static function workingDayIndex(string $date): ?int
    {
        if (self::$workingDayIndex === null) {
            $index = [];
            $position = 0;
            $cursor = Carbon::today();
            $floor = Carbon::today()->subDays(self::HISTORY_DAYS);

            while ($cursor->greaterThanOrEqualTo($floor)) {
                if (AttendancePolicy::isWorkingDay($cursor)) {
                    $index[$cursor->toDateString()] = $position++;
                }

                $cursor->subDay();
            }

            self::$workingDayIndex = $index;
        }

        return self::$workingDayIndex[$date] ?? null;
    }

    /* ══════════════════════════════════════════════════════════════════════
       BUILDING RECORDS
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * The dates one person's approved leave covers.
     *
     * Attendance does not store leave. It asks the Leave module, because a day
     * the company already granted must never be drawn as an absence — and two
     * copies of that answer would eventually disagree.
     *
     * @return list<string>
     */
    public static function leaveDates(string $employeeId): array
    {
        if (isset(self::$leaveCache[$employeeId])) {
            return self::$leaveCache[$employeeId];
        }

        $dates = [];

        foreach (DemoLeave::forEmployee($employeeId)->where('status', LeavePresenter::APPROVED) as $request) {
            $cursor = Carbon::parse($request['from']);
            $end = Carbon::parse($request['to']);

            while ($cursor->lessThanOrEqualTo($end)) {
                $dates[] = $cursor->toDateString();
                $cursor->addDay();
            }
        }

        return self::$leaveCache[$employeeId] = $dates;
    }

    /**
     * One record, or null when there is nothing to record: a weekly off, a
     * holiday, an approved leave day, or a date in the future.
     *
     * An absence is the ABSENCE of a record, not a record saying "absent".
     * Writing absence rows would mean deciding, at write time, that somebody is
     * not coming — and then having to delete the row when they walk in at 11.
     *
     * @return array<string, mixed>|null
     */
    protected static function shape(string $employeeId, Carbon $day): ?array
    {
        $date = $day->toDateString();

        if ($day->isFuture() || ! AttendancePolicy::isWorkingDay($day)) {
            return null;
        }

        $position = self::workingDayIndex($date);
        $override = $position === null ? null : (self::overrides()[$employeeId.'@'.$position] ?? null);

        if ($override !== null) {
            return $override['in'] === null ? null : self::record($employeeId, $date, $override['in'], $override['out'], $override['reject'], $override['by']);
        }

        if (in_array($date, self::leaveDates($employeeId), true)) {
            return null;
        }

        // Seeded from the person and the day: stable between requests, so a
        // page does not redraw itself differently on reload, and different per
        // person, so a roll is not twelve identical rows.
        $seed = crc32($employeeId.':'.$date);
        $roll = $seed % 100;

        // Nobody turned up. Kept rare — a demo where a tenth of the company is
        // missing every day is not a demo of anything.
        if ($roll < 5) {
            return null;
        }

        // Arrival times spread across the morning and mean nothing on their own
        // — there is no "late". What decides the day is the gap to check-out.
        $in = 9 * 60 + 2 + ($seed % 52);   // 09:02 – 09:53

        $out = $roll < 9
            // A short day, under the threshold.
            ? $in + 150 + ($seed % 60)
            : $in + 8 * 60 + 20 + ($seed % 70);

        return self::record(
            $employeeId,
            $date,
            self::clock($in),
            // Today is still running: nobody has checked out yet.
            $day->isToday() ? null : self::clock($out),
            null,
            null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected static function record(string $employeeId, string $date, string $in, ?string $out, ?string $reason, ?string $by): array
    {
        return [
            'id' => 'ATT-'.$date.'-'.$employeeId,
            'employee' => $employeeId,
            'date' => $date,
            'check_in' => $in,
            'check_out' => $out,
            // A rejection is stamped, attributed and explained — all three, or
            // it is not auditable (§6).
            'rejected_at' => $reason === null ? null : Carbon::parse($date)->setTime(17, 40),
            'rejected_by' => $reason === null ? null : $by,
            'rejection_reason' => $reason,
        ];
    }

    protected static function clock(int $minutes): string
    {
        $minutes = max(0, min($minutes, 23 * 60 + 59));

        return str_pad((string) intdiv($minutes, 60), 2, '0', STR_PAD_LEFT).':'
            .str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }

    /* ══════════════════════════════════════════════════════════════════════
       READING
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * One person's records, newest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function forEmployee(string $employeeId, int $days = self::HISTORY_DAYS): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        $records = [];

        for ($offset = 0; $offset >= -$days; $offset--) {
            $record = self::shape($employeeId, Carbon::today()->addDays($offset));

            if ($record !== null) {
                $records[] = $record;
            }
        }

        return collect($records);
    }

    /**
     * Everyone's standing on one date — a row per PERSON, not per record.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * A ROLL LISTS PEOPLE, NOT ROWS IN A TABLE
     *
     * The handover's management page listed attendance records, which meant the
     * people who had not turned up were the only ones missing from the list of
     * who had not turned up. Absence is the thing this page exists to show, so
     * everyone active gets a row and the ones with no record are the answer.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function forDate(Carbon|string $date): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        $day = Carbon::parse($date);

        return DemoEmployees::all()
            // Someone who has left the company is not absent; they are gone.
            ->where('status', '!=', 'inactive')
            ->map(function (array $employee) use ($day) {
                $record = self::shape($employee['user_id'], $day);
                $onLeave = in_array($day->toDateString(), self::leaveDates($employee['user_id']), true);

                return [
                    'employee' => $employee['user_id'],
                    'employee_record' => $employee,
                    'date' => $day->toDateString(),
                    'record' => $record,
                    'id' => $record['id'] ?? null,
                    'check_in' => $record['check_in'] ?? null,
                    'check_out' => $record['check_out'] ?? null,
                    'rejection_reason' => $record['rejection_reason'] ?? null,
                ] + AttendancePolicy::evaluate($day, $record, $onLeave);
            })
            ->values();
    }

    /**
     * One record by its `ATT-{date}-{employee}` id.
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $id): ?array
    {
        if (! self::enabled()) {
            return null;
        }

        if (preg_match('/^ATT-(\d{4}-\d{2}-\d{2})-(EMP\d{3})$/', $id, $matches) !== 1) {
            return null;
        }

        [, $date, $employeeId] = $matches;

        // Outside the window this source generates, there is no record — and
        // saying so is the difference between a 404 and inventing a Tuesday in
        // 2019 for anybody who edits the URL.
        if (self::workingDayIndex($date) === null) {
            return null;
        }

        $employee = DemoEmployees::all()->firstWhere('user_id', $employeeId);

        if ($employee === null) {
            return null;
        }

        $day = Carbon::parse($date);
        $record = self::shape($employeeId, $day);

        if ($record === null) {
            return null;
        }

        $onLeave = in_array($date, self::leaveDates($employeeId), true);

        return $record + [
            'employee_record' => $employee,
            'rejecter_record' => $record['rejected_by'] ? DemoEmployees::all()->firstWhere('user_id', $record['rejected_by']) : null,
        ] + AttendancePolicy::evaluate($day, $record, $onLeave);
    }

    /**
     * The viewer's record for today, or null if they have not checked in.
     *
     * @return array<string, mixed>|null
     */
    public static function today(string $employeeId): ?array
    {
        return self::shape($employeeId, Carbon::today());
    }

    /**
     * Days somebody checked into and never checked out of.
     *
     * The management page's rail. See AttendancePolicy::missingCheckOut for why
     * this is worth its own list rather than a blank cell in a table.
     *
     * Starts at today, not yesterday: somebody who checked in at 07:00 has
     * tripped the ten-hour window by 17:00, and the day it is worth mentioning
     * to them is that one.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function missingCheckOuts(int $days = 14): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        $open = [];

        foreach (DemoEmployees::all()->where('status', '!=', 'inactive') as $employee) {
            for ($offset = 0; $offset >= -$days; $offset--) {
                $record = self::shape($employee['user_id'], Carbon::today()->addDays($offset));

                if ($record !== null && AttendancePolicy::missingCheckOut($record)) {
                    $open[] = $record + ['employee_record' => $employee];
                }
            }
        }

        return collect($open)->sortByDesc('date')->values();
    }

    /**
     * Records a PERSON rejected, newest first — not the ones the ten-hour
     * window closed on, which are `missingCheckOuts()`.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function rejected(int $days = 30): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        $rejected = [];

        foreach (DemoEmployees::all()->where('status', '!=', 'inactive') as $employee) {
            for ($offset = 0; $offset >= -$days; $offset--) {
                $record = self::shape($employee['user_id'], Carbon::today()->addDays($offset));

                if ($record !== null && $record['rejected_at'] !== null) {
                    $rejected[] = $record + ['employee_record' => $employee];
                }
            }
        }

        return collect($rejected)->sortByDesc('date')->values();
    }

    /**
     * Counts per state across a day's roll, plus the headcount they are out of.
     *
     * @param  Collection<int, array<string, mixed>>  $roll
     * @return array<string, int>
     */
    public static function stats(Collection $roll): array
    {
        $counts = array_fill_keys(P::states(), 0);

        foreach ($roll as $row) {
            $counts[$row['state']] = ($counts[$row['state']] ?? 0) + 1;
        }

        return $counts + ['headcount' => $roll->count()];
    }
}
