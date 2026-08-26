<?php

namespace Tests\Feature;

use App\Support\Shell;
use App\Support\Theme;
use Tests\TestCase;

/**
 * The authenticated shell — sidebar, topbar, navigation.
 *
 * Foundation spec §5 (permission-filtered navigation), §12 (roadmap and the
 * deferred entries).
 */
class AppShellTest extends TestCase
{
    public function test_it_renders_the_shell(): void
    {
        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSee('class="sidebar"', false);
        $response->assertSee('class="topbar"', false);
        $response->assertSee('ZephryxLabs', false);
    }

    public function test_the_navigation_follows_the_roadmap_order(): void
    {
        $html = $this->get('/dashboard')->getContent();

        $order = ['Dashboard', 'Clients', 'Employees', 'Teams', 'Projects', 'Tasks'];
        $positions = array_map(fn (string $label) => strpos($html, '>'.$label.'</span>'), $order);

        $sorted = $positions;
        sort($sorted);

        $this->assertNotContains(false, $positions, 'a roadmap entry is missing from the sidebar');
        $this->assertSame($sorted, $positions, 'sidebar entries are out of roadmap order');
    }

    public function test_the_current_module_is_marked(): void
    {
        $response = $this->get('/employees');

        $response->assertSee('aria-current="page"', false);
        // The highlight is not carried by colour alone.
        $this->assertSame(1, substr_count($response->getContent(), 'aria-current="page"'));
    }

    public function test_an_entry_whose_module_does_not_exist_is_not_rendered(): void
    {
        // Settings routes into the Admin Panel (§12), which is not built, so
        // App\Support\Navigation drops it rather than rendering a dead link.
        $this->get('/dashboard')->assertDontSee('>Settings</span>', false);
    }

    public function test_deferred_modules_keep_their_entry_but_have_no_page(): void
    {
        // §12 — Leads and Calendar ship as navigation entries returning 404 so
        // the navigation's shape stays stable when they arrive in v2.
        $response = $this->get('/dashboard');
        $response->assertSee('>Leads</span>', false);
        $response->assertSee('>Calendar</span>', false);

        $this->get('/leads')->assertNotFound();
        $this->get('/calendar')->assertNotFound();
    }

    public function test_sign_out_is_a_post_with_a_csrf_token(): void
    {
        // A GET sign-out can be fired by any <img src="/logout"> the user loads.
        $response = $this->get('/dashboard');
        $response->assertSee('action="'.route('logout').'"', false);
        $response->assertSee('name="_token"', false);

        $this->get('/logout')->assertStatus(405);
    }

    public function test_the_search_box_is_inert_until_it_is_built(): void
    {
        // A working-looking search that silently returns nothing is worse than
        // one that says it is not ready.
        $this->get('/dashboard')->assertSee('disabled', false);
    }

    public function test_the_shell_renders_nothing_the_content_security_policy_would_block(): void
    {
        $html = $this->get('/dashboard')->getContent();

        $this->assertSame(0, preg_match_all('/<style[\s>]/i', $html), 'inline <style> block');
        $this->assertSame(0, preg_match_all('/\sstyle="/i', $html), 'inline style attribute');
        $this->assertSame(0, preg_match_all('/\son[a-z]+="/i', $html), 'inline event handler');
        $this->assertSame(0, preg_match_all('/<script(?![^>]*\ssrc=)/i', $html), 'inline <script> block');
    }

    public function test_shell_state_is_rendered_server_side(): void
    {
        // Read in JavaScript instead, every navigation would show the default
        // state for a frame and then jump.
        $this->get('/dashboard')
            ->assertSee('data-sidebar="expanded"', false)
            ->assertSee('data-density="comfortable"', false)
            ->assertSee('data-theme="dark"', false);

        $this->withUnencryptedCookies([
            Shell::SIDEBAR_COOKIE => 'collapsed',
            Shell::DENSITY_COOKIE => 'compact',
            Theme::COOKIE => 'light',
        ])->get('/dashboard')
            ->assertSee('data-sidebar="collapsed"', false)
            ->assertSee('data-density="compact"', false)
            ->assertSee('data-theme="light"', false);
    }

    public function test_tampered_shell_cookies_fall_back_to_the_default(): void
    {
        $this->withUnencryptedCookies([
            Shell::SIDEBAR_COOKIE => '"><script>alert(1)</script>',
            Shell::DENSITY_COOKIE => 'enormous',
        ])->get('/dashboard')
            ->assertOk()
            ->assertSee('data-sidebar="expanded"', false)
            ->assertSee('data-density="comfortable"', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
