<?php

namespace Tests\Feature;

use App\Support\Theme;
use Tests\TestCase;

/**
 * POST /theme — foundation spec §3.2, §7.
 */
class ThemePreferenceTest extends TestCase
{
    public function test_it_stores_a_valid_preference(): void
    {
        $response = $this->post('/theme', ['theme' => 'light']);

        $response->assertRedirect();
        $response->assertPlainCookie(Theme::COOKIE, 'light');
    }

    public function test_it_answers_json_for_an_asynchronous_request(): void
    {
        $response = $this->postJson('/theme', ['theme' => 'light']);

        $response->assertOk();
        $response->assertJson(['theme' => 'light']);
        $response->assertPlainCookie(Theme::COOKIE, 'light');
    }

    public function test_it_rejects_a_theme_outside_the_allow_list(): void
    {
        $this->post('/theme', ['theme' => 'neon'])
            ->assertSessionHasErrors('theme');

        $this->post('/theme', ['theme' => '<script>alert(1)</script>'])
            ->assertSessionHasErrors('theme');
    }

    public function test_the_toggle_submits_a_csrf_token(): void
    {
        // Laravel's ValidateCsrfToken middleware short-circuits while the suite
        // is running, so a 419 cannot be asserted here. What is worth pinning is
        // that the form actually carries a token (spec §6) and posts to the
        // route inside the `web` group, where that middleware applies.
        $this->get('/')
            ->assertSee('action="'.route('theme.store').'"', false)
            ->assertSee('name="_token"', false);
    }

    public function test_the_endpoint_is_rate_limited(): void
    {
        $this->assertContains(
            'throttle:30,1',
            app('router')->getRoutes()->getByName('theme.store')->gatherMiddleware()
        );
    }
}
