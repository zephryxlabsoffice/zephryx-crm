<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeBanking;
use App\Models\SalaryRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Salary, read from the database.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * NOTHING HERE RETURNS AN UNMASKED IDENTIFIER TO A CALLER THAT DID NOT ASK
 *
 * `banked()` returns the real values, because the only honest place to decide
 * what a viewer may see is App\Support\Sensitive with a viewer in hand — and
 * the bank transfer file, when it exists, needs the real ones. Every page goes
 * through the controller's masking step; nothing renders this directly.
 *
 * THE PAYROLL PAGE LISTS PEOPLE, NOT RECORDS
 *
 * A month's list built from salary records is a list in which the people who
 * have no record are missing — and those are exactly the ones worth seeing,
 * because a person with no record is a person who quietly does not get paid.
 * `forPeriod` therefore walks the employees and looks records up against them,
 * the same shape as the attendance roll.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class SalaryDirectory
{
    /**
     * One month across everybody who was employed in it.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function forPeriod(string $period): Collection
    {
        $records = SalaryRecord::query()
            ->with(['employee.user', 'addedBy.user'])
            ->where('period', $period)
            ->get()
            ->keyBy('employee_id');

        return self::employedIn($period)
            ->map(fn (Employee $employee) => self::row($employee, $records->get($employee->id), $period))
            ->values();
    }

    /**
     * One person's months, most recent first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function forEmployee(?Employee $employee): Collection
    {
        if ($employee === null) {
            return collect();
        }

        return SalaryRecord::query()
            ->with(['employee.user', 'addedBy.user'])
            ->where('employee_id', $employee->id)
            ->orderByDesc('period')
            ->get()
            ->map(fn (SalaryRecord $r) => self::row($employee, $r, $r->period));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $period, string $staffId): ?array
    {
        $employee = Employee::query()
            ->with(['user', 'department', 'designation'])
            ->whereHas('user', fn ($q) => $q->where('user_id', $staffId))
            ->first();

        if ($employee === null) {
            return null;
        }

        $record = SalaryRecord::query()
            ->with(['employee.user', 'addedBy.user'])
            ->where('employee_id', $employee->id)
            ->where('period', $period)
            ->first();

        if ($record === null) {
            return null;
        }

        return self::row($employee, $record, $period);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function latestFor(?Employee $employee): ?array
    {
        return self::forEmployee($employee)->first();
    }

    /**
     * One row in the shape SalaryPresenter and the views read.
     *
     * Takes the employee as well as the record because a person with no record
     * for the month still gets a row — see the head of this class.
     *
     * @return array<string, mixed>
     */
    public static function row(Employee $employee, ?SalaryRecord $record, string $period): array
    {
        $base = [
            'id' => $period.'-'.$employee->user?->user_id,
            'period' => $period,
            'employee' => $employee->user?->user_id,
            'employee_record' => EmployeeDirectory::row($employee),
            'currency' => Money::DEFAULT_CURRENCY,
            'net' => null,
            'payslip' => null,
            'paid_on' => null,
            'method' => 'Bank transfer',
            'record_id' => $record?->id,
        ];

        if ($record === null) {
            return $base;
        }

        return array_merge($base, $record->toRecordArray(), [
            'employee_record' => $base['employee_record'],
            'record_id' => $record->id,
        ]);
    }

    /**
     * The real banking details, or null when there are none on file.
     *
     * @return array<string, string|null>|null
     */
    public static function banked(?Employee $employee): ?array
    {
        if ($employee === null) {
            return null;
        }

        return EmployeeBanking::where('employee_id', $employee->id)->first()?->toRecordArray();
    }

    public static function currentPeriod(): string
    {
        return Carbon::today()->format('Y-m');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $records
     * @return array<string, mixed>
     */
    public static function stats(Collection $records): array
    {
        $withStatus = fn (string $status) => $records->filter(
            fn (array $r) => SalaryPresenter::statusOf($r) === $status
        );

        // Only what has actually gone out. A total that folds in unpaid rows
        // describes an intention, not a payment.
        $paidOut = MoneyBag::of(
            $withStatus(SalaryPresenter::PAID)->map(fn (array $r) => $r['net'])
        );

        // Everything with a payslip on file, paid or not — what the month costs.
        $onFile = MoneyBag::of(
            $records->reject(fn (array $r) => $r['net'] === null)->map(fn (array $r) => $r['net'])
        );

        return [
            'total' => $records->count(),
            'no_payslip' => $withStatus(SalaryPresenter::NO_PAYSLIP)->count(),
            'awaiting_payment' => $withStatus(SalaryPresenter::AWAITING_PAYMENT)->count(),
            'paid' => $withStatus(SalaryPresenter::PAID)->count(),
            'paid_out' => $paidOut,
            'on_file' => $onFile,
        ];
    }

    /**
     * Employees with no salary record for a period at all.
     *
     * Different from "no payslip yet", and worse: without this list they are
     * not on the month's page in any form, so nothing about it makes their
     * absence visible.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function missingFrom(string $period): Collection
    {
        $covered = SalaryRecord::where('period', $period)->pluck('employee_id');

        return self::employedIn($period)
            ->reject(fn (Employee $e) => $covered->contains($e->id))
            ->map(fn (Employee $e) => EmployeeDirectory::row($e))
            ->values();
    }

    /**
     * Employees with no banking details on file.
     *
     * They cannot be paid at all: the transfer file has nothing to send.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function withoutBanking(): Collection
    {
        $known = EmployeeBanking::pluck('employee_id');

        return Employee::query()
            ->with(['user', 'department', 'designation'])
            ->active()
            ->get()
            ->reject(fn (Employee $e) => $known->contains($e->id))
            ->map(fn (Employee $e) => EmployeeDirectory::row($e))
            ->values();
    }

    /**
     * Everybody who was employed during a month.
     *
     * Somebody who joined in October has no September pay, and somebody whose
     * record was closed is not on this month's payroll — but a closed record is
     * still on the months they worked, because they were paid for them.
     *
     * @return Collection<int, Employee>
     */
    protected static function employedIn(string $period): Collection
    {
        $month = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $current = $month->isSameMonth(Carbon::today());

        return Employee::query()
            ->with(['user', 'department', 'designation'])
            ->when($current, fn ($q) => $q->active())
            ->get()
            ->reject(fn (Employee $e) => $e->joined_on !== null
                && $e->joined_on->copy()->startOfMonth()->greaterThan($month))
            ->values();
    }
}
