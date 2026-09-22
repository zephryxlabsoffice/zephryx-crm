<?php

namespace Tests\Feature;

use App\Support\SupportContact;
use Tests\TestCase;

/**
 * The Support page (plan doc §Support).
 *
 * One page, two buttons — raise a ticket, or email us. Nothing here owns a
 * write of its own: Tickets already owns raising one, and SupportContact
 * already owns the mailto address. What is worth testing is that both links
 * are actually wired to the real thing, and that the page is reachable by
 * both an ordinary employee and a Mentor — the one role with no Employee
 * base (§2.1), which is exactly the case a permission gate could get wrong.
 */
class SupportPageTest extends TestCase
{
    public function test_the_page_renders_for_an_employee(): void
    {
        $this->signInAsStaff(['employee']);

        $this->get('/support')->assertOk();
    }

    public function test_the_page_renders_for_a_mentor(): void
    {
        // Mentor holds no Employee base at all — everything it can reach is
        // granted explicitly, the same way dashboard.view is. support.view
        // follows that exact precedent (RbacSeeder's mentor definition).
        $this->signInAsMentor();

        $this->get('/support')->assertOk();
    }

    public function test_it_links_to_raising_a_ticket_and_to_the_support_address(): void
    {
        $this->signInAsStaff(['employee']);

        $body = $this->get('/support')->getContent();

        $this->assertStringContainsString(route('tickets.create'), $body);
        $this->assertStringContainsString(SupportContact::mailto(), $body);
    }

    public function test_it_renders_nothing_the_content_security_policy_would_block(): void
    {
        $this->signInAsStaff(['employee']);

        $html = $this->get('/support')->getContent();

        $this->assertSame(0, preg_match_all('/<style[\s>]/i', $html), 'inline <style>');
        $this->assertSame(0, preg_match_all('/\sstyle="/i', $html), 'inline style attribute');
        $this->assertSame(0, preg_match_all('/\son[a-z]+="/i', $html), 'inline event handler');
        $this->assertSame(0, preg_match_all('/<script(?![^>]*\ssrc=)/i', $html), 'inline <script>');
    }
}
