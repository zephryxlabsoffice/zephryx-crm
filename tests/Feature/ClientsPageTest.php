<?php

namespace Tests\Feature;

use App\Support\Demo\DemoClients;
use Tests\TestCase;

/**
 * Clients — GET /clients.
 *
 * There is no `clients` table yet. Rows come from DemoClients, which is inert
 * outside local + debug, so most of these run against the empty state; the
 * ones that need rows force the demo source on.
 */
class ClientsPageTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Every route in the staff realm is behind `realm:staff` now (§3.1), so
         * a page test has to be somebody. A CEO, because this file is about
         * what the page renders rather than about who may see it — the guard
         * and the permission filtering have their own tests.
         */
        $this->signInAsStaff();
    }
    protected function withDemoData(): void
    {
        // DemoClients keys off environment + debug; the suite runs as `testing`.
        $this->app->detectEnvironment(fn () => 'local');
        config(['app.debug' => true]);
    }

    public function test_it_renders_inside_the_app_shell(): void
    {
        $response = $this->get('/clients');

        $response->assertOk();
        $response->assertSee('Client Management', false);
        $response->assertSee('class="sidebar"', false);
    }

    public function test_the_sidebar_marks_clients_as_current(): void
    {
        $response = $this->get('/clients');

        $response->assertSee('aria-current="page"', false);
        $this->assertSame(1, substr_count($response->getContent(), 'aria-current="page"'));
    }

    public function test_it_shows_an_empty_state_when_there_are_no_clients(): void
    {
        // The real state of a deployed site today, and what production shows.
        $response = $this->get('/clients');

        $response->assertSee('No clients yet.', false);
        $response->assertDontSee('DGL International School', false);
    }

    public function test_no_figure_is_written_into_the_markup(): void
    {
        // The handover hardcoded 58+, 42, ₹1,85,000 and 12 tickets. With no
        // data the page must read zero, not carry invented numbers.
        $response = $this->get('/clients');

        $response->assertSee('Total Clients', false);
        $response->assertDontSee('58+', false);
        $response->assertDontSee('1,85,000', false);
    }

    public function test_the_demo_source_is_inert_outside_local_debug(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.debug' => false]);

        $this->assertFalse(DemoClients::enabled());
        $this->assertTrue(DemoClients::all()->isEmpty());
        $this->assertSame([], DemoClients::activity());
        $this->assertSame([], DemoClients::meetings());
    }

    public function test_it_lists_clients_and_paginates_them(): void
    {
        $this->withDemoData();

        $first = $this->get('/clients');
        $first->assertSee('DGL International School', false);
        $first->assertSee('Showing 1 to 7 of 10 clients', false);
        // Page size is 7, so the eighth client is not on page one.
        $first->assertDontSee('Urban Nest Interiors', false);

        $second = $this->get('/clients?page=2');
        $second->assertSee('Urban Nest Interiors', false);
        $second->assertSee('Showing 8 to 10 of 10 clients', false);
    }

    public function test_search_filters_the_list(): void
    {
        $this->withDemoData();

        $response = $this->get('/clients?q=education');

        // Asserted on the row links, not the names: the activity rail shows
        // recent events across every client and is deliberately not filtered
        // by the table's search, so the name alone is still on the page.
        $response->assertSee('/clients/dgl-international-school', false);
        $response->assertSee('/clients/bright-future-academy', false);
        $response->assertDontSee('/clients/technova-solutions', false);
    }

    public function test_a_search_with_no_matches_offers_a_way_back(): void
    {
        $this->withDemoData();

        $response = $this->get('/clients?q=zzzznothing');

        $response->assertSee('No clients match that search.', false);
        $response->assertSee('clear the filters', false);
    }

    public function test_status_filtering_works(): void
    {
        $this->withDemoData();

        $response = $this->get('/clients?status=completed');

        $response->assertSee('/clients/medicare-services', false);
        $response->assertDontSee('/clients/technova-solutions', false);
    }

    public function test_an_invalid_status_is_rejected_rather_than_ignored(): void
    {
        $this->withDemoData();

        $this->get('/clients?status=;DROP TABLE')->assertSessionHasErrors('status');
    }

    public function test_filters_survive_paging(): void
    {
        // Paging through a filtered list must not silently reset the filter.
        $this->withDemoData();

        $html = $this->get('/clients?q=e&page=1')->getContent();

        $this->assertStringContainsString('q=e', $html);
    }

    public function test_the_page_renders_nothing_the_content_security_policy_would_block(): void
    {
        $this->withDemoData();

        $html = $this->get('/clients')->getContent();

        $this->assertSame(0, preg_match_all('/<style[\s>]/i', $html), 'inline <style> block');
        $this->assertSame(0, preg_match_all('/\sstyle="/i', $html), 'inline style attribute');
        $this->assertSame(0, preg_match_all('/\son[a-z]+="/i', $html), 'inline event handler');
        $this->assertSame(0, preg_match_all('/<script(?![^>]*\ssrc=)/i', $html), 'inline <script> block');
    }

    public function test_the_table_keeps_its_semantics_for_the_mobile_card_layout(): void
    {
        // Below 760px the CSS sets `display: block` on the table, which drops
        // the implicit ARIA roles; the markup states them so it still reads as
        // a table either way.
        $this->withDemoData();

        $html = $this->get('/clients')->getContent();

        $this->assertStringContainsString('role="table"', $html);
        $this->assertStringContainsString('role="columnheader"', $html);
        $this->assertStringContainsString('data-label="Industry"', $html);
    }

    public function test_export_is_not_pretending_to_work(): void
    {
        $this->get('/clients')->assertSee('Export is not built yet', false);
    }
}
