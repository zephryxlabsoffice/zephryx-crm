<?php

namespace App\Support\Demo;

use App\Support\LeavePresenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sample leave requests, for reviewing the Leave pages before the database
 * exists. Local + debug only, like the other demo sources.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHAT A LEAVE REQUEST IS
 *
 * Who asked, what type, which dates, how many days it costs, why, and what was
 * decided. `days` is stated by the requester — this module counts what is
 * recorded rather than working out what should be counted (decided 2026-08-28;
 * see App\Support\LeavePolicy).
 *
 * Dates are day offsets from today, for the same reason DemoProjects uses them:
 * the handover's were all in May 2024, which by now makes every request "already
 * over" and the pending queue impossible to review.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class DemoLeave
{
    /** The person the "my" pages stand in for until authentication lands. */
    public const VIEWER = 'EMP002';

    public static function enabled(): bool
    {
        return app()->environment('local') && config('app.debug');
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function rows(): array
    {
        return [
            // ── pending, and close enough to matter ──
            ['id' => 'LV-2026-041', 'employee' => 'EMP004', 'type' => 'casual', 'from' => 1, 'to' => 1, 'days' => 1,
             'reason' => 'Personal work', 'applied' => -2, 'status' => 'pending', 'decided_by' => null, 'note' => null, 'contact' => '+91 98200 11223'],
            ['id' => 'LV-2026-040', 'employee' => 'EMP007', 'type' => 'sick', 'from' => 0, 'to' => 1, 'days' => 2,
             'reason' => 'Fever, seeing a doctor tomorrow', 'applied' => 0, 'status' => 'pending', 'decided_by' => null, 'note' => null, 'contact' => '+91 98200 44556'],
            ['id' => 'LV-2026-039', 'employee' => 'EMP003', 'type' => 'privilege', 'from' => 12, 'to' => 16, 'days' => 5,
             'reason' => 'Family holiday, booked in March', 'applied' => -5, 'status' => 'pending', 'decided_by' => null, 'note' => null, 'contact' => '+91 98200 77889'],
            // Deliberately overlaps LV-2026-039, so the clash panel has
            // something to show an approver.
            ['id' => 'LV-2026-038', 'employee' => 'EMP001', 'type' => 'privilege', 'from' => 14, 'to' => 18, 'days' => 5,
             'reason' => 'Wedding in the family', 'applied' => -6, 'status' => 'pending', 'decided_by' => null, 'note' => null, 'contact' => '+91 98200 99001'],
            ['id' => 'LV-2026-037', 'employee' => 'EMP008', 'type' => 'unpaid', 'from' => 20, 'to' => 24, 'days' => 5,
             'reason' => 'Extended trip, beyond my remaining balance', 'applied' => -3, 'status' => 'pending', 'decided_by' => null, 'note' => null, 'contact' => '+91 98200 22334'],

            // The viewer's own pending request. Kept deliberately so the rule
            // that nobody decides their own leave is reviewable on screen and
            // not only in a test.
            ['id' => 'LV-2026-042', 'employee' => 'EMP002', 'type' => 'casual', 'from' => 26, 'to' => 27, 'days' => 2,
             'reason' => 'Long weekend away', 'applied' => -1, 'status' => 'pending', 'decided_by' => null, 'note' => null, 'contact' => '+91 98200 55667'],

            // ── decided ──
            ['id' => 'LV-2026-036', 'employee' => 'EMP002', 'type' => 'casual', 'from' => 9, 'to' => 9, 'days' => 1,
             'reason' => 'Personal work', 'applied' => -4, 'status' => 'approved', 'decided_by' => 'EMP005', 'note' => null, 'contact' => '+91 98200 55667'],
            ['id' => 'LV-2026-035', 'employee' => 'EMP002', 'type' => 'sick', 'from' => -12, 'to' => -12, 'days' => 1,
             'reason' => 'Migraine', 'applied' => -13, 'status' => 'approved', 'decided_by' => 'EMP005', 'note' => null, 'contact' => '+91 98200 55667'],
            ['id' => 'LV-2026-034', 'employee' => 'EMP002', 'type' => 'privilege', 'from' => -34, 'to' => -32, 'days' => 3,
             'reason' => 'Short break', 'applied' => -45, 'status' => 'approved', 'decided_by' => 'EMP005', 'note' => null, 'contact' => '+91 98200 55667'],
            ['id' => 'LV-2026-033', 'employee' => 'EMP002', 'type' => 'casual', 'from' => -60, 'to' => -59, 'days' => 2,
             'reason' => 'Family function', 'applied' => -66, 'status' => 'approved', 'decided_by' => 'EMP005', 'note' => null, 'contact' => '+91 98200 55667'],
            // A rejection carries its reason. The handover's reject button
            // captured nothing, which leaves the person guessing.
            ['id' => 'LV-2026-032', 'employee' => 'EMP002', 'type' => 'casual', 'from' => -20, 'to' => -18, 'days' => 3,
             'reason' => 'Long weekend', 'applied' => -24, 'status' => 'rejected', 'decided_by' => 'EMP005',
             'note' => 'Client review was scheduled for that week. Happy to approve the following week instead.', 'contact' => '+91 98200 55667'],
            // Withdrawn by the person who asked — not the same as refused.
            ['id' => 'LV-2026-031', 'employee' => 'EMP002', 'type' => 'casual', 'from' => -40, 'to' => -40, 'days' => 1,
             'reason' => 'Personal work', 'applied' => -44, 'status' => 'cancelled', 'decided_by' => null,
             'note' => 'Plans changed, no longer needed.', 'contact' => '+91 98200 55667'],
            ['id' => 'LV-2026-030', 'employee' => 'EMP006', 'type' => 'sick', 'from' => -6, 'to' => -5, 'days' => 2,
             'reason' => 'Food poisoning', 'applied' => -6, 'status' => 'approved', 'decided_by' => 'EMP005', 'note' => null, 'contact' => '+91 98200 33445'],
            ['id' => 'LV-2026-029', 'employee' => 'EMP009', 'type' => 'casual', 'from' => -3, 'to' => -3, 'days' => 0.5,
             'reason' => 'Bank appointment, back after lunch', 'applied' => -5, 'status' => 'approved', 'decided_by' => 'EMP005', 'note' => null, 'contact' => '+91 98200 66778'],
            ['id' => 'LV-2026-028', 'employee' => 'EMP010', 'type' => 'casual', 'from' => -15, 'to' => -15, 'days' => 1,
             'reason' => 'Moving flat', 'applied' => -18, 'status' => 'approved', 'decided_by' => 'EMP005', 'note' => null, 'contact' => '+91 98200 88990'],
            ['id' => 'LV-2026-027', 'employee' => 'EMP012', 'type' => 'casual', 'from' => -50, 'to' => -49, 'days' => 2,
             'reason' => 'Personal', 'applied' => -55, 'status' => 'rejected', 'decided_by' => 'EMP005',
             'note' => 'Notice period — handover was scheduled for those days.', 'contact' => '+91 98200 12345'],
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function all(): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        $employees = DemoEmployees::all()->keyBy('user_id');

        return collect(self::rows())
            ->map(function (array $row) use ($employees) {
                $employee = $employees->get($row['employee']);

                if ($employee === null) {
                    return null;
                }

                return $row + [
                    'employee_record' => $employee,
                    'decider_record' => $row['decided_by'] ? $employees->get($row['decided_by']) : null,
                    'from_date' => Carbon::today()->addDays($row['from'])->toDateString(),
                    'to_date' => Carbon::today()->addDays($row['to'])->toDateString(),
                    'applied_at' => Carbon::today()->addDays($row['applied'])->setTime(10, 25),
                ];
            })
            ->filter()
            // The presenter reads `from` and `to` as dates, so they carry the
            // resolved values rather than the offsets they were written as.
            ->map(fn (array $row) => array_merge($row, [
                'from' => $row['from_date'],
                'to' => $row['to_date'],
            ]))
            ->values();
    }

    public static function find(string $id): ?array
    {
        return self::all()->firstWhere('id', $id);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function forEmployee(string $employeeId): Collection
    {
        return self::all()->where('employee', $employeeId)->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function pending(): Collection
    {
        return self::all()->where('status', LeavePresenter::PENDING)->values();
    }

    /**
     * Everyone else already off during the same dates.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THE THING THE HANDOVER LEFT OUT
     *
     * Its approve and reject buttons sat in a table row with nothing beside them
     * about who else was away. Approving leave without seeing that is how a team
     * ends up with nobody in on a Friday, and it is the one piece of information
     * the decision actually turns on.
     *
     * Pending requests are included as well as approved ones, because two people
     * asking for the same week is exactly the clash worth catching before either
     * is granted.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @param  array<string, mixed>  $request
     * @return Collection<int, array<string, mixed>>
     */
    public static function clashesWith(array $request): Collection
    {
        return self::all()
            ->where('id', '!=', $request['id'])
            ->whereIn('status', [LeavePresenter::APPROVED, LeavePresenter::PENDING])
            ->filter(fn (array $other) => LeavePresenter::overlaps($request, $other))
            ->sortBy('from')
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $requests
     * @return array<string, int>
     */
    public static function stats(Collection $requests): array
    {
        return [
            'total' => $requests->count(),
            'pending' => $requests->where('status', LeavePresenter::PENDING)->count(),
            'approved' => $requests->where('status', LeavePresenter::APPROVED)->count(),
            'rejected' => $requests->where('status', LeavePresenter::REJECTED)->count(),
            'cancelled' => $requests->where('status', LeavePresenter::CANCELLED)->count(),
        ];
    }

    /**
     * Who is off today and over the next fortnight — the rail on the managing
     * page, and the thing somebody actually opens this module to check.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function upcomingAbsences(int $days = 14): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        $window = [
            'from' => Carbon::today()->toDateString(),
            'to' => Carbon::today()->addDays($days)->toDateString(),
        ];

        return self::all()
            ->where('status', LeavePresenter::APPROVED)
            ->filter(fn (array $r) => LeavePresenter::overlaps($r, $window))
            ->sortBy('from')
            ->values();
    }
}
