<?php

namespace App\Support;

use App\Models\CompOff;
use App\Models\Employee;
use Illuminate\Support\Collection;

/**
 * Comp-offs, read from the database.
 *
 * The row shape CompOffPolicy and the views read: the stored columns, plus
 * `lapsed` and `takeable`, which are asked of CompOffPolicy rather than
 * stored — see the head of that class for why.
 */
class CompOffDirectory
{
    /**
     * @return array<string, mixed>
     */
    public static function row(CompOff $compOff): array
    {
        return [
            'id' => $compOff->id,
            'earned_on' => $compOff->earned_on->toDateString(),
            'expires_on' => $compOff->expires_on->toDateString(),
            'status' => $compOff->status,
            'take_date' => $compOff->take_date?->toDateString(),
            'decided_by' => $compOff->decider?->user?->name,
            'decision_note' => $compOff->decision_note,
            'lapsed' => CompOffPolicy::isLapsed($compOff),
            'takeable' => CompOffPolicy::isTakeable($compOff),
            'employee_record' => $compOff->employee ? EmployeeDirectory::row($compOff->employee) : null,
        ];
    }

    /**
     * One person's comp-offs, newest earned first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function forEmployee(?Employee $employee): Collection
    {
        if ($employee === null) {
            return collect();
        }

        return CompOff::query()
            ->with(['employee.user', 'decider.user'])
            ->where('employee_id', $employee->id)
            ->orderByDesc('earned_on')
            ->get()
            ->map(fn (CompOff $c) => self::row($c));
    }

    /**
     * A running balance: available (and not lapsed) minus none, since a
     * lapsed one was never spent and a taken one already left the count.
     * Counting what is recorded, the same rule LeavePolicy::balance follows.
     */
    public static function availableCount(?Employee $employee): int
    {
        if ($employee === null) {
            return 0;
        }

        return CompOff::query()
            ->where('employee_id', $employee->id)
            ->where('status', CompOff::AVAILABLE)
            ->whereDate('expires_on', '>=', now()->toDateString())
            ->count();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        $compOff = CompOff::query()->with(['employee.user', 'decider.user'])->find($id);

        return $compOff === null ? null : self::row($compOff) + ['model' => $compOff];
    }

    /**
     * Pending take-requests across everybody — the queue a manager or Team
     * Lead works.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function pendingRequests(): Collection
    {
        return CompOff::query()
            ->with(['employee.user', 'employee.department'])
            ->where('status', CompOff::PENDING)
            ->orderBy('take_date')
            ->get()
            ->map(fn (CompOff $c) => self::row($c));
    }
}
