<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\MasterDataItem;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Realm;
use Tests\TestCase;

/**
 * A client who marks a ticket technical skips triage (owner, 2026-11-03).
 */
class TechnicalTicketRoutingTest extends TestCase
{
    protected const PAYLOAD = ['subject' => 'The site is down', 'description' => 'Since this morning.'];

    protected function employeeWith(array $roles = [], ?MasterDataItem $designation = null, ?MasterDataItem $department = null, string $status = 'active'): Employee
    {
        $user = User::factory()->create([
            'user_id' => 'EMP-X'.fake()->unique()->numberBetween(100, 999),
            'account_type' => Realm::STAFF,
            'staff_kind' => 'employee',
            'status' => $status,
        ]);

        $user->roles()->sync(Role::whereIn('role_key', ['employee', ...$roles])->pluck('id'));

        return Employee::create([
            'user_id' => $user->id,
            'employment_type' => Employee::FULL_TIME,
            'designation_id' => $designation?->id,
            'department_id' => $department?->id,
            'joined_on' => now()->subYear()->toDateString(),
        ]);
    }

    protected function technicalAssociate(string $status = 'active'): Employee
    {
        $it = MasterDataItem::firstOrCreate(
            ['list' => MasterDataItem::DEPARTMENTS, 'code' => 'ITT'],
            ['name' => 'IT Test', 'is_active' => true],
        );
        $designation = MasterDataItem::firstOrCreate(
            ['list' => MasterDataItem::DESIGNATIONS, 'code' => config('tickets.technical_designation_code')],
            ['name' => 'Technical Associate', 'department_id' => $it->id, 'is_active' => true],
        );

        return $this->employeeWith([], $designation, $it, $status);
    }

    protected function wasNotified(Employee $employee): bool
    {
        return Notification::where('user_id', $employee->user_id)->where('kind', 'ticket')->exists();
    }

    public function test_a_technical_ticket_goes_straight_to_the_technical_associate_and_not_to_support(): void
    {
        $associate = $this->technicalAssociate();
        $support = $this->employeeWith(['support']);

        $this->signInAsClient();

        $this->post('/client/tickets', self::PAYLOAD + ['issue_type' => 'technical'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $ticket = Ticket::query()->latest('id')->firstOrFail();

        $this->assertSame($associate->id, $ticket->assignee_id);
        $this->assertSame('open', $ticket->status);
        $this->assertSame('IT Test', $ticket->department);
        $this->assertTrue($this->wasNotified($associate));
        $this->assertFalse($this->wasNotified($support));
    }

    public function test_a_general_ticket_still_goes_to_support(): void
    {
        $associate = $this->technicalAssociate();
        $support = $this->employeeWith(['support']);

        $this->signInAsClient();

        $this->post('/client/tickets', self::PAYLOAD + ['issue_type' => 'general'])->assertRedirect();

        $ticket = Ticket::query()->latest('id')->firstOrFail();

        $this->assertNull($ticket->assignee_id);
        $this->assertSame('unassigned', $ticket->status);
        $this->assertTrue($this->wasNotified($support));
        $this->assertFalse($this->wasNotified($associate));
    }

    public function test_a_technical_ticket_joins_the_normal_queue_when_nobody_active_holds_the_designation(): void
    {
        $closed = $this->technicalAssociate('inactive');
        $support = $this->employeeWith(['support']);

        $this->signInAsClient();

        $this->post('/client/tickets', self::PAYLOAD + ['issue_type' => 'technical'])->assertRedirect();

        $ticket = Ticket::query()->latest('id')->firstOrFail();

        $this->assertNull($ticket->assignee_id);
        $this->assertSame('unassigned', $ticket->status);
        $this->assertTrue($this->wasNotified($support));
        $this->assertFalse($this->wasNotified($closed));
    }

    public function test_with_several_technical_associates_the_one_with_fewest_open_tickets_gets_it(): void
    {
        $busy = $this->technicalAssociate();
        $free = $this->technicalAssociate();

        Ticket::create([
            'reference' => 'TKT-2026-970', 'type' => 'internal', 'subject' => 'Already on it',
            'description' => 'Open', 'status' => 'open', 'assignee_id' => $busy->id,
        ]);

        $this->signInAsClient();

        $this->post('/client/tickets', self::PAYLOAD + ['issue_type' => 'technical'])->assertRedirect();

        $this->assertSame($free->id, Ticket::query()->latest('id')->firstOrFail()->assignee_id);
    }

    public function test_an_unknown_issue_type_is_refused(): void
    {
        $this->signInAsClient();

        $this->post('/client/tickets', self::PAYLOAD + ['issue_type' => 'urgent'])
            ->assertSessionHasErrors('issue_type');

        $this->assertSame(0, Ticket::count());
    }

    public function test_the_client_form_offers_the_choice(): void
    {
        $this->signInAsClient();

        $this->get('/client/tickets/raise')->assertOk()->assertSee('Technical issue');
    }
}
