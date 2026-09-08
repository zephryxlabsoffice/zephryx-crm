<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceSessionLifetime;
use App\Mail\OneTimeCodeMail;
use App\Mail\PasswordResetMail;
use App\Models\User;
use App\Support\Auth\LoginThrottle;
use App\Support\Auth\OneTimeCode;
use App\Support\Auth\RememberMe;
use App\Support\Auth\TrustedDevices;
use App\Support\Realm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Authentication (foundation spec §4).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MOST OF THIS FILE IS ABOUT WHAT THE APPLICATION REFUSES TO SAY
 *
 * §4.2's flow is mostly a list of things that must NOT differ: an unknown
 * address and a wrong password give the same message, a closed account gives
 * the same message, and a wrong code gives the same message as an expired one.
 * Each of those differences, if it existed, would be a way to learn something
 * about somebody else's account.
 *
 * The rest is about a session not existing until it should, and stopping when
 * it should.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class AuthenticationTest extends TestCase
{
    protected const PASSWORD = 'password';

    protected function user(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    /**
     * Sign in far enough to be waiting on a code.
     *
     * @return array{user: User, code: string}
     */
    protected function toVerifyStep(array $attributes = []): array
    {
        Mail::fake();

        $user = $this->user($attributes);

        $this->post('/login', ['identifier' => $user->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('login.verify'));

        $code = null;

        Mail::assertSent(OneTimeCodeMail::class, function (OneTimeCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $this->assertNotNull($code);

        return ['user' => $user, 'code' => $code];
    }

    /* ══════════════════════════════════════════════════════════════════════
       §4.2 — ONE MESSAGE, WHATEVER WENT WRONG
       ══════════════════════════════════════════════════════════════════════ */

    public function test_an_unknown_identifier_and_a_wrong_password_are_indistinguishable(): void
    {
        $user = $this->user();

        /*
         * The difference between these two answers is a staff directory: type
         * an address, read the response, repeat. §4.2 step 3.
         *
         * Asserted against the exact string rather than by comparing the two
         * responses, so that softening the message to something helpful — "no
         * account with that email" — fails here rather than passing because
         * both were softened the same way.
         */
        $this->from('/login')->post('/login', [
            'identifier' => 'nobody-at-all@zephryxlabs.com',
            'password' => 'whatever-it-is',
        ])->assertSessionHasErrors(['auth' => 'Invalid credentials.']);

        $this->from('/login')->post('/login', [
            'identifier' => $user->email,
            'password' => 'definitely-not-the-password',
        ])->assertSessionHasErrors(['auth' => 'Invalid credentials.']);

        $this->assertGuest();
    }

    public function test_a_closed_account_leaks_nothing_at_the_form(): void
    {
        $user = $this->user(['status' => 'suspended']);

        // The password is CORRECT. §4.2 step 4 still says nothing about why.
        $response = $this->from('/login')->post('/login', [
            'identifier' => $user->email,
            'password' => self::PASSWORD,
        ]);

        // They are sent to the closed-account page, which explains — but only
        // because they proved they hold the credentials.
        $response->assertRedirect(route('account.inactive'));
        $this->assertGuest();
    }

    public function test_a_closed_account_never_receives_a_code(): void
    {
        Mail::fake();

        $user = $this->user(['status' => 'inactive']);

        $this->post('/login', ['identifier' => $user->email, 'password' => self::PASSWORD]);

        Mail::assertNothingSent();
    }

    public function test_a_user_id_signs_in_as_well_as_an_email(): void
    {
        // §4.1 — one field, an `@` decides how it is read.
        Mail::fake();

        $user = $this->user(['user_id' => 'EMP999']);

        $this->post('/login', ['identifier' => 'EMP999', 'password' => self::PASSWORD])
            ->assertRedirect(route('login.verify'));
    }

    /* ══════════════════════════════════════════════════════════════════════
       §4.2 STEP 6 — NO SESSION UNTIL THE CODE IS VERIFIED
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_correct_password_alone_does_not_sign_anybody_in(): void
    {
        /*
         * The one that makes two-factor mean anything. Signing somebody in and
         * then asking for a code makes the code decorative — whatever holds a
         * session cookie is already inside.
         */
        $this->toVerifyStep();

        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_the_code_completes_the_sign_in(): void
    {
        ['user' => $user, 'code' => $code] = $this->toVerifyStep();

        $this->post('/login/verify', ['code' => str_split($code)])
            ->assertRedirect(Realm::dashboardFor($user));

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_verify_page_is_not_reachable_without_a_pending_sign_in(): void
    {
        // Reaching this URL directly must not suggest a code was sent to
        // anybody.
        $this->get('/login/verify')->assertRedirect(route('login'));
    }

    public function test_a_wrong_code_and_an_expired_code_read_the_same(): void
    {
        ['code' => $code] = $this->toVerifyStep();

        $message = 'That code was not accepted. Check it, or ask for a new one.';

        $this->from('/login/verify')
            ->post('/login/verify', ['code' => str_split($this->otherThan($code))])
            ->assertSessionHasErrors(['code' => $message]);

        // Expire the live code, then present the real one.
        DB::table('two_factor_codes')->update(['expires_at' => now()->subMinute()]);

        /*
         * "That code has expired" would tell somebody holding a stolen password
         * that they had the right account and only needed to be quicker.
         */
        $this->from('/login/verify')
            ->post('/login/verify', ['code' => str_split($code)])
            ->assertSessionHasErrors(['code' => $message]);
    }

    public function test_a_code_is_single_use(): void
    {
        ['user' => $user, 'code' => $code] = $this->toVerifyStep();

        $this->post('/login/verify', ['code' => str_split($code)]);
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');
        $this->assertGuest();

        // The same code again, on a fresh pending sign-in.
        Mail::fake();
        $this->post('/login', ['identifier' => $user->email, 'password' => self::PASSWORD]);

        $this->post('/login/verify', ['code' => str_split($code)]);
        $this->assertGuest();
    }

    public function test_five_wrong_codes_burn_the_code(): void
    {
        // §4.3 — maximum 5 attempts per code, then invalidated. Otherwise five
        // guesses per code plus unlimited resends is unlimited guesses.
        ['code' => $code] = $this->toVerifyStep();

        for ($i = 0; $i < OneTimeCode::MAX_ATTEMPTS; $i++) {
            $this->post('/login/verify', ['code' => str_split($this->otherThan($code))]);
        }

        // Even the right one now fails.
        $this->post('/login/verify', ['code' => str_split($code)]);
        $this->assertGuest();
    }

    public function test_resending_invalidates_the_previous_code(): void
    {
        ['user' => $user, 'code' => $first] = $this->toVerifyStep();

        // Past the cooldown.
        DB::table('two_factor_codes')->update([
            'created_at' => now()->subSeconds(OneTimeCode::RESEND_COOLDOWN_SECONDS + 5),
        ]);

        $this->post('/login/resend');

        // The first code no longer works — there is never more than one live.
        $this->post('/login/verify', ['code' => str_split($first)]);
        $this->assertGuest();
    }

    public function test_resending_is_rate_limited(): void
    {
        $this->toVerifyStep();

        Mail::fake();
        $this->post('/login/resend');

        // §4.3 — the cooldown is measured from the live code's creation, so it
        // cannot be sidestepped.
        Mail::assertNothingSent();
    }

    /* ══════════════════════════════════════════════════════════════════════
       §4.2 STEP 1 — RATE LIMITING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_repeated_failures_lock_the_identifier(): void
    {
        $user = $this->user();

        for ($i = 0; $i < LoginThrottle::IDENTIFIER_LIMIT; $i++) {
            $this->post('/login', ['identifier' => $user->email, 'password' => 'wrong-'.$i]);
        }

        Mail::fake();

        // The CORRECT password now, and it is refused.
        $this->post('/login', ['identifier' => $user->email, 'password' => self::PASSWORD]);

        Mail::assertNothingSent();
        $this->assertGuest();
    }

    public function test_the_lock_lifts_from_the_oldest_failure_not_the_newest(): void
    {
        /*
         * Measuring from the newest would let somebody hold a colleague's
         * account locked forever by failing once every few minutes — a denial
         * of service that costs the attacker nothing.
         */
        $user = $this->user();
        $throttle = app(LoginThrottle::class);

        for ($i = 0; $i < LoginThrottle::IDENTIFIER_LIMIT; $i++) {
            $this->post('/login', ['identifier' => $user->email, 'password' => 'wrong-'.$i]);
        }

        $this->assertNotNull($throttle->lockedFor($user->email, request()));

        // Age the block past its window.
        DB::table('login_attempts')->update([
            'created_at' => now()->subMinutes(LoginThrottle::IDENTIFIER_WINDOW_MINUTES + 1),
        ]);

        $this->assertNull($throttle->lockedFor($user->email, request()));
    }

    public function test_a_success_clears_the_identifier_but_not_the_address(): void
    {
        /*
         * Somebody guessing one password correctly on a shared address must not
         * reset the counter protecting everybody else behind it.
         */
        $mine = $this->user();
        $theirs = $this->user();

        $this->post('/login', ['identifier' => $theirs->email, 'password' => 'wrong']);
        $this->post('/login', ['identifier' => $mine->email, 'password' => 'wrong']);

        Mail::fake();
        $this->post('/login', ['identifier' => $mine->email, 'password' => self::PASSWORD]);

        $this->assertSame(0, DB::table('login_attempts')
            ->where('identifier', $mine->email)->where('succeeded', false)->count());

        $this->assertSame(1, DB::table('login_attempts')
            ->where('identifier', $theirs->email)->where('succeeded', false)->count());
    }

    public function test_every_attempt_is_recorded(): void
    {
        // §4.2 step 3 — "Attempt logged". Successes too: a sign-in from an
        // unfamiliar address is what an investigation actually needs.
        $user = $this->user();

        $this->post('/login', ['identifier' => $user->email, 'password' => 'wrong']);

        $this->assertSame(1, DB::table('login_attempts')
            ->where('identifier', $user->email)->where('succeeded', false)->count());

        Mail::fake();
        $this->post('/login', ['identifier' => $user->email, 'password' => self::PASSWORD]);

        /*
         * The success is recorded, and it CLEARS this identifier's failures —
         * so the count is one, not two. Somebody who signs in correctly should
         * not be one bad afternoon away from locking themselves out.
         *
         * The success row stays: a sign-in from an unfamiliar address is what
         * an investigation actually needs, and a table of failures alone cannot
         * show it.
         */
        $this->assertSame(1, DB::table('login_attempts')->where('succeeded', true)->count());
        $this->assertSame(0, DB::table('login_attempts')
            ->where('identifier', $user->email)->where('succeeded', false)->count());
    }

    /* ══════════════════════════════════════════════════════════════════════
       §4.3 — TRUSTED DEVICES
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_verified_code_trusts_the_device_and_the_next_sign_in_skips_it(): void
    {
        ['user' => $user, 'code' => $code] = $this->toVerifyStep();

        $response = $this->post('/login/verify', ['code' => str_split($code)]);

        $cookie = $response->getCookie(TrustedDevices::COOKIE);
        $this->assertNotNull($cookie, 'no device-trust cookie was set');

        $this->post('/logout');

        Mail::fake();

        // Same device, correct password — straight in, no code.
        $this->withCookie(TrustedDevices::COOKIE, $cookie->getValue())
            ->post('/login', ['identifier' => $user->email, 'password' => self::PASSWORD])
            ->assertRedirect(Realm::dashboardFor($user));

        Mail::assertNothingSent();
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_trusted_device_never_skips_the_password(): void
    {
        // The whole reason trust is safe to have: the cookie is not a
        // credential on its own. A stolen laptop is still a laptop nobody can
        // sign in from.
        ['user' => $user, 'code' => $code] = $this->toVerifyStep();

        $cookie = $this->post('/login/verify', ['code' => str_split($code)])
            ->getCookie(TrustedDevices::COOKIE);

        $this->post('/logout');

        $this->withCookie(TrustedDevices::COOKIE, $cookie->getValue())
            ->post('/login', ['identifier' => $user->email, 'password' => 'not-the-password']);

        $this->assertGuest();
    }

    public function test_signing_out_revokes_the_trust_on_that_device(): void
    {
        // §4.4 — logout destroys the session, the remember-me token and the
        // device trust. On a shared machine, all three or none.
        ['user' => $user, 'code' => $code] = $this->toVerifyStep();

        $cookie = $this->post('/login/verify', ['code' => str_split($code)])
            ->getCookie(TrustedDevices::COOKIE);

        // The cookie has to travel with the sign-out, as it does in a browser —
        // revoking "this device" means the one the request came from.
        $this->withCookie(TrustedDevices::COOKIE, $cookie->getValue())->post('/logout');

        Mail::fake();

        $this->withCookie(TrustedDevices::COOKIE, $cookie->getValue())
            ->post('/login', ['identifier' => $user->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('login.verify'));

        // A code again, because the trust is gone.
        Mail::assertSent(OneTimeCodeMail::class);
    }

    /* ══════════════════════════════════════════════════════════════════════
       §4.5 — REMEMBER ME
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_admin_account_is_never_remembered(): void
    {
        ['user' => $user, 'code' => $code] = $this->toVerifyStep(['account_type' => Realm::ADMIN, 'staff_kind' => null]);

        $response = $this->post('/login/verify', ['code' => str_split($code)]);

        $this->assertNull($response->getCookie(RememberMe::COOKIE));
    }

    public function test_issuing_a_remember_token_to_the_admin_account_is_refused_loudly(): void
    {
        // A silent no-op would leave somebody believing they were remembered.
        $admin = $this->user(['account_type' => Realm::ADMIN, 'staff_kind' => null]);

        $this->expectException(\LogicException::class);

        app(RememberMe::class)->issue($admin, request());
    }

    public function test_a_retired_remember_token_revokes_the_whole_chain(): void
    {
        /*
         * §4.5's theft signal. A token that has already been rotated past means
         * the cookie was copied — refusing just that request would leave the
         * thief's fresh token working.
         */
        $user = $this->user();
        $remember = app(RememberMe::class);

        $first = $remember->issue($user, request())->getValue();

        // The legitimate browser uses it and rotates forward.
        $this->withCookie(RememberMe::COOKIE, $first)->get('/login');

        $this->assertGreaterThan(0, DB::table('remember_tokens')->where('user_id', $user->id)->count());

        // The copy is presented afterwards.
        $this->withCookie(RememberMe::COOKIE, $first)->get('/login');

        $this->assertSame(0, DB::table('remember_tokens')->where('user_id', $user->id)->count());
    }

    public function test_remembering_does_not_bypass_the_code_on_an_untrusted_device(): void
    {
        /*
         * §4.5 in one line. The two cookies answer different questions —
         * remember-me says WHO, device trust says whether a code is still owed
         * — and getting this wrong turns one stolen cookie into permanent
         * two-factor bypass.
         */
        $user = $this->user();

        $cookie = app(RememberMe::class)->issue($user, request());

        $this->withCookie(RememberMe::COOKIE, $cookie->getValue())
            ->get('/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    /* ══════════════════════════════════════════════════════════════════════
       §4.4 — SESSION LIFETIME
       ══════════════════════════════════════════════════════════════════════ */

    public function test_an_idle_staff_session_expires_after_thirty_days(): void
    {
        /*
         * Thirty days is the staff idle window as of 2026-09-08. Reaching it
         * needs the seven-day ceiling held back, because otherwise that fires
         * first and this would pass for the wrong reason — so the session is
         * stamped as issued a moment ago and idle for a month, which is a state
         * only remember-me produces in practice (§4.5 restores a session and
         * stamps it fresh).
         */
        $this->signInAsStaff();

        $this->get('/dashboard')->assertOk();

        $this->session([
            EnforceSessionLifetime::ISSUED_AT => now()->timestamp,
            EnforceSessionLifetime::LAST_SEEN => now()->subDays(31)->timestamp,
        ]);

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_an_idle_admin_session_expires_after_two_hours(): void
    {
        // §4.4 gives the account that can rewrite everybody's permissions a much
        // shorter leash than the one that reads a task list.
        $this->signInAsAdmin();

        $this->get('/admin/dashboard')->assertOk();

        $this->session([
            EnforceSessionLifetime::LAST_SEEN => now()->subMinutes(121)->timestamp,
        ]);

        $this->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_staff_session_survives_two_hours_idle(): void
    {
        // The counterpart: the admin rule must not have been applied to
        // everybody, which would sign people out over a long lunch.
        $this->signInAsStaff();

        $this->session([
            EnforceSessionLifetime::LAST_SEEN => now()->subMinutes(121)->timestamp,
        ]);

        $this->get('/dashboard')->assertOk();
    }


    public function test_a_session_expires_seven_days_after_it_was_issued_however_busy(): void
    {
        /*
         * The absolute ceiling. Laravel's own lifetime is idle-based, so a
         * session used daily would otherwise live forever — and a stolen cookie
         * with it.
         *
         * This is also what stops the thirty-day idle window from being
         * "reconciled" upward: the two numbers disagree on purpose, and a
         * session active this second, issued eight days ago, must still be over.
         */
        $this->signInAsStaff();

        $this->session([
            EnforceSessionLifetime::ISSUED_AT => now()->subDays(8)->timestamp,
            EnforceSessionLifetime::LAST_SEEN => now()->timestamp,
        ]);

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /* ══════════════════════════════════════════════════════════════════════
       §4.6 — PASSWORD RESET
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_reset_form_answers_identically_for_a_missing_account(): void
    {
        Mail::fake();

        $user = $this->user();

        $known = $this->from('/forgot-password')->post('/forgot-password', ['identifier' => $user->email]);
        $unknown = $this->from('/forgot-password')->post('/forgot-password', ['identifier' => 'nobody@zephryxlabs.com']);

        $this->assertSame(
            $known->getSession()->get('status'),
            $unknown->getSession()->get('status'),
        );

        // And only one email went anywhere.
        Mail::assertSentCount(1);
    }

    public function test_a_reset_changes_the_password_and_revokes_everything(): void
    {
        Mail::fake();

        $user = $this->user();
        $remember = app(RememberMe::class);
        $devices = app(TrustedDevices::class);

        $remember->issue($user, request());
        $devices->trust($user, request());

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

        // §4.6 — all four.
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('a-brand-new-passphrase', $user->fresh()->password));
        $this->assertSame(0, DB::table('remember_tokens')->where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('trusted_devices')->where('user_id', $user->id)->whereNull('revoked_at')->count());
        $this->assertSame(0, DB::table('password_resets')->where('user_id', $user->id)->whereNull('consumed_at')->count());
    }

    public function test_a_reset_token_is_single_use(): void
    {
        Mail::fake();

        $user = $this->user();
        $this->post('/forgot-password', ['identifier' => $user->email]);

        $token = null;
        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use (&$token) {
            $token = str($mail->url)->afterLast('/')->value();

            return true;
        });

        $payload = [
            'token' => $token,
            'identifier' => $user->email,
            'password' => 'a-brand-new-passphrase',
            'password_confirmation' => 'a-brand-new-passphrase',
        ];

        $this->post('/reset-password', $payload);
        $this->from('/reset-password/'.$token)->post('/reset-password', $payload)
            ->assertSessionHasErrors('token');
    }

    public function test_the_identifier_field_does_not_decide_whose_password_is_reset(): void
    {
        /*
         * The whole vulnerability, in one field: if the posted identifier were
         * trusted, anybody with a valid token for their own account could reset
         * somebody else's by editing it.
         */
        Mail::fake();

        $mine = $this->user();
        $theirs = $this->user();

        $this->post('/forgot-password', ['identifier' => $mine->email]);

        $token = null;
        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use (&$token) {
            $token = str($mail->url)->afterLast('/')->value();

            return true;
        });

        $this->post('/reset-password', [
            'token' => $token,
            // Somebody else's.
            'identifier' => $theirs->email,
            'password' => 'a-brand-new-passphrase',
            'password_confirmation' => 'a-brand-new-passphrase',
        ]);

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('a-brand-new-passphrase', $mine->fresh()->password));
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('a-brand-new-passphrase', $theirs->fresh()->password));
    }

    public function test_a_suspended_account_gets_no_reset_link_and_is_not_told(): void
    {
        Mail::fake();

        $user = $this->user(['status' => 'suspended']);

        $this->post('/forgot-password', ['identifier' => $user->email])
            ->assertSessionHas('status');

        Mail::assertNothingSent();
    }

    /* ══════════════════════════════════════════════════════════════════════
       SIGNING OUT
       ══════════════════════════════════════════════════════════════════════ */

    public function test_signing_out_clears_the_session_and_both_cookies(): void
    {
        ['user' => $user, 'code' => $code] = $this->toVerifyStep();

        $this->post('/login/verify', ['code' => str_split($code)]);
        $this->assertAuthenticatedAs($user);

        $response = $this->post('/logout');

        $this->assertGuest();
        $this->assertSame('', $response->getCookie(RememberMe::COOKIE)?->getValue());
        $this->assertSame('', $response->getCookie(TrustedDevices::COOKIE)?->getValue());
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHAT IS WRITTEN DOWN
       ══════════════════════════════════════════════════════════════════════ */

    public function test_no_secret_is_stored_in_a_form_that_could_be_used(): void
    {
        /*
         * A dump of these tables must not hand over a single working
         * credential. Every one of them holds a hash and the plaintext lives
         * only in the email or the cookie it was sent in.
         */
        ['user' => $user, 'code' => $code] = $this->toVerifyStep();

        $stored = DB::table('two_factor_codes')->where('user_id', $user->id)->value('code_hash');

        $this->assertNotSame($code, $stored);
        $this->assertStringNotContainsString($code, (string) $stored);

        $cookie = app(TrustedDevices::class)->trust($user, request());
        $trusted = DB::table('trusted_devices')->where('user_id', $user->id)->value('token_hash');

        $this->assertNotSame($cookie->getValue(), $trusted);
    }

    public function test_authentication_events_are_audited(): void
    {
        // §6 requires authentication events in the audit log.
        ['user' => $user, 'code' => $code] = $this->toVerifyStep();

        $this->post('/login/verify', ['code' => str_split($code)]);

        $actions = DB::table('audit_log')->where('actor_user_id', $user->id)->pluck('action');

        $this->assertTrue($actions->contains(\App\Support\Audit\AuditLog::OTP_ISSUED));
        $this->assertTrue($actions->contains(\App\Support\Audit\AuditLog::SIGNED_IN));
        $this->assertTrue($actions->contains(\App\Support\Audit\AuditLog::DEVICE_TRUSTED));
    }

    public function test_a_refused_sign_in_is_audited_even_with_no_account_behind_it(): void
    {
        // An attempted break-in against an address that does not exist leaves
        // no user to attach to, and it is exactly the traffic worth recording.
        $this->post('/login', ['identifier' => 'nobody@zephryxlabs.com', 'password' => 'guessing']);

        $this->assertSame(1, DB::table('audit_log')
            ->where('action', \App\Support\Audit\AuditLog::SIGN_IN_REFUSED)
            ->count());
    }

    /**
     * A six-digit code that is not the given one.
     */
    protected function otherThan(string $code): string
    {
        return str_pad((string) (((int) $code + 1) % 1000000), 6, '0', STR_PAD_LEFT);
    }
}
