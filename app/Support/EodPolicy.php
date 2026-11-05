<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EodReport;
use App\Models\LeaveRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * When End of Day reports begin, and who is expected to file one.
 *
 * The start date is Admin > Settings > "End of Day starts on". Until then the form
 * and Team Reports are closed, tasks are finished from their own page as before, and
 * nobody is reminded. Blank means it has always been running, the same as
 * "Attendance starts on".
 */
class EodPolicy
{
    /**
     * A blank or unparseable value is null rather than an exception, so a typo in a
     * setting cannot take the Tasks pages down.
     */
    public static function startsOn(): ?Carbon
    {
        $value = config('tasks.eod_starts_on');

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function isLive(): bool
    {
        $start = self::startsOn();

        return $start === null || ! $start->isFuture();
    }

    /**
     * Everybody who should have filed for this day and has not.
     *
     * Current staff with an employee record, from the newest hire to the Managers and
     * the CEO, who had joined by then and were not on approved leave. A blank joining
     * date, which an account made in the Admin Panel can have, does not excuse anybody. An empty result on a week-off or holiday: nobody owes a report
     * for a day the office was closed.
     *
     * @return Builder<Employee>
     */
    public static function notFiledOn(string $date): Builder
    {
        $query = Employee::query()->active();

        if (! AttendancePolicy::isWorkingDay($date)) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereHas('user', fn (Builder $user) => $user
                ->where('status', 'active')
                ->where('account_type', Realm::STAFF)
                ->where('staff_kind', 'employee'))
            ->where(fn (Builder $joined) => $joined
                ->whereNull('joined_on')
                ->orWhereDate('joined_on', '<=', $date))
            ->whereNotIn('id', EodReport::query()->whereDate('report_date', $date)->select('employee_id'))
            ->whereNotIn('id', LeaveRequest::query()->approved()->overlapping($date, $date)->select('employee_id'));
    }
}
