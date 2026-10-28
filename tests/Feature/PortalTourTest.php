<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The client portal tour (owner, 2026-10-28): once on the first visit, again
 * on request, and never for staff.
 */
class PortalTourTest extends TestCase
{
    public function test_a_client_gets_the_tour_on_their_first_visit_only(): void
    {
        $client = $this->signInAsClient();

        $html = $this->get('/client/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('Welcome to your portal', $html);
        foreach (['Projects', 'Invoices', 'Support Tickets', 'Meetings', 'Announcements', 'My Profile'] as $stop) {
            $this->assertStringContainsString($stop, $html);
        }
        $this->assertStringContainsString('Step 7 of 7', $html);
        $this->assertNotNull($client->fresh()->portal_tour_seen_at);

        $this->get('/client/dashboard')->assertOk()->assertDontSee('Welcome to your portal');
    }

    public function test_take_the_tour_brings_it_back(): void
    {
        $this->signInAsClient();
        $this->get('/client/dashboard');

        $this->get('/client/dashboard')->assertSee('Take the tour');
        $this->get('/client/dashboard?tour=1')->assertOk()->assertSee('Welcome to your portal');
    }

    public function test_staff_never_get_the_client_tour(): void
    {
        $this->signInAsStaff(['employee']);

        $this->get('/dashboard')->assertOk()->assertDontSee('Welcome to your portal');
        $this->get('/dashboard?tour=1')->assertOk()->assertDontSee('Welcome to your portal');
    }
}
