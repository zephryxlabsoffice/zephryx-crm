<?php

namespace Tests\Feature;

use App\Mail\EmailVerifyMail;
use App\Mail\OneTimeCodeMail;
use App\Mail\PasswordResetMail;
use App\Models\Employee;
use App\Models\EmployeeProfile;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Realm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Sign-in by Employee ID, and email verification (owner, 2026-10-28): a
 * password reset proves the address, and a changed personal email has to be
 * proven again.
 */
class EmailVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_the_employee_id_signs_in_typed_in_any_case(): void
    {
        User::factory()->create(['user_id' => 'ZEPH261001']);

        $this->post('/login', ['identifier' => 'zeph261001', 'password' => 'password'])
            ->assertRedirect(route('login.verify'));
    }

    public function test_choosing_a_password_from_the_link_verifies_the_email(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->post('/forgot-password', ['identifier' => $user->email]);

        $token = null;
        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use (&$token) {
            $token = str($mail->url)->afterLast('/')->value();

            return true;
        });

        $this->post('/reset-password', [
            'token' => $token,
            'identifier' => $user->email,
            'password' => 'a-brand-new-passphrase',
            'password_confirmation' => 'a-brand-new-passphrase',
        ])->assertRedirect(route('login'));

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_signing_in_with_the_emailed_code_verifies_the_email(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->post('/login', ['identifier' => $user->email, 'password' => 'password']);

        $code = null;
        Mail::assertSent(OneTimeCodeMail::class, function (OneTimeCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $this->post('/login/verify', ['code' => str_split($code)]);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_a_changed_personal_email_is_unverified_until_its_link_is_followed(): void
    {
        [$user, $profile] = $this->staffWithWorkEmail();

        $profile->update(['personal_email' => 'new.personal@example.com']);

        $this->assertNull($profile->fresh()->personal_email_verified_at);
        // The work email is not touched.
        $this->assertNotNull($user->fresh()->email_verified_at);

        $url = $this->linkSentTo('new.personal@example.com');

        $this->get($url)->assertRedirect(route('login'));

        $this->assertNotNull($profile->fresh()->personal_email_verified_at);
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::EMAIL_VERIFIED)->count());
    }

    public function test_when_the_personal_email_is_the_sign_in_one_both_move_and_one_link_proves_both(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com', 'account_type' => Realm::STAFF, 'staff_kind' => 'employee']);
        $employee = Employee::create(['user_id' => $user->id, 'joined_on' => Carbon::now()->subYear()]);
        $profile = EmployeeProfile::create(['employee_id' => $employee->id, 'personal_email' => 'old@example.com']);
        $profile->forceFill(['personal_email_verified_at' => now()])->save();

        // What ProfileChanges does when HR applies the request.
        $user->forceFill(['email' => 'moved@example.com'])->save();
        $profile->update(['personal_email' => 'moved@example.com']);

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertNull($profile->fresh()->personal_email_verified_at);
        Mail::assertSent(EmailVerifyMail::class, 1);

        $this->get($this->linkSentTo('moved@example.com'));

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertNotNull($profile->fresh()->personal_email_verified_at);
    }

    public function test_a_link_for_an_address_no_longer_on_the_account_does_nothing(): void
    {
        [, $profile] = $this->staffWithWorkEmail();

        $profile->update(['personal_email' => 'first@example.com']);
        $stale = $this->linkSentTo('first@example.com');
        $profile->update(['personal_email' => 'second@example.com']);

        $this->get($stale)->assertSessionHas('status_tone', 'warning');
        $this->assertNull($profile->fresh()->personal_email_verified_at);
    }

    public function test_a_tampered_link_is_refused(): void
    {
        [, $profile] = $this->staffWithWorkEmail();
        $profile->update(['personal_email' => 'new@example.com']);

        $this->get($this->linkSentTo('new@example.com').'x')->assertForbidden();
    }

    public function test_my_profile_shows_it_and_can_send_the_link_again(): void
    {
        [$user, $profile] = $this->staffWithWorkEmail();
        $profile->update(['personal_email' => 'unproven@example.com']);
        Mail::fake();

        $this->actingAs($user);

        $this->get('/profile')->assertOk()
            ->assertSee('unproven@example.com')
            ->assertSee('Not verified')
            ->assertSee('Send verification link');

        $this->post('/profile/email/verify')->assertSessionHas('status_tone', 'success');

        Mail::assertSent(EmailVerifyMail::class, fn (EmailVerifyMail $m) => $m->hasTo('unproven@example.com'));
        // The work email is already verified, so it gets nothing.
        Mail::assertSent(EmailVerifyMail::class, 1);
    }

    /**
     * A staff member signing in with a verified work email, whose personal
     * email is a separate, verified address.
     *
     * @return array{0: User, 1: EmployeeProfile}
     */
    protected function staffWithWorkEmail(): array
    {
        $user = User::factory()->create([
            'email' => 'work@zephryxlabs.in',
            'account_type' => Realm::STAFF,
            'staff_kind' => 'employee',
        ]);
        $user->roles()->sync(Role::where('role_key', 'employee')->pluck('id'));

        $employee = Employee::create(['user_id' => $user->id, 'joined_on' => Carbon::now()->subYear()]);
        $profile = EmployeeProfile::create(['employee_id' => $employee->id, 'personal_email' => 'me@example.com']);
        $profile->forceFill(['personal_email_verified_at' => now()])->save();

        return [$user->fresh(), $profile->fresh()];
    }

    protected function linkSentTo(string $address): string
    {
        $url = null;

        Mail::assertSent(EmailVerifyMail::class, function (EmailVerifyMail $mail) use ($address, &$url) {
            if ($mail->address === $address) {
                $url = $mail->url;
            }

            return true;
        });

        $this->assertNotNull($url, 'no link went to '.$address);

        return $url;
    }
}
