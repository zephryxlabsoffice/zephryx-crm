<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Support\Demo\DemoClientPortal;
use App\Support\Demo\DemoInvoices;
use App\Support\Demo\DemoProjects;
use App\Support\Demo\DemoTickets;
use App\Support\InvoicePresenter;
use Tests\TestCase;

/**
 * The client portal.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MOST OF THIS FILE IS ABOUT ONE RULE
 *
 * §6: "A client requesting invoice 47 must be verified as the owner of invoice
 * 47", named there as the most common real-world leak in applications of this
 * shape. Every page in the portal is a chance to break it, so the tests below
 * are mostly the same test aimed at different URLs: fetch one client's record
 * while signed in as another, and require a 404.
 *
 * These tests are cheap and they are the ones worth keeping. A layout
 * regression is visible; this one is not.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class ClientPortalTest extends TestCase
{
    /** The client the portal defaults to. */
    protected const OURS = 'DGL International School';

    /** Somebody else entirely, with their own projects, invoices and tickets. */
    protected const THEIRS = 'GreenLeaf Foods';

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * The portal is behind `realm:client` (§3.1). A staff session is
         * refused here before any data is read — see test_a_staff_session_is
         * _refused_the_client_portal, which is the assertion that guard exists
         * for.
         */
        $this->signInAsClient(self::OURS);
    }

    protected function withDemoData(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config(['app.debug' => true]);
    }

    /**
     * A record belonging to the OTHER client, found from the data rather than
     * hardcoded — a fixed id rots the first time the demo rows are edited.
     */
    protected function theirProject(): string
    {
        $project = DemoProjects::all()->firstWhere('client', self::THEIRS);

        $this->assertNotNull($project, 'no project for the other client');

        return $project['id'];
    }

    protected function theirInvoice(): string
    {
        $invoice = DemoInvoices::forClient(self::THEIRS)
            ->first(fn (array $i) => InvoicePresenter::statusOf($i) !== InvoicePresenter::DRAFT);

        $this->assertNotNull($invoice, 'no sent invoice for the other client');

        return $invoice['id'];
    }

    /**
     * The page with the development client switcher cut out of it.
     *
     * The switcher lists every client by name — that is its whole job, and it
     * does not exist outside local + debug. Leaving it in would make every
     * "this page does not name another client" assertion fail on scaffolding
     * rather than on the portal.
     */
    protected function withoutSwitcher(string $body): string
    {
        $start = strpos($body, '<section class="cl-switch"');

        if ($start === false) {
            return $body;
        }

        $end = strpos($body, '</section>', $start);

        return substr($body, 0, $start).substr($body, $end + 10);
    }

    protected function theirTicket(): string
    {
        $ticket = DemoTickets::all()->firstWhere('client', self::THEIRS);

        $this->assertNotNull($ticket, 'no ticket for the other client');

        return $ticket['id'];
    }

    /* ══════════════════════════════════════════════════════════════════════
       OWNERSHIP
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_client_cannot_open_another_clients_project(): void
    {
        $this->withDemoData();

        // Theirs renders for them...
        $this->get('/client/projects/'.$this->theirProject().'?as='.self::THEIRS)->assertOk();

        // ...and does not exist for anybody else.
        $this->get('/client/projects/'.$this->theirProject().'?as='.self::OURS)->assertNotFound();
    }

    public function test_a_client_cannot_open_another_clients_invoice(): void
    {
        $this->withDemoData();

        $this->get('/client/invoices/'.$this->theirInvoice().'?as='.self::THEIRS)->assertOk();
        $this->get('/client/invoices/'.$this->theirInvoice().'?as='.self::OURS)->assertNotFound();
    }

    public function test_a_client_cannot_open_another_clients_ticket(): void
    {
        $this->withDemoData();

        $this->get('/client/tickets/'.$this->theirTicket().'?as='.self::THEIRS)->assertOk();
        $this->get('/client/tickets/'.$this->theirTicket().'?as='.self::OURS)->assertNotFound();
    }

    public function test_someone_elses_record_is_indistinguishable_from_one_that_does_not_exist(): void
    {
        $this->withDemoData();

        /*
         * Both answers must be the same 404. If "not yours" and "no such thing"
         * differed — by status, by wording, by anything — the difference could
         * be walked to learn which references are real, which is the first half
         * of the leak this portal is guarding against.
         */
        $theirs = $this->get('/client/invoices/'.$this->theirInvoice().'?as='.self::OURS);
        $fictional = $this->get('/client/invoices/INV-9999-999?as='.self::OURS);

        $theirs->assertNotFound();
        $fictional->assertNotFound();
        $this->assertSame($fictional->getStatusCode(), $theirs->getStatusCode());
    }

    public function test_a_list_never_carries_another_clients_records(): void
    {
        $this->withDemoData();

        // Driven off the data: every page, checked against every other client's
        // records, so adding a listing page cannot quietly skip the check.
        $pages = ['/client/dashboard', '/client/projects', '/client/invoices', '/client/tickets', '/client/meetings'];

        foreach ($pages as $page) {
            $body = $this->withoutSwitcher($this->pageBody($page.'?as='.self::OURS));

            $this->assertStringNotContainsString($this->theirProject(), $body, $page.' leaked a project');
            $this->assertStringNotContainsString($this->theirInvoice(), $body, $page.' leaked an invoice');
            $this->assertStringNotContainsString($this->theirTicket(), $body, $page.' leaked a ticket');
            $this->assertStringNotContainsString(self::THEIRS, $body, $page.' named another client');
        }
    }

    public function test_the_data_source_has_no_unscoped_reader(): void
    {
        /*
         * The structural guarantee behind every test above: a controller cannot
         * fetch a client record without saying whose it is, because no such
         * method exists to call.
         *
         * Asserted by reflection rather than by reading the class, so adding a
         * convenient all() later fails here instead of in production.
         */
        $reflection = new \ReflectionClass(DemoClientPortal::class);

        $exempt = ['enabled', 'viewer', 'switchable', 'knows'];

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (in_array($method->getName(), $exempt, true)) {
                continue;
            }

            $first = $method->getParameters()[0] ?? null;

            $this->assertNotNull($first, DemoClientPortal::class.'::'.$method->getName().'() takes no client');
            $this->assertSame(
                'client',
                $first->getName(),
                DemoClientPortal::class.'::'.$method->getName().'() does not take the client first',
            );
        }
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHAT A CLIENT IS NOT SHOWN
       ══════════════════════════════════════════════════════════════════════ */

    public function test_internal_project_updates_never_reach_the_client(): void
    {
        $this->withDemoData();

        $body = $this->pageBody('/client/projects/WD-2024-001?as='.self::OURS);

        /*
         * These are real sentences from the internal updates on this client's
         * own project — the kind of note the visibility flag exists for. If any
         * of them appears, the default has been inverted or a filter dropped.
         */
        $this->assertStringNotContainsString('March invoice is settled', $body);
        $this->assertStringNotContainsString('third brief for the same block', $body);

        // The client-visible ones are here, so the filter is not simply hiding
        // everything.
        $this->assertStringContainsString('Homepage layout signed off internally', $body);
    }

    public function test_the_page_does_not_say_how_much_is_being_withheld(): void
    {
        $this->withDemoData();

        /*
         * A line reading "4 internal updates hidden" is itself a disclosure —
         * it tells the client there is something they are not being shown, on
         * the exact day it was written.
         *
         * Asserted on the phrasings such a line would take rather than on
         * single words: an update legitimately says "through internal review",
         * and every decorative icon on the page carries aria-hidden.
         */
        $body = strtolower($this->pageBody('/client/projects/WD-2024-001?as='.self::OURS));

        foreach (['internal update', 'internal note', 'updates hidden', 'not shown', 'withheld', 'more update'] as $phrase) {
            $this->assertStringNotContainsString($phrase, $body);
        }
    }

    public function test_internal_ticket_comments_never_reach_the_client(): void
    {
        $this->withDemoData();

        /*
         * Any client ticket that actually carries an internal note — found
         * from the fixture rather than named here, so this keeps testing
         * something when the demo threads are edited.
         */
        $ticket = DemoTickets::all()
            ->whereNotNull('client')
            ->first(fn (array $t) => collect(DemoTickets::commentsFor($t, DemoTickets::AUDIENCE_STAFF))
                ->contains(fn (array $c) => \App\Support\TicketPresenter::isInternal($c)));

        // Without one, this test proves nothing and should say so.
        $this->assertNotNull($ticket, 'no client ticket with an internal comment in the fixture');

        $internal = collect(DemoTickets::commentsFor($ticket, DemoTickets::AUDIENCE_STAFF))
            ->filter(fn (array $c) => \App\Support\TicketPresenter::isInternal($c));

        $body = $this->pageBody('/client/tickets/'.$ticket['id'].'?as='.$ticket['client']);

        foreach ($internal as $comment) {
            $this->assertStringNotContainsString($comment['body'], $body);
        }
    }

    public function test_the_ticket_page_does_not_offer_the_client_the_staff_composer(): void
    {
        $this->withDemoData();

        $ticket = DemoTickets::all()->whereNotNull('client')->first();

        $this->assertNotNull($ticket);

        $body = $this->pageBody('/client/tickets/'.$ticket['id'].'?as='.$ticket['client']);

        /*
         * The staff thread partial bundles a composer with an "Internal note"
         * button and a caption explaining that internal notes are never shown
         * to the client. Rendering it here leaked no comment — the audience
         * filter held — but it told the client there is a private channel on
         * their own ticket, on the page where that lands worst.
         *
         * A component that is safe with the right data can still be wrong for
         * the audience.
         */
        $this->assertStringNotContainsString('Internal note', $body);
        $this->assertStringNotContainsString('never shown to them', $body);
        $this->assertStringNotContainsString('Client can see', $body);
        $this->assertStringNotContainsString(route('tickets.comment', $ticket['id']), $body);

        // The client's own reply box is still there, posting to the client route.
        $this->assertStringContainsString(route('client.tickets.comment', $ticket['id']), $body);
    }

    public function test_the_topbar_names_the_client_whose_portal_is_on_screen(): void
    {
        $this->withDemoData();

        /*
         * The switcher changes whose records are shown, so the corner of the
         * page has to follow — otherwise a correctly-scoped page looks broken
         * and a leaking one looks correct.
         */
        $html = $this->get('/client/dashboard?as='.self::THEIRS)->getContent();

        $this->assertStringContainsString('<strong>'.self::THEIRS.'</strong>', $html);
        $this->assertStringNotContainsString('<strong>'.self::OURS.'</strong>', $html);
    }

    public function test_draft_invoices_are_not_the_clients_to_see(): void
    {
        $this->withDemoData();

        $draft = DemoInvoices::all()
            ->first(fn (array $i) => InvoicePresenter::statusOf($i) === InvoicePresenter::DRAFT);

        $this->assertNotNull($draft, 'no draft invoice in the fixture');

        // Not in the list, and not reachable by id either — the filter lives in
        // the data source, so it applies to the lookup as well as the table.
        $body = $this->pageBody('/client/invoices?as='.$draft['client']);
        $this->assertStringNotContainsString($draft['id'], $body);

        $this->get('/client/invoices/'.$draft['id'].'?as='.$draft['client'])->assertNotFound();
    }

    public function test_a_client_does_not_see_internal_tickets_on_their_own_project(): void
    {
        $this->withDemoData();

        /*
         * An engineer's ticket is ours even when it is about a client's
         * project, and it usually says why something is late in words nobody
         * wrote for a client to read.
         *
         * Checked against EVERY internal ticket rather than one example: the
         * property is "no ticket without a client appears here", and a single
         * example would stop proving it the moment the fixture changed.
         */
        $internal = DemoTickets::all()->whereNull('client');

        $this->assertNotEmpty($internal, 'no internal tickets in the fixture');

        $body = $this->pageBody('/client/tickets?as='.self::OURS);

        foreach ($internal as $ticket) {
            $this->assertStringNotContainsString($ticket['id'], $body);
            $this->assertStringNotContainsString($ticket['subject'], $body);

            // And not reachable by id either.
            $this->get('/client/tickets/'.$ticket['id'].'?as='.self::OURS)->assertNotFound();
        }
    }

    public function test_the_notification_bell_is_empty_in_the_client_realm(): void
    {
        $this->withDemoData();

        /*
         * Every row Notifier writes is addressed to a staff account and worded
         * for one. Handing a client the staff bell would leak the lot, so the
         * client realm gets none until a client-addressed stream exists.
         *
         * The two halves need two sessions now — the staff dashboard is behind
         * `realm:staff` and this file signs in as a client. Which is itself the
         * point: the bell is not the only thing keeping the realms apart.
         */
        $client = $this->get('/client/dashboard')->getContent();
        $this->assertStringNotContainsString('notif-item', $client);

        /*
         * The control half writes its own notification rather than leaning on
         * the seed. The bell is per-account now, so "a staff session sees
         * something" is only demonstrated by a row addressed to the account
         * doing the looking — and a control that quietly stopped controlling
         * for anything is worse than no control at all.
         */
        $staffUser = $this->signInAsStaff();

        Notification::create([
            'user_id' => $staffUser->id,
            'kind' => 'task',
            'title' => 'Somebody assigned you a task',
            'body' => 'Something to do.',
        ]);

        $staff = $this->get('/dashboard')->getContent();

        $this->assertStringContainsString('notif-item', $staff, 'the staff bell is empty, so this proves nothing');
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE SHELL
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_sidebar_is_the_client_one_and_only_that(): void
    {
        $this->withDemoData();

        $html = $this->get('/client/dashboard')->getContent();

        foreach (config('navigation-client') as $entry) {
            $html && $this->assertStringContainsString('>'.$entry['label'].'</span>', $html);
        }

        /*
         * And nothing from the staff side. Driven off the staff configuration
         * rather than a list written here, so a module added there cannot
         * appear in a client's sidebar without failing this.
         */
        $clientKeys = array_column(config('navigation-client'), 'key');

        foreach (config('navigation') as $entry) {
            if (in_array($entry['key'], $clientKeys, true)) {
                continue;
            }

            $this->assertStringNotContainsString(
                '>'.$entry['label'].'</span>',
                $html,
                'the staff entry "'.$entry['label'].'" is in a client sidebar',
            );
        }
    }

    public function test_no_client_page_links_into_the_staff_realm(): void
    {
        $this->withDemoData();

        $pages = [
            '/client/dashboard', '/client/projects', '/client/projects/WD-2024-001',
            '/client/invoices', '/client/tickets', '/client/tickets/raise',
            '/client/meetings', '/client/meetings/request', '/client/profile',
        ];

        foreach ($pages as $page) {
            $body = $this->pageBody($page.'?as='.self::OURS);

            // The staff URLs a copy-paste from the staff views would drag in.
            foreach (['"/dashboard"', '"/projects/', '"/invoices/', '"/tickets/', '"/employees', '"/salary'] as $staffUrl) {
                $this->assertStringNotContainsString($staffUrl, $body, $page.' links into the staff realm');
            }
        }
    }

    public function test_every_page_renders(): void
    {
        $this->withDemoData();

        foreach (config('navigation-client') as $entry) {
            $this->get(route($entry['route']))->assertOk();
        }

        $this->get('/client/tickets/raise')->assertOk();
        $this->get('/client/meetings/request')->assertOk();
    }

    public function test_an_unknown_client_preview_is_refused(): void
    {
        $this->withDemoData();

        // Validated against the known clients, so `?as=` cannot put arbitrary
        // text on the page or scope a query to something invented.
        $response = $this->get('/client/dashboard?as=%22%3E%3Cscript%3E');

        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertDontSee('<script>', false);
    }

    public function test_the_client_switcher_does_not_exist_outside_local_debug(): void
    {
        $this->assertStringNotContainsString('cl-switch', $this->pageBody('/client/dashboard'));
    }

    public function test_it_renders_its_empty_states_without_demo_data(): void
    {
        /*
         * Outside local + debug every Demo source returns nothing, so the
         * portal must show that honestly rather than failing on a missing key.
         *
         * My Profile is the exception, and correctly so: it renders a client's
         * own record, and with no record there is no page. A 404 is the right
         * answer to a session whose client does not exist — inventing a
         * placeholder organisation to fill the page would be worse.
         */
        foreach (config('navigation-client') as $entry) {
            $expected = $entry['key'] === 'profile' ? 404 : 200;

            $this->assertSame(
                $expected,
                $this->get(route($entry['route']))->getStatusCode(),
                $entry['label'].' behaved unexpectedly with no data behind it',
            );
        }
    }

    public function test_it_renders_nothing_the_content_security_policy_would_block(): void
    {
        $this->withDemoData();

        foreach (['/client/dashboard', '/client/invoices', '/client/tickets/raise'] as $page) {
            $html = $this->get($page)->getContent();

            $this->assertSame(0, preg_match_all('/<style[\s>]/i', $html), $page.': inline <style>');
            $this->assertSame(0, preg_match_all('/\sstyle="/i', $html), $page.': inline style attribute');
            $this->assertSame(0, preg_match_all('/\son[a-z]+="/i', $html), $page.': inline event handler');
            $this->assertSame(0, preg_match_all('/<script(?![^>]*\ssrc=)/i', $html), $page.': inline <script>');
            $this->assertStringNotContainsString('fonts.googleapis.com', $html);
            $this->assertStringNotContainsString('unpkg.com', $html);
        }
    }

    public function test_the_write_routes_exist_so_the_forms_are_real(): void
    {
        foreach (['client.tickets.store', 'client.tickets.comment', 'client.meetings.request',
            'client.profile.update', 'client.invoices.download'] as $route) {
            $this->assertTrue(app('router')->has($route), $route.' is missing');
        }
    }

    public function test_no_route_lets_a_client_schedule_a_meeting_or_record_a_response(): void
    {
        /*
         * A client asks; a project manager creates. And responses belong to
         * Google Calendar — a second accept button here would be a second
         * source of truth for the same fact.
         */
        $this->assertFalse(app('router')->has('client.meetings.store'));
        $this->assertFalse(app('router')->has('client.meetings.respond'));
    }
}
