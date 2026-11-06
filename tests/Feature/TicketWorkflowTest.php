<?php

namespace Tests\Feature;

use App\Mail\TicketUpdateMail;
use App\Models\Client;
use App\Models\Employee;
use App\Models\MasterDataItem;
use App\Models\Project;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\User;
use App\Support\Navigation\Navigation;
use App\Support\Rbac\Rbac;
use App\Support\TicketLifecycle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The ticket process (owner, 2026-11-06): who sees a ticket, who may decide it,
 * taking, escalating, resolving, and what the person who asked is told.
 */
class TicketWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /* ── who sees what ────────────────────────────────────────────────────── */

    public function test_an_employee_sees_only_their_own_tickets_and_the_sidebar_opens_my_tickets(): void
    {
        $me = $this->person(['employee'], 'EMP701');
        $mine = $this->internalTicket($me, 'TKT-W-001');
        $someoneElses = $this->internalTicket($this->person(['employee'], 'EMP702'), 'TKT-W-002');
        $assignedToMe = $this->internalTicket($this->person(['employee'], 'EMP703'), 'TKT-W-003', ['assignee_id' => $me->id, 'status' => 'open']);

        $this->actAs($me);

        $this->get('/tickets/'.$mine->reference)->assertOk();
        $this->get('/tickets/'.$assignedToMe->reference)->assertOk();
        $this->get('/tickets/'.$someoneElses->reference)->assertNotFound();
        $this->post('/tickets/'.$someoneElses->reference.'/comment', ['body' => 'Hello'])->assertNotFound();

        $listed = collect($this->get('/tickets')->viewData('tickets')->items())->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$mine->reference, $assignedToMe->reference], $listed);

        $this->assertSame(route('tickets.mine'), $this->sidebarUrl($me->user));
    }

    public function test_support_sees_every_ticket_and_a_manager_only_their_own(): void
    {
        $manager = $this->person(['employee', 'manager'], 'EMP704');
        $onTheirProject = $this->clientTicket('TKT-W-004', ['project_id' => $this->project($manager)->id]);
        $elsewhere = $this->clientTicket('TKT-W-005');

        $this->actAs($this->person(['employee', 'support'], 'EMP705'));
        $this->get('/tickets/'.$elsewhere->reference)->assertOk();

        $this->actAs($manager);
        $this->get('/tickets/'.$onTheirProject->reference)->assertOk();
        $this->get('/tickets/'.$elsewhere->reference)->assertNotFound();
        $this->assertSame(route('tickets.index'), $this->sidebarUrl($manager->user));
    }

    /* ── nobody decides their own ticket ──────────────────────────────────── */

    public function test_the_person_who_raised_it_can_reply_but_not_decide_it(): void
    {
        $supportPerson = $this->person(['employee', 'support'], 'EMP706');
        $ticket = $this->internalTicket($supportPerson, 'TKT-W-006');
        $this->person(['employee', 'support'], 'EMP707');

        $this->actAs($supportPerson);

        $page = $this->get('/tickets/'.$ticket->reference);
        $page->assertOk()->assertSee('You raised this ticket')->assertDontSee('Mark Resolved')->assertDontSee('Triage this ticket');
        $this->assertNotContains($supportPerson->id, collect($page->viewData('agents'))->pluck('id')->all());

        $this->post('/tickets/'.$ticket->reference.'/triage', ['priority' => 'high'])->assertForbidden();
        $this->post('/tickets/'.$ticket->reference.'/resolve')->assertForbidden();
        $this->post('/tickets/'.$ticket->reference.'/take')->assertForbidden();
        $this->post('/tickets/'.$ticket->reference.'/comment', ['body' => 'Any news?'])->assertRedirect();

        $this->assertSame('unassigned', $ticket->refresh()->status);
        $this->assertSame(1, $ticket->comments()->count());
    }

    /* ── taking ───────────────────────────────────────────────────────────── */

    public function test_a_system_administrator_or_the_ceo_can_take_any_ticket_but_not_their_own(): void
    {
        $ticket = $this->clientTicket('TKT-W-007');

        foreach (['system_admin' => 'EMP708', 'ceo' => 'EMP709'] as $role => $staffId) {
            $taker = $this->person(['employee', $role], $staffId);
            $this->actAs($taker);

            $this->post('/tickets/'.$ticket->reference.'/take')->assertRedirect();
            $this->assertSame($taker->id, $ticket->refresh()->assignee_id);
            $this->assertSame('in_progress', $ticket->status);
        }

        $ceo = Employee::whereHas('user', fn ($q) => $q->where('user_id', 'EMP709'))->firstOrFail();
        $ownTicket = $this->internalTicket($ceo, 'TKT-W-008');
        $this->post('/tickets/'.$ownTicket->reference.'/take')->assertForbidden();
    }

    public function test_a_manager_can_take_only_a_ticket_of_their_own(): void
    {
        $manager = $this->person(['employee', 'manager'], 'EMP710');
        $theirs = $this->clientTicket('TKT-W-009', ['project_id' => $this->project($manager)->id]);
        $notTheirs = $this->clientTicket('TKT-W-010', ['client_id' => $theirs->client_id]);
        $notTheirs->client->update(['account_manager_id' => null]);

        $this->actAs($manager);

        $this->post('/tickets/'.$theirs->reference.'/take')->assertRedirect();
        $this->assertSame($manager->id, $theirs->refresh()->assignee_id);

        $this->post('/tickets/'.$notTheirs->reference.'/take')->assertNotFound();
    }

    /* ── escalating ───────────────────────────────────────────────────────── */

    public function test_support_escalates_an_it_matter_to_a_technical_associate_with_a_reason(): void
    {
        $technical = $this->technicalAssociate('EMP711');
        $ticket = $this->clientTicket('TKT-W-011', ['category' => 'Access']);
        $support = $this->person(['employee', 'support'], 'EMP712');

        $this->actAs($support);

        $this->assertSame($technical->id, $this->get('/tickets/'.$ticket->reference)->viewData('suggestedEscalation'));

        $this->post('/tickets/'.$ticket->reference.'/escalate', ['escalate_to' => $technical->id])
            ->assertSessionHasErrors(['reason' => 'Say why it needs escalating, so the next person does not start from nothing.']);

        $this->post('/tickets/'.$ticket->reference.'/escalate', [
            'escalate_to' => $technical->id,
            'reason' => 'Password reset did not help.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame($technical->id, $ticket->assignee_id);
        $this->assertSame('escalated', $ticket->status);
        $this->assertSame($support->id, $ticket->escalated_by);

        $note = TicketComment::where('ticket_id', $ticket->id)->latest('id')->firstOrFail();
        $this->assertTrue($note->isInternal());
        $this->assertStringContainsString('Password reset did not help.', $note->body);
    }

    public function test_whoever_has_it_escalates_to_their_seniors_and_the_ceos_own_ticket_skips_the_ceo(): void
    {
        $ceo = $this->person(['employee', 'ceo'], 'EMP713');
        $boss = $this->person(['employee', 'manager'], 'EMP714');
        $worker = $this->technicalAssociate('EMP715');
        $worker->update(['reports_to' => $boss->id]);
        $sysAdmin = $this->person(['employee', 'system_admin'], 'EMP716');

        $ticket = $this->clientTicket('TKT-W-012', ['assignee_id' => $worker->id, 'status' => 'open']);

        $this->actAs($worker);
        $this->assertSame($boss->id, $this->get('/tickets/'.$ticket->reference)->viewData('suggestedEscalation'));

        $this->post('/tickets/'.$ticket->reference.'/escalate', ['escalate_to' => $boss->id, 'reason' => 'Beyond me.'])
            ->assertRedirect();
        $this->assertSame($boss->id, $ticket->refresh()->assignee_id);

        $this->actAs($boss);
        $this->assertSame($ceo->id, $this->get('/tickets/'.$ticket->reference)->viewData('suggestedEscalation'));

        $ceosOwn = $this->internalTicket($ceo, 'TKT-W-013', ['assignee_id' => $boss->id, 'status' => 'open']);
        $offered = $this->get('/tickets/'.$ceosOwn->reference);
        $this->assertSame($sysAdmin->id, $offered->viewData('suggestedEscalation'));
        $this->assertNotContains($ceo->id, collect($offered->viewData('escalationTargets'))->pluck('id')->all());

        $this->post('/tickets/'.$ceosOwn->reference.'/escalate', ['escalate_to' => $ceo->id, 'reason' => 'Up.'])
            ->assertSessionHasErrors(['escalate_to' => 'Choose somebody from the list.']);
    }

    /* ── resolving, reopening, closing, and telling the requester ─────────── */

    public function test_resolving_tells_the_client_and_their_reply_reopens_it(): void
    {
        $ticket = $this->clientTicket('TKT-W-014');
        $clientUser = User::factory()->create([
            'user_id' => 'CLI-W14', 'account_type' => 'client', 'staff_kind' => null,
            'status' => 'active', 'client_ref' => $ticket->client->reference, 'email' => 'buyer@client.test',
        ]);

        $this->actAs($this->person(['employee', 'support'], 'EMP717'));
        $this->post('/tickets/'.$ticket->reference.'/resolve')->assertRedirect();

        $this->assertSame('resolved', $ticket->refresh()->status);
        Mail::assertSent(TicketUpdateMail::class, fn (TicketUpdateMail $mail) => $mail->hasTo('buyer@client.test')
            && $mail->headline === 'Your ticket is resolved');

        $this->actingAs($clientUser);
        $this->post('/client/tickets/'.$ticket->reference.'/comment', ['body' => 'Still broken.'])->assertRedirect();

        $ticket->refresh();
        $this->assertSame('unassigned', $ticket->status);
        $this->assertNull($ticket->resolved_at);
    }

    public function test_a_public_reply_emails_the_requester_and_an_internal_note_does_not(): void
    {
        $raiser = $this->person(['employee'], 'EMP718');
        $raiser->user->update(['email' => 'raiser@zephryx.test']);
        $ticket = $this->internalTicket($raiser, 'TKT-W-015');

        $this->actAs($this->person(['employee', 'support'], 'EMP719'));

        $this->post('/tickets/'.$ticket->reference.'/comment', ['body' => 'Looking.', 'visibility' => TicketComment::INTERNAL]);
        Mail::assertNotSent(TicketUpdateMail::class);

        $this->post('/tickets/'.$ticket->reference.'/comment', ['body' => 'Fixed it, try now.']);
        Mail::assertSent(TicketUpdateMail::class, fn (TicketUpdateMail $mail) => $mail->hasTo('raiser@zephryx.test')
            && ! str_contains($mail->message, 'Fixed it'));
    }

    public function test_a_resolved_ticket_closes_by_itself_after_seven_days(): void
    {
        $stale = $this->clientTicket('TKT-W-016', ['status' => 'resolved', 'resolved_at' => Carbon::now()->subDays(8)]);
        $recent = $this->clientTicket('TKT-W-017', ['status' => 'resolved', 'resolved_at' => Carbon::now()->subDays(2)]);

        $this->assertSame(1, TicketLifecycle::closeStaleResolved());

        $this->assertSame('closed', $stale->refresh()->status);
        $this->assertSame('resolved', $recent->refresh()->status);
    }

    /* ── the client portal ────────────────────────────────────────────────── */

    public function test_a_client_attaches_files_and_downloads_only_their_own(): void
    {
        $clientUser = $this->signInAsClient('A Client W');
        $this->connectGoogleDrive();
        $this->fakeDriveUpload();

        $this->post('/client/tickets', [
            'subject' => 'Logo is wrong',
            'description' => 'See the screenshot.',
            'documents' => [UploadedFile::fake()->create('logo.png', 20, 'image/png')],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $ticket = Ticket::firstOrFail();
        $theirs = TicketAttachment::where('ticket_id', $ticket->id)->firstOrFail();
        $this->assertSame($clientUser->id, $theirs->uploaded_by);

        $staffFile = TicketAttachment::create([
            'ticket_id' => $ticket->id,
            'uploaded_by' => $this->person(['employee', 'support'], 'EMP720')->user_id,
            'uploaded_by_label' => 'Support',
            'document_path' => 'local:tickets/internal.pdf',
            'document_name' => 'internal.pdf',
            'document_bytes' => 10,
        ]);

        $this->actingAs($clientUser);
        $page = $this->get('/client/tickets/'.$ticket->reference);
        $this->assertSame([$theirs->id], collect($page->viewData('files'))->pluck('id')->all());

        $this->get('/client/tickets/'.$ticket->reference.'/attachments/'.$staffFile->id.'/download')->assertNotFound();
    }

    /* ── helpers ──────────────────────────────────────────────────────────── */

    /**
     * @param  list<string>  $roles
     */
    protected function person(array $roles, string $staffId): Employee
    {
        $user = User::factory()->create([
            'user_id' => $staffId,
            'account_type' => 'staff',
            'staff_kind' => 'employee',
            'status' => 'active',
        ]);
        $user->roles()->sync(Role::whereIn('role_key', $roles)->pluck('id'));

        return Employee::create(['user_id' => $user->id, 'joined_on' => Carbon::now()->subYear()]);
    }

    protected function technicalAssociate(string $staffId): Employee
    {
        $it = MasterDataItem::firstOrCreate(
            ['list' => MasterDataItem::DEPARTMENTS, 'code' => 'ITT'],
            ['name' => 'IT Test', 'is_active' => true],
        );
        $designation = MasterDataItem::firstOrCreate(
            ['list' => MasterDataItem::DESIGNATIONS, 'code' => config('tickets.technical_designation_code')],
            ['name' => 'Technical Associate', 'department_id' => $it->id, 'is_active' => true],
        );

        $employee = $this->person(['employee'], $staffId);
        $employee->update(['designation_id' => $designation->id, 'department_id' => $it->id]);

        return $employee;
    }

    protected function actAs(Employee $employee): void
    {
        app(Rbac::class)->forget($employee->user);
        $this->actingAs($employee->user);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function internalTicket(Employee $raiser, string $reference, array $attributes = []): Ticket
    {
        return Ticket::create($attributes + [
            'reference' => $reference,
            'type' => 'internal',
            'subject' => 'Something internal',
            'description' => 'Details.',
            'raised_by' => $raiser->id,
            'status' => 'unassigned',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function clientTicket(string $reference, array $attributes = []): Ticket
    {
        return Ticket::create($attributes + [
            'reference' => $reference,
            'type' => 'client',
            'subject' => 'Something for a client',
            'description' => 'Details.',
            'client_id' => Client::firstOrCreate(['name' => 'A Client W'], ['reference' => 'CLT-W1', 'status' => 'active'])->id,
            'status' => 'unassigned',
        ]);
    }

    protected function project(Employee $manager): Project
    {
        return Project::create([
            'reference' => 'PRJ-W'.fake()->unique()->numberBetween(100, 999),
            'name' => 'Project '.fake()->unique()->numberBetween(100, 999),
            'client_id' => Client::firstOrCreate(['name' => 'A Client W'], ['reference' => 'CLT-W1', 'status' => 'active'])->id,
            'manager_id' => $manager->id,
            'status' => 'in_progress',
            'priority' => 'medium',
            'started_on' => Carbon::today()->subMonth(),
            'deadline' => Carbon::today()->addMonth(),
        ]);
    }

    protected function sidebarUrl(User $user): ?string
    {
        app(Rbac::class)->forget($user);

        return collect($this->app->make(Navigation::class)->for($user))->firstWhere('key', 'tickets')['url'] ?? null;
    }
}
