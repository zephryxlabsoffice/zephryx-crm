<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * POST /login and POST /login/verify — the inline validation states.
 *
 * Authentication itself is not implemented; these pin the field-level
 * behaviour the login page's design depends on.
 */
class LoginValidationTest extends TestCase
{
    public function test_an_empty_submission_reports_both_fields(): void
    {
        $response = $this->from('/login')->post('/login', []);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['identifier', 'password']);
    }

    public function test_a_missing_password_is_reported_on_its_own(): void
    {
        $response = $this->from('/login')->post('/login', ['identifier' => 'yash@zephryxlabs.com']);

        $response->assertSessionHasErrors('password');
        $response->assertSessionDoesntHaveErrors('identifier');
    }

    public function test_the_password_is_never_flashed_back_to_the_form(): void
    {
        $this->from('/login')->post('/login', [
            'identifier' => 'yash@zephryxlabs.com',
            'password' => 'a-real-password',
        ]);

        $this->assertSame('yash@zephryxlabs.com', session('_old_input.identifier'));
        $this->assertArrayNotHasKey('password', (array) session('_old_input'));
    }

    public function test_a_short_code_is_rejected_before_anything_else(): void
    {
        $response = $this->from('/login/verify')->post('/login/verify', [
            'code' => ['1', '2', '3'],
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_the_sign_in_routes_are_rate_limited(): void
    {
        $routes = app('router')->getRoutes();

        $this->assertContains('throttle:10,1', $routes->getByName('login.attempt')->gatherMiddleware());
        $this->assertContains('throttle:10,1', $routes->getByName('login.verify.attempt')->gatherMiddleware());
        $this->assertContains('throttle:5,1', $routes->getByName('login.resend')->gatherMiddleware());
    }
}
