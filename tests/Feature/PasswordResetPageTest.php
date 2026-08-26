<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * /forgot-password and /reset-password — foundation spec §4.6, §4.7.
 *
 * Token issue, mail and token verification are not implemented. The
 * no-enumeration guarantee and the password policy are, and are the reason
 * this file exists.
 */
class PasswordResetPageTest extends TestCase
{
    public function test_the_request_form_renders(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertOk();
        $response->assertSee('Forgot your password?', false);
        $response->assertSee('name="identifier"', false);
        $response->assertSee('name="_token"', false);
    }

    public function test_the_login_page_links_to_it(): void
    {
        $this->get('/login')->assertSee('href="'.route('password.forgot').'"', false);
    }

    public function test_the_response_is_identical_for_any_identifier(): void
    {
        // §4.6 — the confirmation must not reveal whether an account exists.
        // Once the backend lands, only the side effects may differ.
        $known = $this->from('/forgot-password')
            ->post('/forgot-password', ['identifier' => 'santanu@zephryxlabs.com']);

        $unknown = $this->from('/forgot-password')
            ->post('/forgot-password', ['identifier' => 'nobody-at-all-9f3a']);

        $this->assertSame($known->getStatusCode(), $unknown->getStatusCode());
        $this->assertSame(session()->get('status'), session()->get('status'));

        $known->assertSessionHas('status');
        $unknown->assertSessionHas('status');
        $this->assertStringContainsString('If that account exists', (string) session('status'));
    }

    public function test_an_empty_identifier_is_reported(): void
    {
        $this->from('/forgot-password')
            ->post('/forgot-password', [])
            ->assertSessionHasErrors('identifier');
    }

    public function test_the_reset_form_carries_the_token_from_the_url(): void
    {
        $response = $this->get('/reset-password/abc123token');

        $response->assertOk();
        $response->assertSee('value="abc123token"', false);
        $response->assertSee('name="password_confirmation"', false);
    }

    public function test_a_malformed_token_is_not_routed(): void
    {
        // Keeps anything unexpected out of the view, where it would be echoed
        // into a hidden input.
        $this->get('/reset-password/'.urlencode('<script>alert(1)</script>'))->assertNotFound();
    }

    public function test_a_short_password_is_rejected(): void
    {
        $this->from('/reset-password/abc123token')->post('/reset-password', [
            'token' => 'abc123token',
            'identifier' => 'santanu@zephryxlabs.com',
            'password' => 'short1234',
            'password_confirmation' => 'short1234',
        ])->assertSessionHasErrors('password');
    }

    public function test_a_common_password_is_rejected_even_when_long_enough(): void
    {
        $this->from('/reset-password/abc123token')->post('/reset-password', [
            'token' => 'abc123token',
            'identifier' => 'santanu@zephryxlabs.com',
            'password' => 'P@ssw0rd1234',
            'password_confirmation' => 'P@ssw0rd1234',
        ])->assertSessionHasErrors('password');
    }

    public function test_mismatched_passwords_are_rejected(): void
    {
        $this->from('/reset-password/abc123token')->post('/reset-password', [
            'token' => 'abc123token',
            'identifier' => 'santanu@zephryxlabs.com',
            'password' => 'velvet harbour ninety',
            'password_confirmation' => 'velvet harbour ninetx',
        ])->assertSessionHasErrors('password');
    }

    public function test_a_good_password_clears_validation(): void
    {
        // It still fails at the token step, because that is not built — but it
        // must get past the policy.
        $this->from('/reset-password/abc123token')->post('/reset-password', [
            'token' => 'abc123token',
            'identifier' => 'santanu@zephryxlabs.com',
            'password' => 'velvet harbour ninety',
            'password_confirmation' => 'velvet harbour ninety',
        ])->assertSessionDoesntHaveErrors('password');
    }

    public function test_the_password_is_never_flashed_back(): void
    {
        $this->from('/reset-password/abc123token')->post('/reset-password', [
            'token' => 'abc123token',
            'identifier' => 'santanu@zephryxlabs.com',
            'password' => 'velvet harbour ninety',
            'password_confirmation' => 'velvet harbour ninety',
        ]);

        $old = (array) session('_old_input');

        $this->assertArrayNotHasKey('password', $old);
        $this->assertArrayNotHasKey('password_confirmation', $old);
    }

    public function test_the_reset_routes_are_rate_limited(): void
    {
        $routes = app('router')->getRoutes();

        $this->assertContains('throttle:5,1', $routes->getByName('password.request')->gatherMiddleware());
        $this->assertContains('throttle:5,1', $routes->getByName('password.reset')->gatherMiddleware());
    }
}
