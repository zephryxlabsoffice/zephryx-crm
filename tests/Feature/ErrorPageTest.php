<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Branded error pages.
 *
 * These are the pages nobody looks at until they are already having a bad day,
 * which is exactly why they are worth testing.
 */
class ErrorPageTest extends TestCase
{
    public function test_a_missing_page_renders_the_branded_404(): void
    {
        $response = $this->get('/nope-does-not-exist');

        $response->assertNotFound();
        $response->assertSee('We cannot find that page', false);
        $response->assertSee('ZephryxLabs', false);
    }

    public function test_a_deferred_module_says_it_is_not_built_rather_than_missing(): void
    {
        // §12 keeps Leads and Calendar in the navigation until v2. Someone who
        // clicked a link we chose to show them should not be told the page does
        // not exist — that reads as a broken application, not a decision.
        foreach (['/leads' => 'Leads', '/calendar' => 'Calendar'] as $url => $label) {
            $response = $this->get($url);

            $response->assertNotFound();
            $response->assertSee($label.' is not built yet', false);
            $response->assertDontSee('We cannot find that page', false);
        }
    }

    public function test_the_404_escapes_the_path_it_echoes_back(): void
    {
        // A 404 page is a classic place to reflect attacker-controlled input
        // straight back into the browser.
        $response = $this->get('/'.urlencode('<script>alert(1)</script>'));

        $response->assertNotFound();
        $response->assertDontSee('<script>alert(1)</script>', false);
    }

    /**
     * Rendered directly rather than through the preview route, which exists
     * only in local + debug — see the last test in this file.
     */
    protected function render(int $code): string
    {
        return view("errors.{$code}")->render();
    }

    public function test_the_403_does_not_say_what_would_have_granted_access(): void
    {
        // Naming the permission or the role tells someone probing the system
        // how it is put together (§5).
        $html = $this->render(403);

        $this->assertStringContainsString('You do not have access to this', $html);
        $this->assertStringNotContainsString('permission_key', $html);
        $this->assertStringNotContainsString('role_permissions', $html);
    }

    public function test_the_419_explains_the_session_rather_than_saying_page_expired(): void
    {
        // The most likely error page in the application: every form carries a
        // CSRF token and sessions time out after 12 hours.
        $html = $this->render(419);

        $this->assertStringContainsString('Your session expired', $html);
        $this->assertStringContainsString(url('/login'), $html);
        $this->assertStringNotContainsString('Page Expired', $html);
    }

    public function test_the_500_leaks_nothing_about_the_failure(): void
    {
        $html = $this->render(500);

        $this->assertStringContainsString('Something went wrong at our end', $html);
        $this->assertStringNotContainsString('Exception', $html);
        $this->assertStringNotContainsString('vendor/laravel', $html);
    }

    public function test_every_error_page_is_themed_and_not_indexed(): void
    {
        foreach ([403, 404, 419, 429, 500, 503] as $code) {
            $html = $this->render($code);

            $this->assertStringContainsString('noindex, nofollow', $html, "{$code} is indexable");
            $this->assertStringContainsString('data-theme="dark"', $html, "{$code} has no theme");
        }
    }

    public function test_error_pages_render_nothing_the_content_security_policy_would_block(): void
    {
        foreach ([403, 404, 419, 429, 500, 503] as $code) {
            $html = $this->render($code);

            $this->assertSame(0, preg_match_all('/<style[\s>]/i', $html), "inline <style> in {$code}");
            $this->assertSame(0, preg_match_all('/\sstyle="/i', $html), "inline style attribute in {$code}");
            $this->assertSame(0, preg_match_all('/\son[a-z]+="/i', $html), "inline event handler in {$code}");
            $this->assertSame(0, preg_match_all('/<script(?![^>]*\ssrc=)/i', $html), "inline <script> in {$code}");
        }
    }

    public function test_the_generic_pages_offer_support_rather_than_the_landing_page(): void
    {
        // Someone on an error page has already found the thing that did not
        // work; sending them back to the start just makes them find it again.
        foreach ([404, 500] as $code) {
            $html = $this->render($code);

            $this->assertStringContainsString('Contact Support', $html);
            $this->assertStringContainsString('mailto:'.config('zephryx.support.email'), $html);
            $this->assertStringNotContainsString('Back to start', $html);
        }
    }

    public function test_the_support_subject_carries_the_status_code(): void
    {
        // So a reply does not have to start by asking what they saw.
        $this->assertStringContainsString(
            rawurlencode('error 404'),
            $this->render(404)
        );
    }

    public function test_every_error_page_offers_a_way_out(): void
    {
        // An error page with no exit is a dead end; the shell is not rendered
        // here, so these links are the only navigation on the page.
        foreach ([403, 404, 419, 429, 500, 503] as $code) {
            $this->assertMatchesRegularExpression(
                '/class="btn btn-(primary|outline)"/',
                $this->render($code),
                "no way out of the {$code} page"
            );
        }
    }

    public function test_the_preview_route_is_absent_outside_local_debug(): void
    {
        /*
         * The whole reason it is gated. Registered in production it would let
         * anyone show staff a convincing "session expired" or "maintenance"
         * page at a URL of their choosing — a ready-made phishing surface.
         *
         * The suite runs as `testing`, so its absence here is the gate working.
         */
        $this->assertFalse(app('router')->has('dev.errors'));
    }
}
