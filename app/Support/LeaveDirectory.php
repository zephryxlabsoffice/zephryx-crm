<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Leave, read from the database.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE ROW SHAPE IS A CONTRACT WITH THE POLICY AND THE PRESENTER
 *
 * LeavePolicy::balance and every LeavePresenter method take plain arrays and
 * are tested against them. This returns the same shape, plus `employee_record`
 * and `decider_record` for the views.
 *
 * WHAT THE REST OF THE APPLICATION ASKS THIS CLASS
 *
 * `datesFor` is the one Attendance calls, and it is the reason the two modules
 * cannot disagree about whether somebody was off: there is one answer and
 * Attendance does not keep a copy of it.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class LeaveDirectory
{
    /**
     * Every day covered by one person's APPROVED leave, as date strings.
     *
     * Attendance's whole knowledge of leave. Expanded from ranges here rather
     * than stored per day, because a stored day-per-row copy would be a second
     * version of the same fact — and the version that is wrong the moment
     * somebody withdraws a request.
     *
     * @return list<string>
     */
    public static function datesFor(int $employeeId, ?string $from = null, ?string $to = null): array
    {
        $query = LeaveRequest::query()->where('employee_id', $employeeId)->approved();

        if ($from !== null && $to !== null) {
            $query->overlapping($from, $to);
        }

        $dates = [];

        foreach ($query->get(['from_date', 'to_date']) as $request) {
            $cursor = $request->from_date->copy();
            $end = $request->to_date;

            while ($cursor->lessThanOrEqualTo($end)) {
                $dates[] = $cursor->toDateString();
                $cursor->addDay();
            }
        }

        return array_values(array_unique($dates));
    }

    /**
     * The queue query.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<LeaveRequest>
     */
    public static function query(array $filters = [], ?string $status = null): Builder
    {
        $search = ($filters['search'] ?? '') !== '' ? $filters['search'] : null;

        return LeaveRequest::query()
            ->with(['employee.user', 'employee.department', 'decider.user'])
            ->when($status, fn (Builder $q, string $value) => $q->where('status', $value))
            ->when($search, fn (Builder $q, string $term) => $q->where(function (Builder $q) use ($term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

                $q->where('reference', 'like', $like)
                    ->orWhereHas('employee.user', fn (Builder $u) => $u
                        ->where('name', 'like', $like)
                        ->orWhere('user_id', 'like', $like));
            }))
            ->when($filters['type'] ?? null, fn (Builder $q, string $value) => $q->where('type', $value))
            ->when($filters['department'] ?? null, fn (Builder $q, string $name) => $q->whereHas(
                'employee.department', fn (Builder $d) => $d->where('name', $name)
            ))
            // Soonest first: a request that starts tomorrow is the one that has
            // to be decided today.
            ->orderBy('from_date');
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(LeaveRequest $request): array
    {
        return $request->toRecordArray() + [
            'employee_record' => $request->employee ? EmployeeDirectory::row($request->employee) : null,
            'decider_record' => $request->decider ? EmployeeDirectory::row($request->decider) : null,
        ];
    }

    /**
     * One person's requests, newest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function forEmployee(?Employee $employee): Collection
    {
        if ($employee === null) {
            return collect();
        }

        return LeaveRequest::query()
            ->with(['employee.user', 'decider.user'])
            ->where('employee_id', $employee->id)
            ->orderByDesc('applied_at')
            ->get()
            ->map(fn (LeaveRequest $r) => self::row($r));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $reference): ?array
    {
        $record = LeaveRequest::query()
            ->with(['employee.user', 'employee.department', 'employee.designation', 'decider.user'])
            ->where('reference', $reference)
            ->first();

        return $record === null ? null : self::row($record) + ['model' => $record];
    }

    /**
     * Everyone else already off during the same dates.
     *
     * Pending requests are included as well as approved ones: two people asking
     * for the same week is exactly the clash worth catching before either is
     * granted. Approving leave without seeing this is how a team ends up with
     * nobody in on a Friday.
     *
     * @param  array<string, mixed>  $request
     * @return Collection<int, array<string, mixed>>
     */
    public static function clashesWith(array $request): Collection
    {
        return LeaveRequest::query()
            ->with(['employee.user', 'decider.user'])
            ->where('reference', '!=', $request['id'])
            ->whereIn('status', [LeavePresenter::APPROVED, LeavePresenter::PENDING])
            ->overlapping($request['from'], $request['to'])
            ->orderBy('from_date')
            ->get()
            ->map(fn (LeaveRequest $r) => self::row($r));
    }

    /**
     * Who is off today and over the next fortnight.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function upcomingAbsences(int $days = 14): Collection
    {
        return LeaveRequest::query()
            ->with(['employee.user', 'decider.user'])
            ->approved()
            ->overlapping(Carbon::today()->toDateString(), Carbon::today()->addDays($days)->toDateString())
            ->orderBy('from_date')
            ->get()
            ->map(fn (LeaveRequest $r) => self::row($r));
    }

    /**
     * @param  Builder<LeaveRequest>|null  $scope
     * @return array<string, int>
     */
    public static function stats(?Builder $scope = null): array
    {
        $base = fn () => clone ($scope ?? LeaveRequest::query());

        return [
            'total' => $base()->count(),
            'pending' => $base()->where('status', LeavePresenter::PENDING)->count(),
            'approved' => $base()->where('status', LeavePresenter::APPROVED)->count(),
            'rejected' => $base()->where('status', LeavePresenter::REJECTED)->count(),
            'cancelled' => $base()->where('status', LeavePresenter::CANCELLED)->count(),
        ];
    }
}
