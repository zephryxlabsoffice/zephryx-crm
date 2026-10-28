<?php

namespace Tests\Feature;

use App\Mail\OneTimeCodeMail;
use App\Mail\PasswordResetMail;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Support\Auth\SecurityLock;
use App\Support\Realm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * "This wasn't me" on every account email, the On hold status it sets, and
 * the System Administrator's Accounts pages (owner, 2026-10-28).
 */
class SecurityHoldTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_the_sign_in_code_and_a_requested_reset_carry_the_red_button(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['identifier' => $user->email, 'password' => 'password']);
        Mail::assertSent(OneTimeCodeMail::class, fn (OneTimeCodeMail $m) => str_contains($m->notMeUrl, 'reason=sign-in'));

        $this->post('/forgot-password', ['identifier' => $user->email]);
        Mail::assertSent(PasswordResetMail::class, fn (PasswordResetMail $m) => str_contains($m->notMeUrl, 'reason=reset'));

        $this->assertStringContainsString("This wasn't me", (new OneTimeCodeMail('123456', 'A', null, 'https://x'))->render());
    }

    public function test_a_reset_an_admin_sends_has_no_red_button(): void
    {
        $employee = $this->staff('EMP801');
        $this->signInAsAdmin();

        $this->post('/admin/accounts/EMP801/force-password-reset');

        Mail::assertSent(PasswordResetMail::class, fn (PasswordResetMail $m) => $m->hasTo($employee->email) && $m->notMeUrl === '');
    }

    public function test_the_red_button_puts_the_account_on_hold_not_suspended(): void
    {
        $user = $this->staff('EMP802');

        $this->post(SecurityLock::url($user, (string) $user->email, SecurityLock::SIGN_IN))
            ->assertOk()->assertSee('on hold');

        $this->assertSame(SecurityLock::STATUS, $user->fresh()->status);
    }

    public function test_an_account_on_hold_lands_on_its_own_page_with_contact_admin(): void
    {
        $user = $this->staff('EMP803');
        $user->forceFill(['status' => SecurityLock::STATUS])->save();

        $this->post('/login', ['identifier' => 'EMP803', 'password' => 'password'])
            ->assertRedirect(route('account.on-hold'));

        $this->get('/account/on-hold')->assertOk()
            ->assertSee('on hold for security')
            ->assertSee('Contact Admin')
            ->assertSee('Back to sign in');
    }

    public function test_restoring_from_hold_emails_a_password_link_without_the_red_button(): void
    {
        $user = $this->staff('EMP804');
        $user->forceFill(['status' => SecurityLock::STATUS])->save();
        $this->signInAsAdmin();

        $this->get('/admin/accounts/EMP804')->assertSee('On hold for security');

        $this->post('/admin/accounts/EMP804/status', ['status' => 'active'])
            ->assertSessionHas('status', fn (string $s) => str_contains($s, 'emailed a link'));

        $this->assertSame('active', $user->fresh()->status);
        Mail::assertSent(PasswordResetMail::class, fn (PasswordResetMail $m) => $m->hasTo($user->email) && $m->notMeUrl === '');
    }

    public function test_hr_cannot_lift_a_security_hold(): void
    {
        $user = $this->staff('EMP805');
        $user->forceFill(['status' => SecurityLock::STATUS])->save();
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees/EMP805/status', ['status' => 'active'])->assertSessionHasErrors('status');
        $this->assertSame(SecurityLock::STATUS, $user->fresh()->status);
    }

    /* ══════════════════════════════════════════════════════════════════════
       ACCOUNTS FOR A SYSTEM ADMINISTRATOR
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_system_administrator_has_accounts_in_the_sidebar_and_can_restore_a_hold(): void
    {
        $held = $this->staff('EMP806');
        $held->forceFill(['status' => SecurityLock::STATUS])->save();

        $this->signInAsStaff(['employee', 'system_admin']);

        $this->get('/dashboard')->assertSee(route('staff-accounts.index'));
        $this->get('/accounts')->assertOk()->assertSee('EMP806');
        $this->get('/accounts/EMP806')->assertOk()->assertSee(route('staff-accounts.status', 'EMP806'));

        $this->post('/accounts/EMP806/status', ['status' => 'active'])
            ->assertRedirect(route('staff-accounts.show', 'EMP806'));

        $this->assertSame('active', $held->fresh()->status);
        Mail::assertSent(PasswordResetMail::class);
    }

    public function test_nobody_else_gets_the_accounts_pages(): void
    {
        $this->signInAsStaff(['employee', 'hr', 'manager']);

        $this->get('/accounts')->assertForbidden();
    }

    public function test_a_system_administrator_cannot_act_on_themselves_or_another_system_administrator(): void
    {
        $other = $this->staff('EMP807', ['employee', 'system_admin']);
        $me = $this->signInAsStaff(['employee', 'system_admin']);

        $this->get('/accounts/EMP807')->assertForbidden();
        $this->get('/accounts/'.$me->user_id)->assertForbidden();
        $this->assertSame('active', $other->fresh()->status);
    }

    public function test_only_the_owner_hands_out_the_ceo_and_system_administrator_roles(): void
    {
        $user = $this->staff('EMP808');
        $this->signInAsStaff(['employee', 'system_admin']);

        $this->post('/accounts/EMP808/roles', ['roles' => ['employee', 'ceo']])->assertSessionHasErrors('roles');
        $this->assertFalse($user->fresh()->roles->pluck('role_key')->contains('ceo'));

        $this->post('/accounts/EMP808/roles', ['roles' => ['employee', 'manager']])->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->roles->pluck('role_key')->contains('manager'));
    }

    /**
     * @param  list<string>  $roles
     */
    protected function staff(string $staffId, array $roles = ['employee']): User
    {
        $user = User::factory()->create([
            'user_id' => $staffId,
            'account_type' => Realm::STAFF,
            'staff_kind' => 'employee',
            'status' => 'active',
        ]);
        $user->roles()->sync(Role::whereIn('role_key', $roles)->pluck('id'));
        Employee::create(['user_id' => $user->id, 'joined_on' => Carbon::now()->subYear()]);

        return $user;
    }
}
