<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\User;
use App\Support\Audit\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Deleting an account made by mistake - and only that (2026-10-27).
 * See App\Support\Admin\AccountRemoval.
 */
class AccountRemovalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->signInAsAdmin();
    }

    public function test_an_unused_account_can_be_deleted_and_its_email_reused(): void
    {
        $user = $this->addEmployee('typo@gmal.test');

        $this->get('/admin/accounts/'.$user->user_id)->assertOk()->assertSee('Delete this account');

        $this->post('/admin/accounts/'.$user->user_id.'/delete', ['confirm_email' => 'typo@gmal.test'])
            ->assertRedirect('/admin/accounts');

        $this->assertNull(User::find($user->id));
        $this->assertNull(Employee::where('user_id', $user->id)->first());
        $this->assertSame(0, DB::table('employee_profiles')->where('personal_email', 'typo@gmal.test')->count());
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::ACCOUNT_DELETED)->count());

        // The email is free again.
        $this->addEmployee('typo@gmal.test');
        $this->assertSame(1, User::where('email', 'typo@gmal.test')->count());
    }

    public function test_a_normal_account_can_be_deleted_too(): void
    {
        $this->post('/admin/accounts', [
            'kind' => 'normal', 'name' => 'HR Desk', 'account_email' => 'hr@zephryxlabs.test', 'roles' => ['hr'],
        ]);
        $user = User::where('email', 'hr@zephryxlabs.test')->firstOrFail();

        $this->post('/admin/accounts/'.$user->user_id.'/delete', ['confirm_email' => 'hr@zephryxlabs.test'])
            ->assertRedirect('/admin/accounts');

        $this->assertNull(User::find($user->id));
    }

    public function test_the_email_must_be_typed_back(): void
    {
        $user = $this->addEmployee('typo@gmal.test');

        $this->post('/admin/accounts/'.$user->user_id.'/delete', ['confirm_email' => 'yes'])
            ->assertSessionHasErrors('confirm_email');

        $this->assertNotNull(User::find($user->id));
    }

    public function test_an_account_that_has_signed_in_cannot_be_deleted(): void
    {
        $user = $this->addEmployee('used@gmail.test');
        $user->forceFill(['last_login_at' => now()])->save();

        $this->get('/admin/accounts/'.$user->user_id)->assertOk()->assertDontSee('Delete this account');

        $this->post('/admin/accounts/'.$user->user_id.'/delete', ['confirm_email' => 'used@gmail.test'])
            ->assertRedirect();

        $this->assertNotNull(User::find($user->id));
    }

    public function test_an_account_with_any_record_cannot_be_deleted(): void
    {
        $user = $this->addEmployee('worked@gmail.test');
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        AttendanceRecord::create([
            'employee_id' => $employee->id, 'date' => now()->subDay()->toDateString(), 'check_in' => '09:30',
        ]);

        $this->post('/admin/accounts/'.$user->user_id.'/delete', ['confirm_email' => 'worked@gmail.test'])
            ->assertSessionHas('status', fn (string $s) => str_contains($s, 'Attendance records'));

        $this->assertNotNull(User::find($user->id));
    }

    protected function addEmployee(string $email): User
    {
        $this->post('/admin/accounts', [
            'name' => 'Mistake Person',
            'personal_email' => $email,
            'phone' => '9800000000',
            'employment_type' => Employee::FULL_TIME,
            'joined_on' => now()->subMonth()->toDateString(),
        ])->assertRedirect();

        return User::where('email', $email)->latest('id')->firstOrFail();
    }
}
