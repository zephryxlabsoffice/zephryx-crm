<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Support\TicketDirectory;
use Tests\TestCase;

/**
 * Tickets — five lists and one overview.
 *
 * The comment-visibility tests below are the important ones in this file. A
 * client reading an internal note is the worst failure this module can have,
 * and it is the kind that ships quietly.
 */
class TicketsPageTest extends TestCase
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
    /**
     * The demo tickets and their threads, as real rows.
     *
     * The environment flip this replaced did nothing once the module read the
     * `tickets` table: there was no row to find, and a test "passing" against
     * an empty page asserts the empty state while claiming to assert the list.
     */
    protected function withDemoData(): void
    {
        $this->seedDemoWorkforce();
    }

    protected function ticket(string $reference): Ticket
    {
        return Ticket::where('reference', $reference)->firstOrFail();
    }

    public function test_the_six_pages_render(): void
    {
        $this->withDemoData();

        $this->get('/tickets')->assertOk()->assertSee('Ticket Management', false);
        $this->get('/tickets/mine')->assertOk()->assertSee('My Tickets', false);
        $this->get('/tickets/assigned')->assertOk()->assertSee('Assigned to Me', false);
        $this->get('/tickets/projects')->assertOk()->assertSee('My Project Tickets', false);
        $this->get('/tickets/escalated')->assertOk()->assertSee('Escalated Tickets', false);
        $this->get('/tickets/TKT-2026-151')->assertOk()->assertSee('Website not loading on mobile', false);
    }

    public function test_the_personal_routes_are_not_read_as_ticket_references(): void
    {
        foreach (['mine', 'assigned', 'projects', 'escalated'] as $segment) {
            $this->get('/tickets/'.$segment)->assertOk();
        }
    }

    /* ───────────────────────  comment visibility  ─────────────────────── */

    public function test_the_client_audience_never_receives_internal_notes(): void
    {
        $this->withDemoData();

        $ticket = $this->ticket('TKT-2026-151');

        $staff = TicketDirectory::commentsFor($ticket, TicketDirectory::AUDIENCE_STAFF);
        $client = TicketDirectory::commentsFor($ticket, TicketDirectory::AUDIENCE_CLIENT);

        // The sample thread must actually contain an internal note, or this
        // test proves nothing.
        $this->assertNotEmpty(array_filter($staff, fn ($c) => $c['visibility'] === 'internal'));

        foreach ($client as $comment) {
            $this->assertSame('public', $comment['visibility'], 'an internal note reached the client audience');
        }

        $this->assertLessThan(count($staff), count($client));
    }

    public function test_the_client_audience_never_receives_the_internal_wording(): void
    {
        // Not just the flag — the actual text must be absent, in case a future
        // change filters on the wrong field.
        $this->withDemoData();

        $client = TicketDirectory::commentsFor($this->ticket('TKT-2026-151'), TicketDirectory::AUDIENCE_CLIENT);
        $bodies = implode(' ', array_column($client, 'body'));

        $this->assertStringNotContainsString('Not telling them', $bodies);
        $this->assertStringNotContainsString('CDN rule', $bodies);
    }

    public function test_an_unmarked_comment_is_treated_as_internal(): void
    {
        // Failing towards secrecy: a missing or unrecognised visibility must
        // never default to something a client can read.
        $this->assertTrue(\App\Support\TicketPresenter::isInternal(['body' => 'x']));
        $this->assertTrue(\App\Support\TicketPresenter::isInternal(['visibility' => 'draft']));
        $this->assertFalse(\App\Support\TicketPresenter::isInternal(['visibility' => 'public']));
    }

    public function test_internal_notes_are_labelled_in_words_not_only_colour(): void
    {
        $this->withDemoData();

        $html = $this->get('/tickets/TKT-2026-151')->getContent();

        $this->assertStringContainsString('Internal only', $html);
        $this->assertStringContainsString('Client can see', $html);
        $this->assertStringContainsString('is-internal', $html);
    }

    public function test_the_composer_offers_two_verbs_rather_than_a_default(): void
    {
        // A toggle has a default, and a default is a thing to get wrong on the
        // one occasion it matters.
        $this->withDemoData();

        $html = $this->get('/tickets/TKT-2026-151')->getContent();

        $this->assertStringContainsString('Reply to client', $html);
        $this->assertStringContainsString('Internal note', $html);
        $this->assertStringContainsString('name="visibility" value="public"', $html);
        $this->assertStringContainsString('name="visibility" value="internal"', $html);
    }

    public function test_an_internal_ticket_offers_no_client_reply(): void
    {
        // There is no client on the ticket, so a "reply to client" button would
        // be a lie about who is reading.
        $this->withDemoData();

        $html = $this->get('/tickets/TKT-2026-152')->getContent();

        $this->assertStringNotContainsString('Reply to client', $html);
        $this->assertStringContainsString('Internal note', $html);
        $this->assertStringContainsString('there is no client to see it', $html);
    }

    public function test_a_client_ticket_carries_a_standing_warning(): void
    {
        $this->withDemoData();

        $this->get('/tickets/TKT-2026-151')->assertSee('A client reads this ticket', false);
        $this->get('/tickets/TKT-2026-152')->assertDontSee('A client reads this ticket', false);
    }

    /* ───────────────────────  queues and triage  ─────────────────────── */

    public function test_the_tabs_filter_the_queue(): void
    {
        $this->withDemoData();

        $unassigned = $this->get('/tickets?tab=unassigned');
        $unassigned->assertSee('/tickets/TKT-2026-149', false);
        $unassigned->assertDontSee('/tickets/TKT-2026-152', false);

        $escalated = $this->get('/tickets?tab=escalated');
        $escalated->assertSee('/tickets/TKT-2026-148', false);
        $escalated->assertDontSee('/tickets/TKT-2026-152', false);
    }

    public function test_an_invalid_tab_is_rejected(): void
    {
        $this->withDemoData();

        $this->get('/tickets?tab=everything')->assertSessionHasErrors('tab');
    }

    public function test_triage_appears_only_when_the_ticket_needs_it(): void
    {
        $this->withDemoData();

        // Unassigned and escalated both need routing.
        $this->get('/tickets/TKT-2026-149')->assertSee('Triage this ticket', false);
        $this->get('/tickets/TKT-2026-148')->assertSee('Review and reassign', false);

        // One already with someone does not.
        $this->get('/tickets/TKT-2026-152')->assertDontSee('Triage this ticket', false);
    }

    public function test_escalation_is_a_flat_queue(): void
    {
        // Decided 2026-08-27: one shared review queue, not an L1→L2→L3 ladder.
        // Nothing on a ticket records a level, and nothing should start to.
        $this->withDemoData();

        foreach (Ticket::escalated()->get() as $ticket) {
            $this->assertSame('escalated', $ticket['status']);
            $this->assertArrayNotHasKey('escalation_level', $ticket);
        }
    }

    public function test_untriaged_fields_say_not_set_rather_than_rendering_blank(): void
    {
        // A blank cell hides the fact that something still has to be done.
        $this->withDemoData();

        $this->get('/tickets/TKT-2026-149')->assertSee('Not set', false);
    }

    /* ───────────────────────  the usual guards  ─────────────────────── */

    public function test_an_empty_database_produces_an_empty_module(): void
    {
        /*
         * This replaced "the demo source is inert outside local + debug", which
         * was true only because the fixture switched itself off. Tickets come
         * from a table now, and in production real ones SHOULD be shown.
         *
         * What survives is the guarantee underneath it: nothing is invented.
         */
        $this->assertTrue(Ticket::all()->isEmpty());
        $this->assertNull(TicketDirectory::find('TKT-2026-151'));
        $this->assertSame(0, TicketDirectory::stats()['unassigned']);
    }

    public function test_there_is_no_way_to_read_a_thread_without_naming_the_audience(): void
    {
        /*
         * The contract of the module, checked as a signature rather than as
         * behaviour: a caller cannot ask for "the comments" and get internal
         * notes by accident, because the argument has no default.
         */
        $method = new \ReflectionMethod(TicketDirectory::class, 'commentsFor');

        $this->assertSame(2, $method->getNumberOfRequiredParameters());
        $this->assertSame('audience', $method->getParameters()[1]->getName());
    }

    public function test_no_figure_is_written_into_the_markup(): void
    {
        // The handover hardcoded 152 / 12 / 8 / 54 / 31.
        $response = $this->get('/tickets');

        $response->assertSee('Total Tickets', false);
        $response->assertDontSee('>152<', false);
    }

    public function test_an_unknown_ticket_is_not_found(): void
    {
        $this->withDemoData();

        $this->get('/tickets/TKT-9999')->assertNotFound();
        $this->get('/tickets/'.urlencode('<script>'))->assertNotFound();
    }

    public function test_the_write_routes_exist_so_the_forms_are_real(): void
    {
        // Named now so the composer is a real form with a CSRF token, and the
        // visibility choice is a submitted value from day one.
        $this->assertTrue(app('router')->has('tickets.comment'));
        $this->assertTrue(app('router')->has('tickets.triage'));
    }

    public function test_the_pages_render_nothing_the_content_security_policy_would_block(): void
    {
        $this->withDemoData();

        foreach (['/tickets', '/tickets/mine', '/tickets/escalated', '/tickets/TKT-2026-151'] as $url) {
            $html = $this->get($url)->getContent();

            $this->assertSame(0, preg_match_all('/<style[\s>]/i', $html), "inline <style> in {$url}");
            $this->assertSame(0, preg_match_all('/\sstyle="/i', $html), "inline style attribute in {$url}");
            $this->assertSame(0, preg_match_all('/\son[a-z]+="/i', $html), "inline event handler in {$url}");
            $this->assertSame(0, preg_match_all('/<script(?![^>]*\ssrc=)/i', $html), "inline <script> in {$url}");
        }
    }

    public function test_the_sidebar_marks_tickets_as_current(): void
    {
        $this->withDemoData();

        foreach (['/tickets', '/tickets/mine', '/tickets/escalated', '/tickets/TKT-2026-151'] as $url) {
            $this->assertSame(
                1,
                substr_count($this->get($url)->getContent(), 'class="sb-link active"'),
                "sidebar current marker wrong on {$url}"
            );
        }
    }
}
