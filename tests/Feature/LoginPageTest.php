<?php

namespace Tests\Feature;

use App\Support\Theme;
use Tests\TestCase;

/**
 * GET /login and GET /login/verify — foundation spec §9.2.
 *
 * These cover the rendered page only. The credential check, OTP issue/verify
 * and session handling in §4 are not implemented yet.
 */
class LoginPageTest extends TestCase
{
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

    public function test_the_lockout_preview_is_unavailable_outside_local_debug(): void
    {
        // The query parameter exists so the banner can be reviewed while the
        // page is designed. It must not be a way to fake a lockout notice on a
        // deployed site.
        $this->get('/login?preview=lockout')
            ->assertDontSee('Too many attempts', false);
    }

    public function test_the_verify_step_renders_six_code_boxes(): void
    {
        $response = $this->get('/login/verify');

        $response->assertOk();
        $response->assertSee('Check your email', false);
        $response->assertSee('one-time-code', false);
        $this->assertSame(6, substr_count($response->getContent(), 'name="code[]"'));
    }

    public function test_the_resend_form_does_not_carry_the_code(): void
    {
        // Resend posts to its own route; the entered digits must not ride along.
        $html = $this->get('/login/verify')->getContent();

        $resendForm = substr($html, strpos($html, 'id="resend-form"'));

        $this->assertStringNotContainsString('name="code[]"', $resendForm);
    }
}
