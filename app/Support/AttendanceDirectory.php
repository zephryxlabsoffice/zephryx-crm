<?php

namespace App\Support;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\SundayRoster;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Attendance, read from the database.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE ROW SHAPE IS THE ONE AttendancePolicy ALREADY TAKES
 *
 * Plain arrays: id, employee, date, check_in, check_out, rejected_at,
 * rejected_by, rejection_reason. The policy and the presenter are the tested
 * heart of this module and they predate the table — handing them models instead
 * would have meant rewriting them to prove nothing.
 *
 * WHERE `onLeave` COMES FROM
 *
 * A day the company already granted must never be drawn as an absence, and
 * Attendance does not store leave — it asks. `leaveDates()` is one call into
 * LeaveDirectory, and it is the only thing this module knows about leave.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class AttendanceDirectory
{
    /**
     * The dates one person's approved leave covers.
     *
     * Asked of the Leave module, never stored here. Two copies of "was this
     * person off on the 14th" is one copy that eventually disagrees — and the
     * disagreement shows up as somebody being marked absent on a day the
     * company granted them.
     *
     * @return list<string>
     */
    public static function leaveDates(int $employeeId): array
    {
        return LeaveDirectory::datesFor($employeeId);
    }

    /**
     * The Sundays and holidays one person is rostered for.
     *
     * Asked of `sunday_rosters` the same way `leaveDates` asks LeaveDirectory
     * — a fact this module reads rather than a second copy of it.
     *
     * @return list<string>
     */
    public static function rosteredDates(int $employeeId): array
    {
        return SundayRoster::where('employee_id', $employeeId)
            ->pluck('date')
            ->map(fn (Carbon $date) => $date->toDateString())
            ->all();
    }

    /**
     * One person's records, newest first, as policy rows.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function forEmployee(?Employee $employee, int $days = 100): Collection
    {
        if ($employee === null) {
            return collect();
        }

        return AttendanceRecord::query()
            ->with(['employee.user', 'rejecter.user'])
            ->where('employee_id', $employee->id)
            ->whereDate('date', '>=', Carbon::today()->subDays($days)->toDateString())
            ->orderByDesc('date')
            ->get()
            ->map(fn (AttendanceRecord $r) => $r->toRecordArray());
    }

    /**
     * Everyone's standing on one date — a row per PERSON, not per record.
     *
     * The people are the spine and the records are looked up against them: a
     * list built from records is a list in which the people who did not turn up
     * are the only ones missing from the list of who did not turn up.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function forDate(Carbon|string $date): Collection
    {
        $day = Carbon::parse($date);

        $records = AttendanceRecord::query()
            ->with(['employee.user', 'rejecter.user'])
            ->whereDate('date', $day->toDateString())
            ->get()
            ->keyBy('employee_id');

        return Employee::query()
            ->with(['user', 'department', 'designation'])
            // Somebody who has left is not absent; they are gone.
            ->active()
            ->get()
            ->map(function (Employee $employee) use ($day, $records) {
                $record = $records->get($employee->id)?->toRecordArray();
                $dateString = $day->toDateString();
                $onLeave = in_array($dateString, self::leaveDates($employee->id), true);
                $rostered = in_array($dateString, self::rosteredDates($employee->id), true);

                return [
                    'employee' => $employee->user?->user_id,
                    'employee_record' => EmployeeDirectory::row($employee),
                    'date' => $dateString,
                    'record' => $record,
                    'id' => $record['id'] ?? null,
                    'check_in' => $record['check_in'] ?? null,
                    'check_out' => $record['check_out'] ?? null,
                    'rejection_reason' => $record['rejection_reason'] ?? null,
                ] + AttendancePolicy::evaluate($day, $record, $onLeave, $rostered);
            })
            ->values();
    }

    /**
     * One record by its `ATT-{date}-{staff id}` reference.
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $reference): ?array
    {
        $parsed = AttendanceRecord::parseReference($reference);

        if ($parsed === null) {
            return null;
        }

        [$date, $staffId] = $parsed;

        $record = AttendanceRecord::query()
            ->with(['employee.user', 'employee.department', 'employee.designation', 'rejecter.user'])
            ->whereDate('date', $date)
            ->whereHas('employee.user', fn ($q) => $q->where('user_id', $staffId))
            ->first();

        if ($record === null) {
            return null;
        }

        $row = $record->toRecordArray();

        return $row + [
            'model' => $record,
            'employee_record' => EmployeeDirectory::row($record->employee),
            'rejecter_record' => $record->rejecter ? EmployeeDirectory::row($record->rejecter) : null,
        ] + AttendancePolicy::evaluate(
            $date,
            $row,
            in_array($date, self::leaveDates($record->employee_id), true),
            in_array($date, self::rosteredDates($record->employee_id), true),
        );
    }

    /**
     * One person's record for today, or null when they have not checked in.
     *
     * @return array<string, mixed>|null
     */
    public static function today(?Employee $employee): ?array
    {
        if ($employee === null) {
            return null;
        }

        return AttendanceRecord::query()
            ->with(['employee.user', 'rejecter.user'])
            ->where('employee_id', $employee->id)
            ->whereDate('date', Carbon::today()->toDateString())
            ->first()
            ?->toRecordArray();
    }

    /**
     * Days somebody checked into and never checked out of.
     *
     * The management page's rail, and the one thing on it that is actually
     * actionable: it is a conversation with a named person rather than a report.
     *
     * Filtered in PHP after an indexed, bounded query, because "past the
     * ten-hour window" is a comparison against `now` that the policy owns — and
     * a second copy of that arithmetic in SQL is the copy that goes stale when
     * the window changes.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function missingCheckOuts(int $days = 14): Collection
    {
        return AttendanceRecord::query()
            ->with(['employee.user', 'employee.department', 'rejecter.user'])
            ->open()
            ->whereDate('date', '>=', Carbon::today()->subDays($days)->toDateString())
            ->orderByDesc('date')
            ->get()
            ->map(fn (AttendanceRecord $r) => $r->toRecordArray() + [
                'employee_record' => EmployeeDirectory::row($r->employee),
            ])
            ->filter(fn (array $row) => AttendancePolicy::missingCheckOut($row))
            ->values();
    }

    /**
     * Counts per state across a day's roll, plus the headcount they are out of.
     *
     * @param  Collection<int, array<string, mixed>>  $roll
     * @return array<string, int>
     */
    public static function stats(Collection $roll): array
    {
        $counts = array_fill_keys(AttendancePresenter::states(), 0);

        foreach ($roll as $row) {
            $counts[$row['state']] = ($counts[$row['state']] ?? 0) + 1;
        }

        return $counts + ['headcount' => $roll->count()];
    }
}
