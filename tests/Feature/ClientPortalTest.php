<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Support\ClientPortal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TWO SESSIONS, NOT A QUERY PARAMETER
 *
 * These tests used to switch clients with `?as=`, because the portal read a
 * fixture keyed on a company name and there was no other way to see two. That
 * switch is gone, so each half signs in as the account that owns the record —
 * which is what the ownership check actually reads, and therefore what is worth
 * testing.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class ClientPortalTest extends TestCase
{
    /** The client this file signs in as by default. */
    protected const OURS = 'DGL International School';

    /** Somebody else entirely, with their own projects, invoices and tickets. */
    protected const THEIRS = 'GreenLeaf Foods';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoWorkforce();

        /*
         * The portal is behind `realm:client` (§3.1). A staff session is
         * refused here before any data is read — see RealmGuardTest, which is
         * the assertion that guard exists for.
         */
        $this->asClient(self::OURS);
    }

    /**
     * Sign in as the seeded portal account for one client.
     */
    protected function asClient(string $name): Client
    {
        $client = Client::where('name', $name)->firstOrFail();

        $user = User::where('client_ref', $client->reference)->firstOrFail();

        $this->actingAs($user);

        return $client;
    }

    protected function ours(): Client
    {
        return Client::where('name', self::OURS)->firstOrFail();
    }

    protected function theirs(): Client
    {
        return Client::where('name', self::THEIRS)->firstOrFail();
    }

    /**
     * A record belonging to the OTHER client, found from the data rather than
     * hardcoded — a fixed id rots the first time the seed is edited.
     */
    protected function theirProject(): string
    {
        $project = Project::where('client_id', $this->theirs()->id)->first();

        $this->assertNotNull($project, 'no project for the other client');

        return $project->reference;
    }

    protected function theirInvoice(): string
    {
        $invoice = Invoice::where('client_id', $this->theirs()->id)->whereNotNull('sent_at')->first();

        $this->assertNotNull($invoice, 'no sent invoice for the other client');

        return $invoice->number;
    }

    protected function theirTicket(): string
    {
        $ticket = Ticket::where('client_id', $this->theirs()->id)->first();

        $this->assertNotNull($ticket, 'no ticket for the other client');

        return $ticket->reference;
    }

    /* ══════════════════════════════════════════════════════════════════════
       OWNERSHIP
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_client_cannot_open_another_clients_project(): void
    {
        $reference = $this->theirProject();

        // Theirs renders for them...
        $this->asClient(self::THEIRS);
        $this->get('/client/projects/'.$reference)->assertOk();

        // ...and does not exist for anybody else.
        $this->asClient(self::OURS);
        $this->get('/client/projects/'.$reference)->assertNotFound();
    }

    public function test_a_client_cannot_open_another_clients_invoice(): void
    {
        $number = $this->theirInvoice();

        $this->asClient(self::THEIRS);
        $this->get('/client/invoices/'.$number)->assertOk();

        $this->asClient(self::OURS);
        $this->get('/client/invoices/'.$number)->assertNotFound();
    }

    public function test_a_client_cannot_open_another_clients_ticket(): void
    {
        $reference = $this->theirTicket();

        $this->asClient(self::THEIRS);
        $this->get('/client/tickets/'.$reference)->assertOk();

        $this->asClient(self::OURS);
        $this->get('/client/tickets/'.$reference)->assertNotFound();
    }

    public function test_someone_elses_record_is_indistinguishable_from_one_that_does_not_exist(): void
    {
        /*
         * Both answers must be the same 404. If "not yours" and "no such thing"
         * differed — by status, by wording, by anything — the difference could
         * be walked to learn which references are real, which is the first half
         * of the leak this portal is guarding against.
         */
        $theirs = $this->get('/client/invoices/'.$this->theirInvoice());
        $fictional = $this->get('/client/invoices/INV-9999-999');

        $theirs->assertNotFound();
        $fictional->assertNotFound();
        $this->assertSame($fictional->getStatusCode(), $theirs->getStatusCode());

        /*
         * The bodies differ only where the 404 page echoes the URL that was
         * asked for, which the reader already knows. Compared with that stripped
         * out rather than not compared at all: a difference anywhere else — a
         * word, a heading, a different template — would be the signal.
         */
        $strip = fn (string $html, string $url) => str_replace($url, '', $html);

        $this->assertSame(
            $strip($fictional->getContent(), '/client/invoices/INV-9999-999'),
            $strip($theirs->getContent(), '/client/invoices/'.$this->theirInvoice()),
        );
    }

    public function test_a_list_never_carries_another_clients_records(): void
    {
        // Driven off the data: every page, checked against every other client's
        // records, so adding a listing page cannot quietly skip the check.
        $pages = ['/client/dashboard', '/client/projects', '/client/invoices', '/client/tickets', '/client/meetings'];

        foreach ($pages as $page) {
            $body = $this->pageBody($page);

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
         * convenient all() later fails here instead of in production. And the
         * argument is typed `Client` now rather than being a string named
         * `$client` — a name is not an identifier, and a method taking one
         * could be handed anything.
         */
        $reflection = new \ReflectionClass(ClientPortal::class);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $first = $method->getParameters()[0] ?? null;

            $this->assertNotNull($first, ClientPortal::class.'::'.$method->getName().'() takes no client');
            $this->assertSame(
                Client::class,
                (string) $first->getType(),
                ClientPortal::class.'::'.$method->getName().'() does not take the client record first',
            );
        }
    }

    public function test_a_portal_session_whose_client_does_not_exist_is_refused(): void
    {
        /*
         * A session scoped to nothing. Refused rather than rendered empty: an
         * empty portal says "you have no projects", which is a different and
         * untrue statement.
         */
        $user = User::factory()->create([
            'user_id' => 'CLI-ORPHAN',
            'account_type' => 'client',
            'staff_kind' => null,
            'status' => 'active',
            'client_ref' => 'CLT-DOES-NOT-EXIST',
        ]);

        $this->actingAs($user);

        $this->get('/client/dashboard')->assertForbidden();
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHAT A CLIENT IS NOT SHOWN
       ══════════════════════════════════════════════════════════════════════ */

    public function test_internal_project_updates_never_reach_the_client(): void
    {
        $project = Project::where('client_id', $this->ours()->id)->firstOrFail();

        $internal = ProjectUpdate::where('project_id', $project->id)
            ->where('visibility', ProjectUpdate::INTERNAL)
            ->get();

        $visible = ProjectUpdate::where('project_id', $project->id)
            ->clientVisible()
            ->get();

        $this->assertNotEmpty($internal, 'no internal updates on this project, so this proves nothing');
        $this->assertNotEmpty($visible, 'no client-visible updates either, so the filter could be hiding everything');

        $body = $this->pageBody('/client/projects/'.$project->reference);

        foreach ($internal as $update) {
            $this->assertStringNotContainsString($update->body, $body, 'an internal update reached the client');
            $this->assertStringNotContainsString($update->title, $body);
        }

        // The client-visible ones are here, so the filter is not simply hiding
        // everything.
        $this->assertStringContainsString($visible->first()->title, $body);
    }

    public function test_the_page_does_not_say_how_much_is_being_withheld(): void
    {
        /*
         * A line reading "4 internal updates hidden" is itself a disclosure —
         * it tells the client there is something they are not being shown, on
         * the exact day it was written.
         *
         * Asserted on the phrasings such a line would take rather than on
         * single words: an update legitimately says "through internal review",
         * and every decorative icon on the page carries aria-hidden.
         */
        $project = Project::where('client_id', $this->ours()->id)->firstOrFail();

        $body = strtolower($this->pageBody('/client/projects/'.$project->reference));

        foreach (['internal update', 'internal note', 'updates hidden', 'not shown', 'withheld', 'more update'] as $phrase) {
            $this->assertStringNotContainsString($phrase, $body);
        }
    }

    public function test_internal_ticket_comments_never_reach_the_client(): void
    {
        /*
         * Any client ticket that actually carries an internal note — found from
         * the data rather than named here, so this keeps testing something when
         * the seeded threads are edited.
         */
        $ticket = Ticket::query()
            ->whereNotNull('client_id')
            ->whereHas('comments', fn ($q) => $q->where('visibility', TicketComment::INTERNAL))
            ->with('client')
            ->first();

        $this->assertNotNull($ticket, 'no client ticket with an internal comment in the seed');

        $internal = $ticket->comments()->where('visibility', TicketComment::INTERNAL)->get();

        $this->asClient($ticket->client->name);

        $body = $this->pageBody('/client/tickets/'.$ticket->reference);

        foreach ($internal as $comment) {
            $this->assertStringNotContainsString($comment->body, $body);
        }
    }

    public function test_the_ticket_page_does_not_offer_the_client_the_staff_composer(): void
    {
        $ticket = Ticket::query()->whereNotNull('client_id')->with('client')->firstOrFail();

        $this->asClient($ticket->client->name);

        $body = $this->pageBody('/client/tickets/'.$ticket->reference);

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
        $this->assertStringNotContainsString(route('tickets.comment', $ticket->reference), $body);

        // The client's own reply box is still there, posting to the client route.
        $this->assertStringContainsString(route('client.tickets.comment', $ticket->reference), $body);
    }

    public function test_the_topbar_names_the_client_whose_portal_is_on_screen(): void
    {
        /*
         * A client account is an organisation, not a person: the portal greets
         * the company. And it has to be the company whose records are on the
         * page — otherwise a correctly-scoped page looks broken and a leaking
         * one looks correct.
         */
        $this->asClient(self::THEIRS);

        $html = $this->get('/client/dashboard')->getContent();

        $this->assertStringContainsString('<strong>'.self::THEIRS.'</strong>', $html);
        $this->assertStringNotContainsString('<strong>'.self::OURS.'</strong>', $html);
    }

    public function test_draft_invoices_are_not_the_clients_to_see(): void
    {
        $draft = Invoice::query()->whereNull('sent_at')->with('client')->first();

        $this->assertNotNull($draft, 'no draft invoice in the seed');

        $this->asClient($draft->client->name);

        // Not in the list, and not reachable by id either — the filter is part
        // of the query, so it applies to the lookup as well as the table.
        $this->assertStringNotContainsString($draft->number, $this->pageBody('/client/invoices'));

        $this->get('/client/invoices/'.$draft->number)->assertNotFound();
        $this->get('/client/invoices/'.$draft->number.'/document/download')->assertNotFound();
    }

    public function test_a_client_does_not_see_internal_tickets_on_their_own_project(): void
    {
        /*
         * An engineer's ticket is ours even when it is about a client's
         * project, and it usually says why something is late in words nobody
         * wrote for a client to read.
         *
         * Checked against EVERY internal ticket rather than one example: the
         * property is "no ticket without a client appears here", and a single
         * example would stop proving it the moment the seed changed.
         */
        $internal = Ticket::query()->whereNull('client_id')->get();

        $this->assertNotEmpty($internal, 'no internal tickets in the seed');

        $body = $this->pageBody('/client/tickets');

        foreach ($internal as $ticket) {
            $this->assertStringNotContainsString($ticket->reference, $body);
            $this->assertStringNotContainsString($ticket->subject, $body);

            // And not reachable by id either.
            $this->get('/client/tickets/'.$ticket->reference)->assertNotFound();
        }
    }

    public function test_the_notification_bell_is_empty_in_the_client_realm(): void
    {
        /*
         * Every row Notifier writes is addressed to a staff account and worded
         * for one. Handing a client the staff bell would leak the lot, so the
         * client realm gets none until a client-addressed stream exists.
         */
        $this->assertStringNotContainsString('notif-item', $this->get('/client/dashboard')->getContent());

        /*
         * The control half writes its own notification rather than leaning on
         * the seed. The bell is per-account, so "a staff session sees
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

        $this->assertStringContainsString(
            'notif-item',
            $this->get('/dashboard')->getContent(),
            'the staff bell is empty, so this proves nothing',
        );
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE WRITES
       ══════════════════════════════════════════════════════════════════════ */

    public function test_raising_a_ticket_files_it_against_the_session_and_not_the_form(): void
    {
        /*
         * The rule the store route exists to hold. There is no `client_id` in
         * its validated set, so a posted one is dropped before it can be read —
         * not overwritten afterwards, which is the version that breaks the day
         * somebody reorders two lines.
         */
        $this->post('/client/tickets', [
            'subject' => 'The contact form is not sending anything',
            'description' => 'Since Tuesday. Nothing arrives.',
            'client_id' => $this->theirs()->id,
            'client' => self::THEIRS,
        ])->assertRedirect();

        $ticket = Ticket::query()->latest('id')->firstOrFail();

        $this->assertSame($this->ours()->id, $ticket->client_id);
        $this->assertSame('client', $ticket->type);

        // Unassigned and unprioritised, like every other ticket: a priority
        // nobody set is different from a low one, and the queue sorts on it.
        $this->assertSame('unassigned', $ticket->status);
        $this->assertNull($ticket->priority);
        $this->assertNull($ticket->assignee_id);
    }

    public function test_a_ticket_cannot_be_filed_against_somebody_elses_project(): void
    {
        // A select is a form field, and a form field is something anyone can
        // type into.
        $this->post('/client/tickets', [
            'subject' => 'Something',
            'description' => 'Anything.',
            'project' => $this->theirProject(),
        ])->assertSessionHasErrors('project');

        $this->assertSame(0, Ticket::where('subject', 'Something')->count());
    }

    public function test_a_clients_comment_cannot_be_an_internal_note(): void
    {
        /*
         * `visibility` is not read from the request at all, so there is no
         * branch here that could be made to post one — which is stronger than a
         * check that forces the value.
         */
        $ticket = Ticket::query()->where('client_id', $this->ours()->id)->firstOrFail();

        $this->post('/client/tickets/'.$ticket->reference.'/comment', [
            'body' => 'Any update on this?',
            'visibility' => TicketComment::INTERNAL,
        ])->assertRedirect();

        $comment = TicketComment::query()->latest('id')->firstOrFail();

        $this->assertSame(TicketComment::PUBLIC, $comment->visibility);
        $this->assertSame(self::OURS, $comment->author_label);
    }

    public function test_a_client_cannot_comment_on_somebody_elses_ticket(): void
    {
        $this->post('/client/tickets/'.$this->theirTicket().'/comment', ['body' => 'Hello'])
            ->assertNotFound();
    }

    public function test_a_client_may_change_how_to_reach_them_and_nothing_else(): void
    {
        $client = $this->ours();

        $wasName = $client->name;
        $wasStatus = $client->status;
        $wasIndustry = $client->industry;

        $this->post('/client/profile', [
            'contact_name' => 'A New Person',
            'contact_email' => 'new.person@example.com',
            'contact_phone' => '+91 90000 00000',
            'billing_address' => "1 Somewhere Road\nKolkata",
            // Everything below is ours, and is dropped rather than refused.
            'name' => 'A Completely Different Company',
            'status' => 'completed',
            'industry' => 'Something else',
            'reference' => 'CLT-9999',
        ])->assertRedirect('/client/profile');

        $client->refresh();

        $this->assertSame('A New Person', $client->contact_name);
        $this->assertSame("1 Somewhere Road\nKolkata", $client->billing_address);

        $this->assertSame($wasName, $client->name);
        $this->assertSame($wasStatus, $client->status);
        $this->assertSame($wasIndustry, $client->industry);
    }

    public function test_the_document_is_the_clients_own_and_is_logged(): void
    {
        /*
         * The seeded invoices carry no document — see InvoiceSeeder, which
         * follows SalarySeeder's own reasoning: there is no PDF to invent,
         * and a row whose `document_path` pointed at nothing would break the
         * download route rather than demonstrate it. So this test attaches
         * one directly, the same way InvoiceWritesTest::anInvoice() does.
         */
        Storage::fake('local');

        $invoice = Invoice::query()
            ->where('client_id', $this->ours()->id)
            ->whereNotNull('sent_at')
            ->firstOrFail();

        Storage::disk('local')->put('invoices/'.$invoice->number.'/stored.pdf', 'not a real pdf');
        $invoice->update([
            'document_path' => 'invoices/'.$invoice->number.'/stored.pdf',
            'document_name' => 'invoice.pdf',
            'document_bytes' => 14,
        ]);

        $download = $this->get('/client/invoices/'.$invoice->number.'/document/download');
        $download->assertOk();
        $this->assertStringContainsString('attachment', $download->headers->get('content-disposition'));

        $view = $this->get('/client/invoices/'.$invoice->number.'/document/view');
        $view->assertOk();
        $this->assertStringContainsString('inline', $view->headers->get('content-disposition'));

        $this->assertSame(
            2,
            DB::table('audit_log')->where('action', 'invoice.downloaded')
                ->where('entity_id', $invoice->number)->count(),
        );

        // Somebody else's is a 404, like every other read in this realm.
        $this->get('/client/invoices/'.$this->theirInvoice().'/document/download')->assertNotFound();
    }

    public function test_an_invoice_with_no_document_yet_404s_rather_than_erroring(): void
    {
        // The seeded set demonstrates exactly this state — see the note on
        // the test above.
        $invoice = Invoice::query()
            ->where('client_id', $this->ours()->id)
            ->whereNotNull('sent_at')
            ->firstOrFail();

        $this->assertFalse($invoice->hasDocument());

        $this->get('/client/invoices/'.$invoice->number.'/document/download')->assertNotFound();
        $this->get('/client/invoices/'.$invoice->number.'/document/view')->assertNotFound();
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE SHELL
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_sidebar_is_the_client_one_and_only_that(): void
    {
        $html = $this->get('/client/dashboard')->getContent();

        foreach (config('navigation-client') as $entry) {
            $this->assertStringContainsString('>'.$entry['label'].'</span>', $html);
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
        $project = Project::where('client_id', $this->ours()->id)->firstOrFail();

        $pages = [
            '/client/dashboard', '/client/projects', '/client/projects/'.$project->reference,
            '/client/invoices', '/client/tickets', '/client/tickets/raise',
            '/client/meetings', '/client/meetings/request', '/client/profile',
        ];

        foreach ($pages as $page) {
            $body = $this->pageBody($page);

            // The staff URLs a copy-paste from the staff views would drag in.
            foreach (['"/dashboard"', '"/projects/', '"/invoices/', '"/tickets/', '"/employees', '"/salary'] as $staffUrl) {
                $this->assertStringNotContainsString($staffUrl, $body, $page.' links into the staff realm');
            }
        }
    }

    public function test_every_page_renders(): void
    {
        foreach (config('navigation-client') as $entry) {
            $this->get(route($entry['route']))->assertOk();
        }

        $this->get('/client/tickets/raise')->assertOk();
        $this->get('/client/meetings/request')->assertOk();
    }

    public function test_the_development_client_switcher_is_gone(): void
    {
        /*
         * It changed WHOSE data a page showed, from a query parameter, and its
         * own docblock said it must not survive sessions. Asserted rather than
         * merely removed: a switch like this is exactly the thing that gets
         * reintroduced for a debugging session and left in.
         */
        $body = $this->pageBody('/client/dashboard');

        $this->assertStringNotContainsString('cl-switch', $body);
        $this->assertStringNotContainsString(self::THEIRS, $body);

        // And `?as=` is now an ignored query parameter rather than an override.
        $this->get('/client/dashboard?as='.urlencode(self::THEIRS))
            ->assertOk()
            ->assertDontSee('<strong>'.self::THEIRS.'</strong>', false);
    }

    public function test_it_renders_nothing_the_content_security_policy_would_block(): void
    {
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
            'client.profile.update', 'client.invoices.document.download', 'client.invoices.document.view'] as $route) {
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

    public function test_a_client_ticket_is_visible_to_staff_and_carries_the_clients_name(): void
    {
        /*
         * The other half of the ticket write, and the reason it matters: a
         * ticket the client can see and the staff queue cannot is a ticket
         * nobody answers.
         */
        $this->post('/client/tickets', [
            'subject' => 'Something is wrong with the site',
            'description' => 'It has been since Tuesday.',
        ])->assertRedirect();

        $ticket = Ticket::query()->latest('id')->firstOrFail();

        $this->signInAsStaff();

        $this->get('/tickets/'.$ticket->reference)
            ->assertOk()
            ->assertSee('Something is wrong with the site', false)
            ->assertSee(self::OURS, false);
    }
}
