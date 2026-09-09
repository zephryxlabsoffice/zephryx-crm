<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Rbac\Rbac;
use App\Support\TicketDirectory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tickets — raising one, replying on it, and triaging it.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE COLUMN THIS FILE IS REALLY ABOUT IS `visibility` AGAIN
 *
 * It defaults to public here and to internal on a project update, and the
 * difference is the point: a ticket comment is a REPLY, so the failure mode is
 * silence — a client waiting for an answer written days ago. An internal note
 * is the exception somebody chooses, and the client audience cannot reach one.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class TicketWritesTest extends TestCase
{
    /* ══════════════════════════════════════════════════════════════════════
       RAISING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_anybody_on_staff_may_raise_a_ticket(): void
    {
        // A ticket is how somebody asks for help. A permission on that would be
        // a permission to ask.
        $employee = $this->signInAsEmployee();

        $this->post('/tickets', $this->validPayload())->assertRedirect();

        $ticket = Ticket::firstOrFail();

        $this->assertSame('TKT-'.now()->year.'-001', $ticket->reference);
        $this->assertSame($employee->id, $ticket->raised_by);
    }

    public function test_it_arrives_unassigned_and_without_a_priority(): void
    {
        /*
         * A priority nobody set is different from a low one, and the queue
         * sorts on the difference. A form where everybody picks their own makes
         * every ticket high, which is the same as none of them being.
         */
        $this->signInAsEmployee();

        $this->post('/tickets', $this->validPayload(['priority' => 'high', 'status' => 'in_progress']));

        $ticket = Ticket::firstOrFail();

        $this->assertSame('unassigned', $ticket->status);
        $this->assertNull($ticket->priority);
        $this->assertNull($ticket->assignee_id);
    }

    public function test_a_client_ticket_must_name_the_client(): void
    {
        // The ownership key. A client ticket with no client is one no portal
        // read could ever scope.
        $this->signInAsEmployee();

        $this->post('/tickets', $this->validPayload(['type' => 'client', 'client_id' => null]))
            ->assertSessionHasErrors('client_id');
    }

    public function test_raising_is_audited(): void
    {
        $this->signInAsEmployee();

        $this->post('/tickets', $this->validPayload());

        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::TICKET_RAISED)->count());
    }

    /* ══════════════════════════════════════════════════════════════════════
       REPLYING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_reply_is_public_unless_somebody_says_otherwise(): void
    {
        $ticket = $this->aTicket();
        $this->signInAsEmployee();

        $this->post('/tickets/'.$ticket->reference.'/comment', ['body' => 'Looking at it now.'])
            ->assertRedirect();

        $comment = TicketComment::firstOrFail();

        $this->assertSame(TicketComment::PUBLIC, $comment->visibility);
    }

    public function test_an_internal_note_is_stored_as_one(): void
    {
        $ticket = $this->aTicket();
        $this->signInAsEmployee();

        $this->post('/tickets/'.$ticket->reference.'/comment', [
            'body' => 'They are on an ancient browser and will not upgrade.',
            'visibility' => TicketComment::INTERNAL,
        ])->assertRedirect();

        $this->assertTrue(TicketComment::firstOrFail()->isInternal());
    }

    public function test_the_client_audience_cannot_reach_an_internal_note(): void
    {
        /*
         * §6, at the query layer. The client portal calls commentsFor with its
         * own audience and there is no call that returns a thread without one.
         */
        $ticket = $this->aTicket();
        $this->signInAsEmployee();

        $this->post('/tickets/'.$ticket->reference.'/comment', ['body' => 'We have reproduced it.']);
        $this->post('/tickets/'.$ticket->reference.'/comment', [
            'body' => 'Their own plugin is the cause. Do not say that outright.',
            'visibility' => TicketComment::INTERNAL,
        ]);

        $staff = TicketDirectory::commentsFor($ticket->fresh(), TicketDirectory::AUDIENCE_STAFF);
        $client = TicketDirectory::commentsFor($ticket->fresh(), TicketDirectory::AUDIENCE_CLIENT);

        $this->assertCount(2, $staff);
        $this->assertCount(1, $client);
        $this->assertSame('We have reproduced it.', $client[0]['body']);
    }

    public function test_the_author_name_is_copied_onto_the_comment(): void
    {
        // A thread whose author's account was later removed must not read
        // "somebody said".
        $ticket = $this->aTicket();
        $user = User::factory()->create([
            'user_id' => 'EMP891',
            'name' => 'Amit Verma',
            'account_type' => 'staff',
            'staff_kind' => 'employee',
            'status' => 'active',
        ]);
        $user->roles()->sync(Role::whereIn('role_key', ['employee'])->pluck('id'));
        Employee::create(['user_id' => $user->id, 'joined_on' => Carbon::now()->subYear()]);

        $this->actingAs($user);

        $this->post('/tickets/'.$ticket->reference.'/comment', ['body' => 'On it.']);

        $this->assertSame('Amit Verma', TicketComment::firstOrFail()->author_label);
    }

    public function test_replying_moves_the_ticket_up_the_queue(): void
    {
        // The queue is ordered by what has moved, and a thread moving is a
        // ticket moving.
        $ticket = $this->aTicket();
        $ticket->forceFill(['updated_at' => Carbon::now()->subWeek()])->saveQuietly();

        $this->signInAsEmployee();

        $this->post('/tickets/'.$ticket->reference.'/comment', ['body' => 'Any update on this?']);

        $this->assertTrue($ticket->fresh()->updated_at->isToday());
    }

    /* ══════════════════════════════════════════════════════════════════════
       TRIAGE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_triage_needs_the_permission(): void
    {
        $ticket = $this->aTicket();
        $this->signInAsEmployee();

        $this->get('/tickets/escalated')->assertForbidden();
        $this->post('/tickets/'.$ticket->reference.'/triage', ['priority' => 'high'])->assertForbidden();
    }

    public function test_support_may_triage_and_it_records_what_changed(): void
    {
        $ticket = $this->aTicket();
        $agent = $this->anEmployee('EMP892', 'An Agent');

        $this->signInAsEmployee(['employee', 'support'], 'EMP893');

        $this->post('/tickets/'.$ticket->reference.'/triage', [
            'assignee_id' => $agent->id,
            'priority' => 'high',
            'category' => 'Bug',
            'department' => 'Development',
        ])->assertRedirect();

        $ticket->refresh();

        $this->assertSame($agent->id, $ticket->assignee_id);
        $this->assertSame('high', $ticket->priority);
        // Assigning somebody opens it: "assign to Amit and leave it unassigned"
        // is not a state anybody means.
        $this->assertSame('open', $ticket->status);

        $entry = DB::table('audit_log')->where('action', AuditLog::TICKET_TRIAGED)->first();

        $this->assertNotNull($entry);
        $this->assertStringContainsString('unassigned', (string) $entry->before_json);
        $this->assertStringContainsString('An Agent', (string) $entry->after_json);
    }

    public function test_escalating_records_who_sent_it_back(): void
    {
        // The review queue is worked by whoever is on shift, and "who routed
        // this here" is the question it gets asked.
        $ticket = $this->aTicket(['status' => 'in_progress']);
        $me = $this->signInAsEmployee(['employee', 'support'], 'EMP894');

        $this->post('/tickets/'.$ticket->reference.'/triage', ['status' => 'escalated'])
            ->assertRedirect();

        $ticket->refresh();

        $this->assertSame('escalated', $ticket->status);
        $this->assertSame($me->id, $ticket->escalated_by);
        $this->assertNotNull($ticket->escalated_at);
    }

    public function test_resolving_stamps_when_and_does_not_move_on_a_second_pass(): void
    {
        $ticket = $this->aTicket(['status' => 'in_progress']);
        $this->signInAsEmployee(['employee', 'support'], 'EMP895');

        $this->post('/tickets/'.$ticket->reference.'/triage', ['status' => 'resolved']);
        $first = $ticket->fresh()->resolved_at;

        $this->assertNotNull($first);

        Carbon::setTestNow(Carbon::now()->addHour());
        $this->post('/tickets/'.$ticket->reference.'/triage', ['status' => 'resolved']);
        Carbon::setTestNow();

        $this->assertEquals($first, $ticket->fresh()->resolved_at);
    }

    public function test_there_is_no_delete_route(): void
    {
        $ticket = $this->aTicket();
        $this->signInAsEmployee(['employee', 'support'], 'EMP896');

        $this->delete('/tickets/'.$ticket->reference)->assertStatus(405);
    }

    /* ══════════════════════════════════════════════════════════════════════
       HELPERS
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validPayload(array $overrides = []): array
    {
        return $overrides + [
            'type' => 'internal',
            'subject' => 'The VPN drops every twenty minutes',
            'description' => 'On the office connection, since Monday.',
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function aTicket(array $attributes = []): Ticket
    {
        return Ticket::create($attributes + [
            'reference' => 'TKT-TEST-001',
            'type' => 'client',
            'subject' => 'Website not loading on mobile',
            'description' => 'Blank page on Android.',
            'client_id' => Client::firstOrCreate(
                ['name' => 'A Client'],
                ['reference' => 'CLT901', 'status' => 'active'],
            )->id,
            'status' => 'unassigned',
        ]);
    }

    /**
     * @param  list<string>  $roles
     */
    protected function signInAsEmployee(array $roles = ['employee'], string $staffId = 'EMP890'): Employee
    {
        $user = User::factory()->create([
            'user_id' => $staffId,
            'account_type' => 'staff',
            'staff_kind' => 'employee',
            'status' => 'active',
        ]);

        $user->roles()->sync(Role::whereIn('role_key', $roles)->pluck('id'));

        app(Rbac::class)->forget($user);
        $this->actingAs($user);

        return Employee::create(['user_id' => $user->id, 'joined_on' => Carbon::now()->subYear()]);
    }

    protected function anEmployee(string $staffId, string $name): Employee
    {
        $user = User::factory()->create([
            'user_id' => $staffId,
            'name' => $name,
            'account_type' => 'staff',
            'staff_kind' => 'employee',
            'status' => 'active',
        ]);

        return Employee::create(['user_id' => $user->id, 'joined_on' => Carbon::now()->subYear()]);
    }
}
