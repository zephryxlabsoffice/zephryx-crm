<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Rbac\Rbac;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Attendance — the clock, and the correction.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHAT THIS FILE IS REALLY CHECKING
 *
 * Four rules from §12, in order of how badly each one fails if it is wrong:
 *
 *   1. The times come from the server. Nothing posted can set one.
 *   2. Checking in is idempotent per day. A double submit is one record, and a
 *      second check-out does not move the first one's time.
 *   3. No write ever changes a recorded time — not even a rejection.
 *   4. Nobody corrects their own record.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class AttendanceWritesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pinned, because every assertion about a recorded time is an assertion
        // about what the server's clock said.
        Carbon::setTestNow(Carbon::today()->setTime(9, 40));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE CLOCK
       ══════════════════════════════════════════════════════════════════════ */

    public function test_checking_in_records_the_servers_time(): void
    {
        $employee = $this->signInAsEmployee();

        $this->post('/attendance/check-in')->assertRedirect();

        $record = AttendanceRecord::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame(Carbon::today()->toDateString(), $record->date->toDateString());
        $this->assertSame('09:40', Carbon::parse($record->check_in)->format('H:i'));
        $this->assertNull($record->check_out);
    }

    public function test_a_posted_time_is_ignored_entirely(): void
    {
        // A form field is something anyone can type into. The route takes none,
        // so sending one changes nothing at all.
        $employee = $this->signInAsEmployee();

        $this->post('/attendance/check-in', [
            'check_in' => '06:00',
            'date' => Carbon::today()->subWeek()->toDateString(),
            'employee_id' => 999,
        ])->assertRedirect();

        $record = AttendanceRecord::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame('09:40', Carbon::parse($record->check_in)->format('H:i'));
        $this->assertSame(Carbon::today()->toDateString(), $record->date->toDateString());
    }

    public function test_checking_in_twice_makes_one_record_and_does_not_move_the_time(): void
    {
        $employee = $this->signInAsEmployee();

        $this->post('/attendance/check-in');

        Carbon::setTestNow(Carbon::today()->setTime(11, 15));
        $this->post('/attendance/check-in')->assertRedirect();

        $records = AttendanceRecord::where('employee_id', $employee->id)->get();

        $this->assertCount(1, $records);
        $this->assertSame('09:40', Carbon::parse($records->first()->check_in)->format('H:i'));
    }

    public function test_checking_out_closes_the_day_and_a_second_one_does_not_move_it(): void
    {
        $employee = $this->signInAsEmployee();

        $this->post('/attendance/check-in');

        Carbon::setTestNow(Carbon::today()->setTime(18, 30));
        $this->post('/attendance/check-out')->assertRedirect();

        Carbon::setTestNow(Carbon::today()->setTime(19, 45));
        $this->post('/attendance/check-out')->assertRedirect();

        $record = AttendanceRecord::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame('18:30', Carbon::parse($record->check_out)->format('H:i'));
    }

    public function test_checking_out_of_a_day_never_checked_into_creates_nothing(): void
    {
        $employee = $this->signInAsEmployee();

        $this->post('/attendance/check-out')->assertRedirect();

        $this->assertSame(0, AttendanceRecord::where('employee_id', $employee->id)->count());
    }

    public function test_the_clock_is_audited(): void
    {
        $this->signInAsEmployee();

        $this->post('/attendance/check-in');
        Carbon::setTestNow(Carbon::today()->setTime(18, 0));
        $this->post('/attendance/check-out');

        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::ATTENDANCE_CHECKED_IN)->count());
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::ATTENDANCE_CHECKED_OUT)->count());
    }

    public function test_somebody_with_no_employment_record_cannot_clock_in(): void
    {
        // A Mentor holds no Employee base at all (§2.1), so there is no
        // attendance for them to record.
        $this->signInAsMentor();

        $this->post('/attendance/check-in')->assertForbidden();
        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_a_freelancer_cannot_clock_in_or_out(): void
    {
        // Paid against work, not time (decided 2026-09-11): no attendance for
        // them at all, even though they DO have an employment record — the
        // rule a Mentor's missing record cannot express on its own.
        $employee = $this->signInAsEmployee();
        $employee->update(['employment_type' => Employee::FREELANCE]);

        $this->post('/attendance/check-in')->assertForbidden();
        $this->assertSame(0, AttendanceRecord::count());

        // Nor can they close a day that somehow exists — belt and braces,
        // since the same gate protects both routes.
        AttendanceRecord::create([
            'employee_id' => $employee->id,
            'date' => Carbon::today(),
            'check_in' => '09:00',
        ]);

        $this->post('/attendance/check-out')->assertForbidden();
        $this->assertNull(AttendanceRecord::first()->check_out);
    }

    /* ══════════════════════════════════════════════════════════════════════
       REJECTION — A CORRECTION, NOT A GATE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_rejecting_needs_the_permission(): void
    {
        $subject = $this->anEmployee('EMP810', 'Their Person');
        $record = $this->aRecord($subject);

        $this->signInAsEmployee();

        $this->post('/attendance/'.$this->reference($record).'/reject', ['reason' => 'A perfectly good reason.'])
            ->assertForbidden();

        $this->assertNull($record->fresh()->rejected_at);
    }

    public function test_hr_may_reject_with_a_reason_and_the_times_are_untouched(): void
    {
        $subject = $this->anEmployee('EMP811', 'Their Person');
        $record = $this->aRecord($subject);

        $this->signInAsEmployee(['employee', 'hr'], 'EMP812');

        $this->post('/attendance/'.$this->reference($record).'/reject', [
            'reason' => 'Duplicate — the reception tablet recorded the same day under a shared login.',
        ])->assertRedirect();

        $record->refresh();

        $this->assertNotNull($record->rejected_at);
        $this->assertStringContainsString('Duplicate', $record->rejection_reason);
        // Rejecting is not editing. The record still says what it recorded.
        $this->assertSame('09:15', Carbon::parse($record->check_in)->format('H:i'));
        $this->assertSame('18:20', Carbon::parse($record->check_out)->format('H:i'));

        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::ATTENDANCE_REJECTED)->count());
    }

    public function test_a_rejection_with_no_reason_is_refused(): void
    {
        // "Rejected" with no explanation is the version somebody has to come and
        // ask about, and it is their attendance record.
        $subject = $this->anEmployee('EMP813', 'Their Person');
        $record = $this->aRecord($subject);

        $this->signInAsEmployee(['employee', 'hr'], 'EMP814');

        $this->post('/attendance/'.$this->reference($record).'/reject', ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertNull($record->fresh()->rejected_at);
    }

    public function test_nobody_rejects_their_own_record(): void
    {
        // The same rule, for the same reason, as nobody approving their own
        // leave: a correction somebody can apply to themselves is not a control.
        $me = $this->signInAsEmployee(['employee', 'hr']);
        $record = $this->aRecord($me);

        $this->post('/attendance/'.$this->reference($record).'/reject', [
            'reason' => 'I would rather this day did not count.',
        ])->assertSessionHasErrors('reason');

        $this->assertNull($record->fresh()->rejected_at);
    }

    public function test_a_rejection_can_be_withdrawn_and_both_acts_stay_on_the_log(): void
    {
        /*
         * A rejection made in error has to be reversible, or the correction
         * mechanism needs a correction mechanism. The log keeps saying what was
         * once claimed about the day, even after the claim was withdrawn.
         */
        $subject = $this->anEmployee('EMP815', 'Their Person');
        $record = $this->aRecord($subject, [
            'rejected_at' => Carbon::now()->subDay(),
            'rejection_reason' => 'Recorded before the office opened.',
        ]);

        $this->signInAsEmployee(['employee', 'hr'], 'EMP816');

        $this->post('/attendance/'.$this->reference($record).'/restore')->assertRedirect();

        $record->refresh();

        $this->assertNull($record->rejected_at);
        $this->assertNull($record->rejection_reason);

        $entry = DB::table('audit_log')->where('action', AuditLog::ATTENDANCE_RESTORED)->first();

        $this->assertNotNull($entry);
        $this->assertStringContainsString('Recorded before the office opened', (string) $entry->before_json);
    }

    public function test_rejecting_the_same_record_twice_writes_one_entry(): void
    {
        $subject = $this->anEmployee('EMP817', 'Their Person');
        $record = $this->aRecord($subject);

        $this->signInAsEmployee(['employee', 'hr'], 'EMP818');

        $reference = $this->reference($record);

        $this->post('/attendance/'.$reference.'/reject', ['reason' => 'A perfectly good reason.']);
        $this->post('/attendance/'.$reference.'/reject', ['reason' => 'A second, different reason.']);

        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::ATTENDANCE_REJECTED)->count());
        $this->assertStringContainsString('perfectly good', $record->fresh()->rejection_reason);
    }

    public function test_no_write_deletes_a_record(): void
    {
        // A wrong record is rejected and stays legible.
        $subject = $this->anEmployee('EMP819', 'Their Person');
        $record = $this->aRecord($subject);

        $this->signInAsEmployee(['employee', 'hr'], 'EMP820');

        $this->post('/attendance/'.$this->reference($record).'/reject', ['reason' => 'A perfectly good reason.']);

        $this->assertNotNull($record->fresh());
        $this->assertSame(1, AttendanceRecord::where('employee_id', $subject->id)->count());
    }

    public function test_rejecting_is_its_own_permission(): void
    {
        $this->assertNotNull(Permission::where('permission_key', 'attendance.reject')->first());
        $this->assertTrue((bool) Permission::where('permission_key', 'attendance.reject')->value('is_sensitive'));
    }

    /* ══════════════════════════════════════════════════════════════════════
       HELPERS
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * @param  list<string>  $roles
     */
    protected function signInAsEmployee(array $roles = ['employee'], string $staffId = 'EMP700'): Employee
    {
        $user = User::factory()->create([
            'user_id' => $staffId,
            'account_type' => 'staff',
            'staff_kind' => 'employee',
            'status' => 'active',
        ]);

        $user->roles()->sync(Role::whereIn('role_key', $roles)->pluck('id'));

        app(Rbac::class)->forget($user);
        $this->actingAs($user);

        return Employee::create(['user_id' => $user->id, 'joined_on' => Carbon::now()->subYear()]);
    }

    protected function anEmployee(string $staffId, string $name): Employee
    {
        $user = User::factory()->create([
            'user_id' => $staffId,
            'name' => $name,
            'account_type' => 'staff',
            'staff_kind' => 'employee',
            'status' => 'active',
        ]);

        return Employee::create(['user_id' => $user->id, 'joined_on' => Carbon::now()->subYear()]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function aRecord(Employee $employee, array $attributes = []): AttendanceRecord
    {
        return AttendanceRecord::create($attributes + [
            'employee_id' => $employee->id,
            'date' => Carbon::today()->subDays(2),
            'check_in' => '09:15',
            'check_out' => '18:20',
        ]);
    }

    protected function reference(AttendanceRecord $record): string
    {
        return $record->fresh('employee.user')->reference();
    }
}
