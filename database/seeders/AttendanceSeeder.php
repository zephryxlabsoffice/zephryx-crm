<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Support\Demo\DemoAttendance;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The demo attendance, as real rows. Local + debug only.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHY THE FIXTURE IS GENERATED AND THIS ONLY COPIES IT
 *
 * DemoAttendance builds days from a seed derived from the person and the date,
 * with the days worth looking at — the absence, the forgotten check-out, the
 * duplicate somebody rejected — written out by hand. Every one of those states
 * is on screen on purpose. Regenerating that logic here would be a second
 * implementation of it that drifts; this walks what it already produces and
 * writes it down.
 *
 * Bounded to ten weeks rather than the fixture's hundred days: it fills the
 * current month's calendar and the one before it, which is what the pages
 * actually read, and it keeps the test suite's seed to a few hundred rows
 * instead of a few thousand.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class AttendanceSeeder extends Seeder
{
    protected const DAYS = 70;

    public function run(): void
    {
        if (! (app()->environment('local') && config('app.debug'))) {
            return;
        }

        $employees = Employee::query()
            ->with('user')
            ->get()
            ->keyBy(fn (Employee $e) => (string) $e->user?->user_id);

        $rejecters = $employees;
        $now = now();

        foreach ($employees as $staffId => $employee) {
            $rows = [];

            foreach (DemoAttendance::forEmployee($staffId, self::DAYS) as $record) {
                $rows[] = [
                    'employee_id' => $employee->id,
                    'date' => $record['date'],
                    'check_in' => $record['check_in'],
                    'check_out' => $record['check_out'],
                    'rejected_at' => $record['rejected_at'],
                    'rejected_by' => $record['rejected_by']
                        ? $rejecters->get($record['rejected_by'])?->id
                        : null,
                    'rejection_reason' => $record['rejection_reason'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows === []) {
                continue;
            }

            /*
             * One statement per person, and upsert rather than insert: the
             * unique key on (employee, date) is the whole idempotency of this
             * module, and a seeder that fell over on a second run would be the
             * first thing to argue with it.
             */
            DB::table('attendance_records')->upsert(
                $rows,
                ['employee_id', 'date'],
                ['check_in', 'check_out', 'rejected_at', 'rejected_by', 'rejection_reason', 'updated_at'],
            );
        }

        // Note what this never writes: an "absent" row. An absence is the
        // absence of a record — see the migration.
    }
}
