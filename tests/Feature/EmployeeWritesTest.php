<?php

namespace Tests\Feature;

use App\Mail\AccountInviteMail;
use App\Models\Employee;
use App\Models\MasterDataItem;
use App\Models\User;
use App\Support\Audit\AuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Employees — creating, editing and closing a record.
 *
 * The first module with real writes, so this file is also where the shape of
 * every later one is settled: the permission is on the route, the act is
 * audited, and nothing destructive exists.
 */
class EmployeeWritesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE GUARD IS ON THE ROUTE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_somebody_without_the_permission_cannot_reach_the_form(): void
    {
        // An Employee holds the base and nothing else — their own attendance,
        // leave and payslips. Adding a colleague is not part of that.
        $this->signInAsStaff(['employee']);

        $this->get('/employees/create')->assertForbidden();
        $this->post('/employees', $this->validPayload())->assertForbidden();
    }

    public function test_the_three_write_permissions_are_separate(): void
    {
        /*
         * Correcting a designation, hiring somebody and closing their record are
         * different sizes of act. A role granted the first is not thereby
         * granted the third.
         */
        $employee = $this->anEmployee();

        $this->signInAsStaff(['employee']);
        $this->grant('employees.view', 'employees.edit');

        $this->get('/employees/'.$employee->user->user_id.'/edit')->assertOk();
        $this->get('/employees/create')->assertForbidden();
        $this->post('/employees/'.$employee->user->user_id.'/status', ['status' => 'inactive'])
            ->assertForbidden();
    }

    public function test_hr_may_do_all_three(): void
    {
        $employee = $this->anEmployee();
        $this->signInAsStaff(['employee', 'hr']);

        $this->get('/employees/create')->assertOk();
        $this->get('/employees/'.$employee->user->user_id.'/edit')->assertOk();
        $this->get('/employees/'.$employee->user->user_id)->assertOk();
    }

    /* ══════════════════════════════════════════════════════════════════════
       CREATING SOMEBODY CREATES AN ACCOUNT
       ══════════════════════════════════════════════════════════════════════ */

    public function test_adding_an_employee_creates_the_account_and_the_employment_together(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->validPayload())->assertRedirect();

        $user = User::where('email', 'new.person@example.test')->first();

        $this->assertNotNull($user);
        $this->assertSame('staff', $user->account_type);
        // The Employee base comes from this column, not from a role, so that no
        // role edit can take somebody's own records away from them.
        $this->assertSame('employee', $user->staff_kind);
        $this->assertSame('active', $user->status);
        $this->assertNotNull($user->employeeRecord ?? Employee::where('user_id', $user->id)->first());
    }

    public function test_nobody_types_somebody_elses_password(): void
    {
        /*
         * The account gets a random password nobody sees, and the person sets
         * their own through a single-use link. A password HR chooses is one HR
         * knows, and "temporary" passwords get shared over chat and never
         * changed.
         */
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->validPayload());

        Mail::assertSent(AccountInviteMail::class, function (AccountInviteMail $mail) {
            return $mail->hasTo('new.person@example.test')
                && str_contains($mail->url, '/reset-password/');
        });
    }

    public function test_the_staff_id_follows_the_scheme_and_its_own_series(): void
    {
        /*
         * `ZEPH` + the year + the engagement digit + a number that restarts
         * each year (decided 2026-09-11). The demo fixtures are seeded first on
         * purpose: they are `EMP0xx`, a different series, and must not push
         * this year's first full-time number off 001. See App\Support\StaffId.
         */
        $this->seedDemoWorkforce();
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->validPayload());

        $expected = 'ZEPH'.Carbon::now()->format('y').'1001';

        $this->assertNotNull(User::where('user_id', $expected)->first());
    }

    public function test_the_engagement_digit_matches_the_engagement_recorded(): void
    {
        // The digit and the column are written in the same act, and this is the
        // only place they can disagree. An intern is 2.
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->validPayload([
            'employment_type' => Employee::INTERN,
        ]))->assertRedirect();

        $user = User::where('email', 'new.person@example.test')->firstOrFail();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('ZEPH'.Carbon::now()->format('y').'2001', $user->user_id);
        $this->assertSame(Employee::INTERN, $employee->employment_type);
    }

    public function test_an_engagement_type_nobody_offers_is_refused(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->validPayload(['employment_type' => 'contractor']))
            ->assertSessionHasErrors('employment_type');

        $this->assertSame(0, Employee::count());
    }

    public function test_the_engagement_type_cannot_be_changed_by_editing(): void
    {
        /*
         * Converting an intern issues a new identifier, closes the old record
         * and restarts the leave balance. Through the edit form none of that
         * would happen: the column would say full-time behind a `ZEPH262001`,
         * with a year of intern leave behind it and nothing recording when it
         * changed.
         */
        $employee = $this->anEmployee();
        $employee->update(['employment_type' => Employee::INTERN]);

        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees/'.$employee->user->user_id, $this->validPayload([
            'name' => $employee->user->name,
            'email' => $employee->user->email,
            'employment_type' => Employee::FULL_TIME,
        ]))->assertRedirect();

        $this->assertSame(Employee::INTERN, $employee->fresh()->employment_type);
    }

    public function test_an_email_already_in_use_is_refused(): void
    {
        // The email is the sign-in identifier, so two accounts sharing one would
        // be two accounts a single credential could mean.
        $existing = $this->anEmployee();
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->validPayload(['email' => $existing->user->email]))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, Employee::count());
    }

    public function test_a_department_from_the_wrong_list_is_refused(): void
    {
        /*
         * Both dropdowns are ids from one table. Without the list check, a
         * crafted request could set somebody's department to a leave type — and
         * every page reading it would render "Sick Leave" as a department.
         */
        $this->signInAsStaff(['employee', 'hr']);

        $leaveType = MasterDataItem::inList(MasterDataItem::LEAVE_TYPES)->firstOrFail();

        $this->post('/employees', $this->validPayload(['department_id' => $leaveType->id]))
            ->assertSessionHasErrors('department_id');
    }

    public function test_an_impossible_birthday_is_refused(): void
    {
        // This feeds the announcements board, so a wrong one is published to
        // everybody.
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->validPayload([
            'date_of_birth' => Carbon::now()->addYear()->toDateString(),
        ]))->assertSessionHasErrors('date_of_birth');
    }

    public function test_creating_is_audited(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->validPayload());

        $entry = DB::table('audit_log')->where('action', AuditLog::EMPLOYEE_CREATED)->first();

        $this->assertNotNull($entry);
        $this->assertSame('employee', $entry->entity_type);
        $this->assertStringContainsString('New Person', (string) $entry->after_json);
    }

    /* ══════════════════════════════════════════════════════════════════════
       EDITING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_editing_records_what_it_was_before(): void
    {
        // "What changed" is the question somebody asks of an audit log, and an
        // entry with only the new value cannot answer it.
        $employee = $this->anEmployee();
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees/'.$employee->user->user_id, $this->validPayload([
            'name' => 'Renamed Person',
            'email' => $employee->user->email,
        ]))->assertRedirect();

        $entry = DB::table('audit_log')->where('action', AuditLog::EMPLOYEE_UPDATED)->first();

        $this->assertNotNull($entry);
        $this->assertStringContainsString('Original Person', (string) $entry->before_json);
        $this->assertStringContainsString('Renamed Person', (string) $entry->after_json);
        $this->assertSame('Renamed Person', $employee->user->fresh()->name);
    }

    public function test_clearing_the_milestone_opt_in_actually_clears_it(): void
    {
        /*
         * An unchecked box sends nothing, so without the hidden field beside it
         * "turn this off" and "leave it alone" arrive identically — and the
         * person's birthday keeps being announced after they asked for it not
         * to be.
         */
        $employee = $this->anEmployee(['announce_milestones' => true]);
        $this->signInAsStaff(['employee', 'hr']);

        $payload = $this->validPayload([
            'name' => $employee->user->name,
            'email' => $employee->user->email,
            'announce_milestones' => '0',
        ]);

        $this->post('/employees/'.$employee->user->user_id, $payload);

        $this->assertFalse($employee->fresh()->announce_milestones);
    }

    /* ══════════════════════════════════════════════════════════════════════
       CLOSING A RECORD — AND THERE IS NO DELETE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_closing_a_record_stops_the_account_and_keeps_everything_else(): void
    {
        $employee = $this->anEmployee();
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees/'.$employee->user->user_id.'/status', ['status' => 'inactive'])
            ->assertRedirect();

        $this->assertSame('inactive', $employee->user->fresh()->status);
        // The row is still there. Attendance, payroll and the audit log all
        // point at it, which is why there is no delete anywhere in this module.
        $this->assertNotNull($employee->fresh());
    }

    public function test_nobody_closes_their_own_record(): void
    {
        // The same rule as nobody approving their own leave: a control somebody
        // can apply to themselves is not a control.
        $me = $this->signInAsStaff(['employee', 'hr']);
        Employee::create(['user_id' => $me->id, 'joined_on' => Carbon::now()->subYear()]);

        $this->post('/employees/'.$me->user_id.'/status', ['status' => 'inactive'])
            ->assertSessionHasErrors('status');

        $this->assertSame('active', $me->fresh()->status);
    }

    public function test_somebody_cannot_close_the_record_of_a_person_who_outranks_them(): void
    {
        /*
         * Rank, not role (§2.5). The permission says what somebody may do; rank
         * in the `people` domain says who they may do it to. A Manager holding
         * employees.deactivate may not close HR's record.
         */
        $hr = User::factory()->create([
            'user_id' => 'EMP900', 'account_type' => 'staff',
            'staff_kind' => 'employee', 'status' => 'active',
        ]);
        $hr->roles()->sync(\App\Models\Role::where('role_key', 'hr')->pluck('id'));
        Employee::create(['user_id' => $hr->id, 'joined_on' => Carbon::now()->subYear()]);

        $this->signInAsStaff(['employee', 'manager']);
        $this->grant('employees.deactivate');

        $this->post('/employees/EMP900/status', ['status' => 'inactive'])->assertForbidden();

        $this->assertSame('active', $hr->fresh()->status);
    }

    public function test_there_is_no_delete_route(): void
    {
        $employee = $this->anEmployee();
        $this->signInAsStaff(['employee', 'hr']);

        $this->delete('/employees/'.$employee->user->user_id)->assertStatus(405);
    }

    /* ══════════════════════════════════════════════════════════════════════
       HELPERS
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validPayload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'New Person',
            'email' => 'new.person@example.test',
            'department_id' => MasterDataItem::inList(MasterDataItem::DEPARTMENTS)->value('id'),
            'designation_id' => MasterDataItem::inList(MasterDataItem::DESIGNATIONS)->value('id'),
            'employment_type' => Employee::FULL_TIME,
            'joined_on' => Carbon::now()->subMonth()->toDateString(),
            'date_of_birth' => '1995-04-11',
            'announce_milestones' => '1',
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function anEmployee(array $attributes = []): Employee
    {
        $user = User::factory()->create([
            'user_id' => 'EMP500',
            'name' => 'Original Person',
            'email' => 'original@example.test',
            'account_type' => 'staff',
            'staff_kind' => 'employee',
            'status' => 'active',
        ]);

        return Employee::create($attributes + [
            'user_id' => $user->id,
            'joined_on' => Carbon::now()->subYear(),
        ]);
    }

    /**
     * Give the signed-in account extra permissions without inventing a role.
     */
    protected function grant(string ...$permissions): void
    {
        $role = \App\Models\Role::firstOrCreate(
            ['role_key' => 'test_grant'],
            ['role_name' => 'Test grant', 'is_active' => true],
        );

        $role->permissions()->syncWithoutDetaching(
            \App\Models\Permission::whereIn('permission_key', $permissions)->pluck('id')
        );

        auth()->user()->roles()->syncWithoutDetaching([$role->id]);
        app(\App\Support\Rbac\Rbac::class)->forget(auth()->user());
    }
}
