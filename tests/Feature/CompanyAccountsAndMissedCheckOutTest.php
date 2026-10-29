<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Role;
use App\Models\User;
use App\Support\AttendancePolicy;
use App\Support\AttendancePresenter;
use App\Support\Audit\AuditLog;
use App\Support\Rbac\Rbac;
use App\Support\Realm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * v1.2.1 (2026-10-30), the owner's two bug reports.
 *
 *  1. A company account (HR Desk: roles, no employment) showed "My Attendance",
 *     "My Leave" and the rest, and could not act on an employee of equal rank.
 *     It is the company's own desk: no "My" pages, and it outranks every
 *     employee. A leave it decides names it.
 *  2. A day somebody forgot to check out of was rejected for good. HR can now
 *     mark it present: "Present (warning)", counted, no hours, times untouched.
 */
class CompanyAccountsAndMissedCheckOutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::today()->setTime(9, 40));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /* ══════════════════════════════════════════════════════════════════════
       1. COMPANY ACCOUNTS
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_company_account_sees_no_my_buttons(): void
    {
        $this->signInAsCompanyAccount(['hr', 'manager']);

        foreach (['/attendance' => 'My Attendance', '/leave' => 'My Leave', '/salary' => 'My Salary',
            '/tasks' => 'My Tasks', '/teams' => 'My Teams', '/projects' => 'My Projects'] as $url => $label) {
            $response = $this->get($url);

            if ($response->status() !== 200) {
                continue;
            }

            $this->assertStringNotContainsString(route(str_replace('/', '', $url).'.mine'), $response->getContent(), $url);
        }

        $this->get('/leave')->assertOk()->assertDontSee('My Leave');
    }

    public function test_a_company_account_has_the_dashboard(): void
    {
        $this->signInAsCompanyAccount(['hr']);

        $this->get('/dashboard')->assertOk();
        $this->get('/employees')->assertOk()->assertSee('href="'.route('dashboard').'"', false);
    }

    public function test_an_employee_still_sees_them(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->get('/leave')->assertOk()->assertSee('My Leave');
        $this->get('/attendance')->assertOk()->assertSee(route('attendance.mine'), false);
    }

    public function test_a_company_account_outranks_an_employee_of_the_same_role(): void
    {
        $desk = $this->signInAsCompanyAccount(['hr']);
        $hrPerson = $this->anEmployee('EMP901', 'Only HR', ['employee', 'hr'])->user;

        $rbac = app(Rbac::class);

        $this->assertTrue($rbac->outranks($desk, $hrPerson, 'people'));
        // Two employees of the same rank still cannot act on each other.
        $this->assertFalse($rbac->outranks($hrPerson, $this->anEmployee('EMP902', 'Other HR', ['employee', 'hr'])->user, 'people'));
        // And an employee never outranks the desk.
        $this->assertFalse($rbac->outranks($hrPerson, $desk, 'people'));
    }

    public function test_a_mentor_is_not_a_company_account(): void
    {
        $mentor = User::factory()->create([
            'user_id' => 'ZEPH4901',
            'account_type' => Realm::STAFF,
            'staff_kind' => null,
            'status' => 'active',
        ]);
        $mentor->roles()->sync(Role::where('role_key', 'mentor')->pluck('id'));

        $this->assertFalse($mentor->fresh()->isCompanyAccount());
        $this->assertFalse(app(Rbac::class)->outranks($mentor->fresh(), $this->anEmployee('EMP903', 'Intern', ['employee'])->user, 'people'));
    }

    public function test_the_company_account_approves_the_only_hrs_leave_and_is_named(): void
    {
        $hrPerson = $this->anEmployee('EMP904', 'Only HR', ['employee', 'hr']);
        $leave = LeaveRequest::create([
            'reference' => 'LV-2026-0904',
            'employee_id' => $hrPerson->id,
            'type' => 'casual',
            'from_date' => Carbon::today()->addDays(3),
            'to_date' => Carbon::today()->addDays(3),
            'days' => 1,
            'reason' => 'A family function out of town.',
            'status' => 'pending',
            'applied_at' => Carbon::now(),
        ]);

        $desk = $this->signInAsCompanyAccount(['hr'], 'HR Desk');

        $this->post('/leave/'.$leave->reference.'/approve')->assertRedirect();

        $leave->refresh();
        $this->assertSame('approved', $leave->status);
        $this->assertSame($desk->id, $leave->decided_by_user);

        $this->get('/leave/'.$leave->reference)->assertOk()->assertSee('HR Desk')->assertDontSee('Nobody yet');
    }

    /* ══════════════════════════════════════════════════════════════════════
       2. A FORGOTTEN CHECK-OUT, MARKED PRESENT
       ══════════════════════════════════════════════════════════════════════ */

    public function test_hr_marks_a_never_closed_day_present_with_a_warning(): void
    {
        $subject = $this->anEmployee('EMP911', 'Forgot To Check Out');
        $record = $this->anOpenDay($subject);

        $this->signInAsStaff(['employee', 'hr']);

        $this->get('/attendance/'.$this->reference($record))->assertOk()->assertSee('Mark this day present');

        $this->post('/attendance/'.$this->reference($record).'/mark-present', [
            'present_reason' => 'Was in office all day, confirmed by the team lead.',
        ])->assertRedirect();

        $record->refresh();
        $this->assertNotNull($record->marked_present_at);
        // Not an edit: the check-out is still missing and the check-in unchanged.
        $this->assertNull($record->check_out);
        $this->assertSame('09:15', Carbon::parse($record->check_in)->format('H:i'));

        $evaluated = AttendancePolicy::evaluate($record->date, $record->fresh('markedPresentBy')->toRecordArray());
        $this->assertSame(AttendancePresenter::WARNING, $evaluated['state']);
        $this->assertFalse($evaluated['auto_rejected']);
        $this->assertNull($evaluated['worked_minutes']);

        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::ATTENDANCE_MARKED_PRESENT)->count());

        $this->get('/attendance/'.$this->reference($record))->assertOk()
            ->assertSee('Counted as present, with a warning')
            ->assertSee('confirmed by the team lead')
            ->assertDontSee('Mark this day present');
    }

    public function test_a_warning_day_counts_as_attended(): void
    {
        $subject = $this->anEmployee('EMP912', 'Counted');
        $record = $this->anOpenDay($subject, ['marked_present_at' => Carbon::now()]);

        $summary = AttendancePolicy::monthSummary($record->date, [$record->fresh()->toRecordArray()]);

        $this->assertSame(1, $summary[AttendancePresenter::WARNING]);
        $this->assertSame(1, $summary['attended']);
    }

    public function test_marking_present_needs_a_reason_the_permission_and_someone_else(): void
    {
        $subject = $this->anEmployee('EMP913', 'Someone');
        $record = $this->anOpenDay($subject);

        $this->signInAsStaff(['employee']);
        $this->post('/attendance/'.$this->reference($record).'/mark-present', [
            'present_reason' => 'Was in office all day, confirmed by the team lead.',
        ])->assertForbidden();

        $this->signInAsStaff(['employee', 'hr']);
        $this->post('/attendance/'.$this->reference($record).'/mark-present', ['present_reason' => ''])
            ->assertSessionHasErrors('present_reason');

        $own = $this->anOpenDay($this->signInAsEmployeeWithRecord(['employee', 'hr'], 'EMP914'));
        $this->post('/attendance/'.$this->reference($own).'/mark-present', [
            'present_reason' => 'My own day, which I may not mark.',
        ])->assertSessionHasErrors('present_reason');

        $this->assertNull($record->fresh()->marked_present_at);
        $this->assertNull($own->fresh()->marked_present_at);
    }

    public function test_a_closed_day_cannot_be_marked(): void
    {
        $subject = $this->anEmployee('EMP915', 'Closed Day');
        $record = AttendanceRecord::create([
            'employee_id' => $subject->id,
            'date' => Carbon::today()->subDays(2),
            'check_in' => '09:15',
            'check_out' => '18:20',
        ]);

        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/attendance/'.$this->reference($record).'/mark-present', [
            'present_reason' => 'Was in office all day, confirmed by the team lead.',
        ])->assertRedirect();

        $this->assertNull($record->fresh()->marked_present_at);
    }

    public function test_a_company_account_can_mark_present_and_is_named(): void
    {
        $subject = $this->anEmployee('EMP916', 'Someone Else');
        $record = $this->anOpenDay($subject);

        $this->signInAsCompanyAccount(['hr'], 'HR Desk');

        $this->post('/attendance/'.$this->reference($record).'/mark-present', [
            'present_reason' => 'Was in office all day, confirmed by the team lead.',
        ])->assertRedirect();

        $this->get('/attendance/'.$this->reference($record))->assertOk()->assertSee('marked by HR Desk');
    }

    /* ══════════════════════════════════════════════════════════════════════
       HELPERS
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * @param  list<string>  $roles
     */
    protected function signInAsCompanyAccount(array $roles, string $name = 'Company Desk'): User
    {
        $user = User::factory()->create([
            'user_id' => 'ZEPH'.fake()->unique()->numberBetween(5000, 5999),
            'name' => $name,
            'account_type' => Realm::STAFF,
            'staff_kind' => null,
            'status' => 'active',
        ]);

        $user->roles()->sync(Role::whereIn('role_key', $roles)->pluck('id'));

        app(Rbac::class)->forget($user);
        $this->actingAs($user);

        return $user;
    }

    /**
     * @param  list<string>  $roles
     */
    protected function signInAsEmployeeWithRecord(array $roles, string $staffId): Employee
    {
        $employee = $this->anEmployee($staffId, 'Signed In', $roles);

        app(Rbac::class)->forget($employee->user);
        $this->actingAs($employee->user);

        return $employee;
    }

    /**
     * @param  list<string>  $roles
     */
    protected function anEmployee(string $staffId, string $name, array $roles = ['employee']): Employee
    {
        $user = User::factory()->create([
            'user_id' => $staffId,
            'name' => $name,
            'account_type' => Realm::STAFF,
            'staff_kind' => 'employee',
            'status' => 'active',
        ]);

        $user->roles()->sync(Role::whereIn('role_key', $roles)->pluck('id'));

        return Employee::create(['user_id' => $user->id, 'joined_on' => Carbon::now()->subYear()]);
    }

    /**
     * A day two days back, checked into and never out of: past the window.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function anOpenDay(Employee $employee, array $attributes = []): AttendanceRecord
    {
        return AttendanceRecord::create($attributes + [
            'employee_id' => $employee->id,
            'date' => Carbon::today()->subDays(2),
            'check_in' => '09:15',
            'check_out' => null,
        ]);
    }

    protected function reference(AttendanceRecord $record): string
    {
        return $record->fresh('employee.user')->reference();
    }
}
