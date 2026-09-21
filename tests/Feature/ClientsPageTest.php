<?php

namespace Tests\Feature;

use App\Models\Client;
use Tests\TestCase;

/**
 * Clients — GET /clients.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * `withDemoData()` IS GONE, AND THAT IS THE POINT OF THIS EDIT
 *
 * These tests used to make content appear by flipping the environment to
 * local + debug, because DemoClients gated on exactly that. The page reads the
 * `clients` table now, so that flip does nothing: there is no row to find, and a
 * test "passing" against an empty page asserts the empty state while claiming to
 * assert the list.
 *
 * So the demo clients are seeded properly, through the same seeder that
 * populates a developer's machine — see TestCase::seedDemoWorkforce.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class ClientsPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Every route in the staff realm is behind `realm:staff` (§3.1), and
         * the list is behind `clients.view` as well now. A CEO, because this
         * file is about what the page renders rather than about who may see it
         * — the guard has its own tests in ClientWritesTest.
         */
        $this->signInAsStaff();
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
        // The real state of a freshly deployed site: the production seeder
        // creates no clients, because inventing one would be inventing a
        // customer.
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

    public function test_receivables_and_tickets_read_zero_until_those_modules_exist(): void
    {
        /*
         * Three of the four KPIs are questions for Invoices and Tickets. They
         * report zero rather than the demo source's ₹1,85,000 — a figure on a
         * page nobody can trace to a record is worse than an empty one, because
         * somebody will quote it.
         */
        $this->seedDemoWorkforce();

        $body = $this->pageBody('/clients');

        $this->assertStringNotContainsString('1,85,000', $body);
        $this->assertStringContainsString('Nothing overdue', $body);
    }

    public function test_it_lists_clients_and_paginates_them(): void
    {
        $this->seedDemoWorkforce();

        $first = $this->get('/clients');
        $first->assertSee('ABC Pvt Ltd', false);
        $first->assertSee('Showing 1 to 7 of 10 clients', false);

        $second = $this->get('/clients?page=2');
        $second->assertSee('Showing 8 to 10 of 10 clients', false);
    }

    public function test_the_list_is_ordered_by_name(): void
    {
        // Alphabetical, because the name is the column people scan. The
        // reference is an identifier, not an order anybody reads in.
        $this->seedDemoWorkforce();

        $body = $this->pageBody('/clients');

        $this->assertLessThan(
            strpos($body, 'Bright Future Academy'),
            strpos($body, 'ABC Pvt Ltd'),
        );
    }

    public function test_a_row_links_by_reference_and_not_by_a_slug_of_the_name(): void
    {
        /*
         * A URL built from a name breaks the day somebody renames the company,
         * and two clients sharing a name would share a URL. The reference is
         * the identifier; the name is a label on it.
         */
        $this->seedDemoWorkforce();

        $client = Client::where('name', 'ABC Pvt Ltd')->firstOrFail();
        $body = $this->pageBody('/clients');

        $this->assertStringContainsString('/clients/'.$client->reference, $body);
        $this->assertStringNotContainsString('/clients/abc-pvt-ltd', $body);
    }

    public function test_search_filters_the_list(): void
    {
        $this->seedDemoWorkforce();

        $education = Client::where('industry', 'Education')->pluck('reference');
        $software = Client::where('industry', 'Software')->pluck('reference');

        $body = $this->pageBody('/clients?q=education');

        foreach ($education as $reference) {
            $this->assertStringContainsString('/clients/'.$reference, $body);
        }

        foreach ($software as $reference) {
            $this->assertStringNotContainsString('/clients/'.$reference, $body);
        }
    }

    public function test_a_search_with_no_matches_offers_a_way_back(): void
    {
        $this->seedDemoWorkforce();

        $response = $this->get('/clients?q=zzzznothing');

        $response->assertSee('No clients match that search.', false);
        $response->assertSee('clear the filters', false);
    }

    public function test_status_filtering_works(): void
    {
        $this->seedDemoWorkforce();

        $inactive = Client::where('status', 'inactive')->firstOrFail();
        $active = Client::where('status', 'active')->firstOrFail();

        $body = $this->pageBody('/clients?status=inactive');

        $this->assertStringContainsString('/clients/'.$inactive->reference, $body);
        $this->assertStringNotContainsString('/clients/'.$active->reference, $body);
    }

    public function test_an_invalid_status_is_rejected_rather_than_ignored(): void
    {
        $this->get('/clients?status=;DROP TABLE')->assertSessionHasErrors('status');
    }

    public function test_a_search_term_with_wildcards_searches_for_itself(): void
    {
        /*
         * `%` and `_` are LIKE wildcards. Unescaped, searching for "%" would
         * return every client — which reads as a broken filter rather than as
         * the injection-adjacent bug it is.
         */
        $this->seedDemoWorkforce();

        $this->get('/clients?q=%')->assertSee('No clients match that search.', false);
    }

    public function test_filters_survive_paging(): void
    {
        // Paging through a filtered list must not silently reset the filter.
        $this->seedDemoWorkforce();

        $html = $this->get('/clients?q=e&page=1')->getContent();

        $this->assertStringContainsString('q=e', $html);
    }

    public function test_the_page_renders_nothing_the_content_security_policy_would_block(): void
    {
        $this->seedDemoWorkforce();

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
        $this->seedDemoWorkforce();

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
