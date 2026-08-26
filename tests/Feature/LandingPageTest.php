<?php

namespace Tests\Feature;

use App\Support\Theme;
use Tests\TestCase;

/**
 * GET / — foundation spec §9.1.
 */
class LandingPageTest extends TestCase
{
    public function test_it_renders_for_an_anonymous_visitor(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Build Stronger', false);
        $response->assertSee('Relationships.', false);
        $response->assertSee('ZephryxLabs', false);
    }

    public function test_it_offers_login_and_support_calls_to_action(): void
    {
        $response = $this->get('/');

        $response->assertSee('href="/login"', false);
        $response->assertSee('mailto:'.config('zephryx.support.email'), false);
    }

    public function test_it_shows_the_feature_strip_and_not_the_invented_statistics(): void
    {
        $response = $this->get('/');

        $response->assertSee('Real-time', false);
        $response->assertSee('Role-based', false);
        $response->assertSee('Encrypted &amp; audited', false);

        // Removed by §9.1: unverifiable figures and an unaudited compliance
        // claim made in the company's name.
        $response->assertDontSee('50+', false);
        $response->assertDontSee('Happy Clients', false);
        $response->assertDontSee('More Productivity', false);
        $response->assertDontSee('GDPR', false);
    }

    public function test_it_carries_no_third_party_assets(): void
    {
        $html = $this->get('/')->getContent();

        // Spec §6: everything is served from our own origin.
        $this->assertStringNotContainsString('unpkg.com', $html);
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringNotContainsString('fonts.gstatic.com', $html);
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $html);
    }

    public function test_it_renders_nothing_the_content_security_policy_would_block(): void
    {
        $html = $this->get('/')->getContent();

        // style-src / script-src are 'self' with no 'unsafe-inline' (spec §6).
        // Anything inline here would be dropped by the browser rather than
        // failing loudly, so it is worth pinning down.
        $this->assertSame(0, preg_match_all('/<style[\s>]/i', $html), 'inline <style> block');
        $this->assertSame(0, preg_match_all('/\sstyle="/i', $html), 'inline style attribute');
        $this->assertSame(0, preg_match_all('/\son[a-z]+="/i', $html), 'inline event handler');
        $this->assertSame(0, preg_match_all('/<script(?![^>]*\ssrc=)/i', $html), 'inline <script> block');
    }

    public function test_it_is_not_indexed(): void
    {
        // Internal software, not a marketing surface (spec §1 non-goals).
        $this->get('/')->assertSee('noindex, nofollow', false);
    }

    public function test_it_renders_dark_by_default(): void
    {
        $this->get('/')->assertSee('data-theme="dark"', false);
    }

    public function test_it_honours_a_stored_light_preference(): void
    {
        $this->withUnencryptedCookie(Theme::COOKIE, 'light')
            ->get('/')
            ->assertSee('data-theme="light"', false);
    }

    public function test_a_tampered_theme_cookie_cannot_reach_the_markup(): void
    {
        $response = $this->withUnencryptedCookie(
            Theme::COOKIE,
            '"><script>alert(1)</script>'
        )->get('/');

        $response->assertOk();
        $response->assertSee('data-theme="dark"', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
    }
}
