<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Theme;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * GET /login and GET /login/verify — foundation spec §9.2.
 *
 * These cover the rendered pages. The flow behind them is exercised by
 * AuthenticationTest.
 */
class LoginPageTest extends TestCase
{
    /**
     * Get as far as the code step, the way a person does.
     *
     * `/login/verify` refuses to render without a half-finished sign-in behind
     * it (§4.2 step 6) — reaching that URL directly must not suggest a code was
     * sent to somebody. So these page tests have to actually sign in first.
     */
    protected function pendingSignIn(): User
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'verify-page@zephryxlabs.com']);

        $this->post('/login', [
            'identifier' => $user->email,
            'password' => 'password',
        ]);

        return $user;
    }

    public function test_it_renders_the_sign_in_form(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Welcome Back !', false);
        $response->assertSee('name="identifier"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('name="_token"', false);
    }

    public function test_the_identifier_field_accepts_an_email_or_a_user_id(): void
    {
        // §4.1 — one field for both; the handover labelled it "Username".
        $response = $this->get('/login');

        $response->assertSee('Email or User ID', false);
        $response->assertDontSee('placeholder="Username"', false);
    }

    public function test_it_shows_the_options_the_spec_keeps(): void
    {
        $response = $this->get('/login');

        $response->assertSee('Remember me', false);
        $response->assertSee('Forgot password?', false);
        $response->assertSee('Need access? Contact your', false);
        $response->assertSee('mailto:'.config('zephryx.support.email'), false);
    }

    public function test_it_never_reveals_which_realm_an_account_belongs_to(): void
    {
        // §3 — one form for all three realms; it must not ask or hint.
        $response = $this->get('/login');

        $response->assertDontSee('Staff', false);
        $response->assertDontSee('Client login', false);
        $response->assertDontSee('Admin', false);
    }

    public function test_it_carries_nothing_the_content_security_policy_would_block(): void
    {
        $html = $this->get('/login')->getContent();

        // This is the page where passwords are typed; §9.2 strips the
        // handover's unpkg script tags and its simulated submit handler.
        $this->assertStringNotContainsString('unpkg.com', $html);
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertSame(0, preg_match_all('/<style[\s>]/i', $html), 'inline <style> block');
        $this->assertSame(0, preg_match_all('/\sstyle="/i', $html), 'inline style attribute');
        $this->assertSame(0, preg_match_all('/\son[a-z]+="/i', $html), 'inline event handler');
        $this->assertSame(0, preg_match_all('/<script(?![^>]*\ssrc=)/i', $html), 'inline <script> block');
    }

    public function test_it_is_not_indexed(): void
    {
        $this->get('/login')->assertSee('noindex, nofollow', false);
    }

    public function test_it_honours_the_theme_preference(): void
    {
        $this->get('/login')->assertSee('data-theme="dark"', false);

        $this->withUnencryptedCookie(Theme::COOKIE, 'light')
            ->get('/login')
            ->assertSee('data-theme="light"', false);
    }

    /**
     * The preview parameter exists so the form-level states can be reviewed
     * while the page is designed. Outside local + debug it must do nothing —
     * otherwise anyone could put "Session expired" or "Cannot sign in" on the
     * live sign-in page and use it to mislead staff.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function previewProvider(): array
    {
        return [
            'lockout'  => ['lockout', 'Too many attempts'],
            'expired'  => ['expired', 'Session expired'],
            'disabled' => ['disabled', 'Cannot sign in'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('previewProvider')]
    public function test_previews_are_unavailable_outside_local_debug(string $preview, string $text): void
    {
        $this->get('/login?preview='.$preview)->assertDontSee($text, false);
    }

    public function test_an_unknown_preview_value_is_ignored(): void
    {
        $this->get('/login?preview=<script>alert(1)</script>')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_the_resend_cooldown_preview_is_unavailable_outside_local_debug(): void
    {
        $this->pendingSignIn();

        /*
         * The cooldown shown is the REAL one now — a code was just issued, so
         * there genuinely is one. What this still checks is that `?preview=`
         * cannot fabricate a number outside local + debug.
         */
        $this->get('/login/verify?preview=cooldown')
            ->assertDontSee('Resend in 45s', false);
    }

    public function test_a_failed_attempt_takes_precedence_over_any_other_banner(): void
    {
        // The user just did something; telling them their session expired
        // instead of that the attempt failed would be actively misleading.
        $response = $this->withSession([
            'status' => 'You were signed out after a period of inactivity.',
            'status_tone' => 'info',
        ])->from('/login')->followingRedirects()->post('/login', [
            'identifier' => 'santanu@zephryxlabs.com',
            'password' => 'whatever-it-is',
        ]);

        // The one refusal (§4.2 step 3) — never a reason, never a hint.
        $response->assertSee('Invalid credentials.', false);
        $response->assertDontSee('period of inactivity', false);
    }

    public function test_the_verify_step_renders_six_code_boxes(): void
    {
        $this->pendingSignIn();

        $response = $this->get('/login/verify');

        $response->assertOk();
        $response->assertSee('Check your email', false);
        $response->assertSee('one-time-code', false);
        $this->assertSame(6, substr_count($response->getContent(), 'name="code[]"'));
    }

    public function test_the_resend_form_does_not_carry_the_code(): void
    {
        // Resend posts to its own route; the entered digits must not ride along.
        $this->pendingSignIn();

        $html = $this->get('/login/verify')->getContent();

        $resendForm = substr($html, strpos($html, 'id="resend-form"'));

        $this->assertStringNotContainsString('name="code[]"', $resendForm);
    }
}
