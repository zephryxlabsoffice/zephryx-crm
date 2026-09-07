<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\AccountStatusController as C;
use App\Support\Realm;
use Tests\TestCase;

/**
 * The closed-account page — ex-employees and former clients.
 *
 * Most of what matters here is about REACHABILITY, not content. The page says
 * something §4.2 forbids the login form from saying, and it is only allowed to
 * because it renders one step later, to somebody whose password was correct.
 * The tests that guard that ordering are the ones worth having.
 */
class AccountInactivePageTest extends TestCase
{
    protected function withDemoData(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config(['app.debug' => true]);
    }

    public function test_it_is_not_reachable_by_typing_the_url(): void
    {
        /*
         * The enumeration guard. If this page rendered for anybody who asked,
         * "does /account/inactive load?" would become the same question the
         * login form refuses to answer, just asked in a different shape.
         */
        $this->get('/account/inactive')->assertRedirect(route('login'));
    }

    public function test_it_renders_when_the_login_flow_has_said_so(): void
    {
        $this->withSession([C::SESSION_KEY => Realm::STAFF])
            ->get('/account/inactive')
            ->assertOk()
            ->assertSee('Your account is closed');
    }

    public function test_a_client_gets_the_client_wording(): void
    {
        $this->withSession([C::SESSION_KEY => Realm::CLIENT])
            ->get('/account/inactive')
            ->assertOk()
            ->assertSee('This account is closed')
            ->assertSee('client portal');
    }

    public function test_a_tampered_session_value_is_refused(): void
    {
        // The flag is checked against the allowed kinds, not trusted. Anything
        // else is treated as no flag at all.
        $this->withSession([C::SESSION_KEY => 'admin'])
            ->get('/account/inactive')
            ->assertRedirect(route('login'));

        $this->withSession([C::SESSION_KEY => '"><script>alert(1)</script>'])
            ->get('/account/inactive')
            ->assertRedirect(route('login'));
    }

    public function test_it_offers_no_way_back_in(): void
    {
        $html = $this->withSession([C::SESSION_KEY => Realm::STAFF])
            ->get('/account/inactive')
            ->getContent();

        /*
         * No "try again", no password reset, no sign-in link. Each of those
         * implies the state is a mistake this page can undo, and none of them
         * is true — the account is closed, not locked out.
         */
        $this->assertStringNotContainsString(route('login'), $html);
        $this->assertStringNotContainsString(route('password.forgot'), $html);
        $this->assertStringNotContainsString('Try again', $html);
    }

    public function test_the_only_action_is_a_mailto_and_not_a_form(): void
    {
        // A contact form here would be an unauthenticated write endpoint,
        // reachable by anybody whose account has just been closed (§6, §13.3).
        $html = $this->withSession([C::SESSION_KEY => Realm::STAFF])
            ->get('/account/inactive')
            ->getContent();

        $this->assertStringContainsString('mailto:', $html);

        // The theme toggle is a form and is meant to be — it is the layout's,
        // it writes a cookie and nothing else. Nothing on the card itself
        // submits anywhere.
        preg_match_all('/<form[^>]*action="([^"]*)"/i', $html, $forms);

        $this->assertSame([route('theme.store')], array_unique($forms[1]));
    }

    public function test_it_names_nobody(): void
    {
        // Nothing about the person needs to reach this page for it to say what
        // it says, so nothing about the person is put in the session.
        $this->withSession([C::SESSION_KEY => Realm::STAFF])
            ->get('/account/inactive')
            ->assertDontSee('Amit', false)
            ->assertDontSee('EMP', false);
    }

    public function test_it_is_not_indexed(): void
    {
        $this->withSession([C::SESSION_KEY => Realm::STAFF])
            ->get('/account/inactive')
            ->assertSee('noindex, nofollow', false);
    }

    public function test_it_renders_nothing_the_content_security_policy_would_block(): void
    {
        $html = $this->withSession([C::SESSION_KEY => Realm::STAFF])
            ->get('/account/inactive')
            ->getContent();

        $this->assertSame(0, preg_match_all('/<style[\s>]/i', $html), 'inline <style> block');
        $this->assertSame(0, preg_match_all('/\sstyle="/i', $html), 'inline style attribute');
        $this->assertSame(0, preg_match_all('/\son[a-z]+="/i', $html), 'inline event handler');
        $this->assertSame(0, preg_match_all('/<script(?![^>]*\ssrc=)/i', $html), 'inline <script> block');

        // The handover pulled Plus Jakarta Sans from Google. §6 forbids CDN
        // assets and the CSP would drop the stylesheet silently.
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
    }

    public function test_the_preview_route_exists_only_in_local_debug(): void
    {
        // Anywhere else it would let somebody show a convincing "your account
        // is closed" at a URL of their choosing.
        $this->assertFalse(app('router')->has('dev.account'));
    }

    public function test_the_preview_renders_both_kinds(): void
    {
        $this->withDemoData();

        /*
         * Routes are registered at boot, so the local-only group is not
         * registered inside this test process. Rendering the controller's
         * preview directly checks the same thing the route would.
         */
        $controller = new \App\Http\Controllers\Auth\AccountStatusController;

        $this->assertSame(200, $controller->preview(Realm::STAFF)->getStatusCode());
        $this->assertSame(200, $controller->preview(Realm::CLIENT)->getStatusCode());
    }
}
