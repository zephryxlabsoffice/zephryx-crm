<?php

namespace Tests\Feature;

use App\Support\Shell;
use Tests\TestCase;

/**
 * POST /shell — sidebar collapse and density.
 */
class ShellPreferenceTest extends TestCase
{
    public function test_it_stores_the_sidebar_state(): void
    {
        $this->postJson('/shell', ['sidebar' => 'collapsed'])
            ->assertOk()
            ->assertPlainCookie(Shell::SIDEBAR_COOKIE, 'collapsed');
    }

    public function test_it_stores_the_density(): void
    {
        $this->postJson('/shell', ['density' => 'compact'])
            ->assertOk()
            ->assertPlainCookie(Shell::DENSITY_COOKIE, 'compact');
    }

    public function test_it_rejects_values_outside_the_allow_list(): void
    {
        // Both are echoed into attributes on <html> on every later page.
        $this->post('/shell', ['sidebar' => '"><script>'])->assertSessionHasErrors('sidebar');
        $this->post('/shell', ['density' => 'enormous'])->assertSessionHasErrors('density');
    }

    public function test_it_is_rate_limited(): void
    {
        $this->assertContains(
            'throttle:60,1',
            app('router')->getRoutes()->getByName('shell.store')->gatherMiddleware()
        );
    }
}
