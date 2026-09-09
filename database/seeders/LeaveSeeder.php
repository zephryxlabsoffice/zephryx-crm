<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Support\Demo\DemoLeave;
use Illuminate\Database\Seeder;

/**
 * The demo leave requests, as real rows. Local + debug only.
 *
 * Runs after EmployeeSeeder and before AttendanceSeeder is READ — approved
 * leave is what stops a day being drawn as an absence, so a seed without it
 * makes the calendar wrong in exactly the way this module exists to prevent.
 *
 * The fixture's pending requests stay pending, including the viewer's own: the
 * rule that nobody decides their own leave is reviewable on screen because
 * there is a request on screen for them to fail to decide.
 */
class LeaveSeeder extends Seeder
{
    public function run(): void
    {
        if (! (app()->environment('local') && config('app.debug'))) {
            return;
        }

        $employees = Employee::query()
            ->with('user')
            ->get()
            ->keyBy(fn (Employee $e) => (string) $e->user?->user_id);

        foreach (DemoLeave::all() as $row) {
            $employee = $employees->get($row['employee']);

            if ($employee === null) {
                continue;
            }

            LeaveRequest::updateOrCreate(
                ['reference' => $row['id']],
                [
                    'employee_id' => $employee->id,
                    'type' => $row['type'],
                    'from_date' => $row['from_date'],
                    'to_date' => $row['to_date'],
                    'days' => $row['days'],
                    'reason' => $row['reason'],
                    'contact_number' => $row['contact'],
                    'status' => $row['status'],
                    'decided_by' => $row['decided_by'] ? $employees->get($row['decided_by'])?->id : null,
                    // A decided request has a decision time. Taken as the day
                    // after it was applied for rather than "now", so the log
                    // does not read as though a year of decisions happened in
                    // the second the seeder ran.
                    'decided_at' => $row['decided_by'] ? $row['applied_at']->copy()->addDay() : null,
                    'decision_note' => $row['note'],
                    'applied_at' => $row['applied_at'],
                ],
            );
        }
    }
}
