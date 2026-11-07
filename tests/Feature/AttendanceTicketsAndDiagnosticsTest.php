<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Support\AttendancePolicy;
use App\Support\AttendancePresenter;
use App\Support\Diagnostics;
use App\Support\Rbac\Rbac;
use App\Support\Realm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * v1.16.0 (owner, 2026-11-07): a day lost to a technical issue is corrected under
 * the employee's ticket, attendance tickets go to HR, and what goes wrong is
 * recorded with a code and put on the ticket.
 */
class AttendanceTicketsAndDiagnosticsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::today()->setTime(9, 40));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /* ── correcting under a ticket ────────────────────────────────────────── */

    public function test_hr_corrects_a_never_closed_day_under_the_employees_ticket_and_it_counts_with_hours(): void
    {
        $subject = $this->anEmployee('EMP931', 'Could Not Check Out');
        $record = $this->anOpenDay($subject);
        $ticket = $this->ticketBy($subject, 'TKT-2026-501');

        $this->actAs($this->anEmployee('EMP932', 'HR Person', ['employee', 'hr']));

        $this->post('/attendance/'.$this->reference($record).'/mark-present', [
            'present_reason' => 'The check-out button failed, see the ticket.',
            'ticket_reference' => 'tkt-2026-501',
            'check_out' => '18:30',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $record->refresh();
        $this->assertSame('18:30', Carbon::parse($record->check_out)->format('H:i'));
        $this->assertSame($ticket->id, $record->corrected_ticket_id);
        $this->assertNull($record->marked_present_at);

        $evaluated = AttendancePolicy::evaluate($record->date, $record->toRecordArray());
        $this->assertSame(AttendancePresenter::PRESENT, $evaluated['state']);
        $this->assertSame(555, $evaluated['worked_minutes']);

        $note = TicketComment::where('ticket_id', $ticket->id)->firstOrFail();
        $this->assertTrue($note->isInternal());
        $this->assertStringContainsString('check-out 18:30', $note->body);

        $this->get('/attendance/'.$this->reference($record))->assertSee('Corrected for a technical issue under TKT-2026-501');
    }

    public function test_a_ticket_somebody_else_raised_is_refused_and_a_warning_day_can_still_be_corrected(): void
    {
        $subject = $this->anEmployee('EMP933', 'Marked With Warning');
        $record = $this->anOpenDay($subject, [
            'marked_present_at' => Carbon::now()->subDay(),
            'marked_present_reason' => 'Forgot, apparently.',
        ]);
        $this->ticketBy($this->anEmployee('EMP934', 'Someone Else'), 'TKT-2026-502');
        $theirs = $this->ticketBy($subject, 'TKT-2026-503');

        $this->actAs($this->anEmployee('EMP935', 'HR Person', ['employee', 'hr']));
        $this->get('/attendance/'.$this->reference($record))->assertSee('Correct this day under a ticket');

        $this->post('/attendance/'.$this->reference($record).'/mark-present', [
            'present_reason' => 'The check-out button failed.',
            'ticket_reference' => 'TKT-2026-502',
            'check_out' => '18:00',
        ])->assertSessionHasErrors(['ticket_reference' => 'That ticket was not raised by Marked With Warning. Use the one they raised about this day.']);

        $this->post('/attendance/'.$this->reference($record).'/mark-present', [
            'present_reason' => 'The check-out button failed.',
            'ticket_reference' => $theirs->reference,
            'check_out' => '08:00',
        ])->assertSessionHasErrors('check_out');

        $this->post('/attendance/'.$this->reference($record).'/mark-present', [
            'present_reason' => 'The check-out button failed.',
            'ticket_reference' => $theirs->reference,
            'check_out' => '18:00',
        ])->assertSessionHasNoErrors();

        $record->refresh();
        $this->assertNull($record->marked_present_at);
        $this->assertSame($theirs->id, $record->corrected_ticket_id);
    }

    /* ── attendance tickets go to HR ──────────────────────────────────────── */

    public function test_an_attendance_ticket_goes_straight_to_hr_who_may_resolve_it(): void
    {
        $hr = $this->anEmployee('EMP936', 'HR Person', ['employee', 'hr']);
        $this->anEmployee('EMP937', 'Support Person', ['employee', 'support']);
        $raiser = $this->anEmployee('EMP938', 'Missed A Day');

        $this->actAs($raiser);
        $this->post('/tickets', [
            'type' => 'internal',
            'subject' => 'Missed check-in on Monday',
            'description' => 'I was in from 9:30 to 6:30.',
            'category' => 'Attendance',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $ticket = Ticket::latest('id')->firstOrFail();
        $this->assertSame($hr->id, $ticket->assignee_id);
        $this->assertSame('open', $ticket->status);

        $other = $this->ticketBy($this->anEmployee('EMP939', 'Another'), 'TKT-2026-504', ['category' => 'Attendance']);
        $notHrs = $this->ticketBy($this->anEmployee('EMP940', 'Third'), 'TKT-2026-505');

        $this->actAs($hr);
        $this->get('/tickets/'.$other->reference)->assertOk()->assertSee('Mark Resolved');
        $this->get('/tickets/'.$notHrs->reference)->assertNotFound();
    }

    /* ── diagnostics ──────────────────────────────────────────────────────── */

    public function test_a_server_error_gets_a_code_on_the_page_and_in_the_log(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/__boom', fn () => throw new RuntimeException('The clock is broken'));

        $response = $this->get('/__boom')->assertStatus(500);

        $event = DB::table('diagnostic_events')->latest('id')->first();
        $this->assertSame('error', $event->kind);
        $this->assertSame('The clock is broken', $event->message);
        $response->assertSee($event->code);
    }

    public function test_a_refused_check_out_is_recorded_and_put_on_the_ticket_they_raise(): void
    {
        $employee = $this->anEmployee('EMP941', 'Cannot Check Out');
        $this->actAs($employee);

        $this->post('/attendance/check-out')->assertRedirect();

        $code = DB::table('diagnostic_events')->where('user_id', $employee->user_id)->value('code');
        $this->assertNotNull($code);

        $this->post('/tickets', [
            'type' => 'internal',
            'subject' => 'Check-out does not work',
            'description' => 'It says there is no check-in.',
        ])->assertRedirect();

        $note = TicketComment::where('ticket_id', Ticket::latest('id')->value('id'))->firstOrFail();
        $this->assertTrue($note->isInternal());
        $this->assertStringContainsString($code, $note->body);
        $this->assertStringContainsString('no check-in', $note->body);

        $this->signInAsAdmin();
        $this->get('/admin/diagnostics')->assertOk()->assertSee($code);
        $this->assertStringContainsString($code, $this->get('/admin/diagnostics/download')->assertOk()->getContent());
    }

    public function test_diagnostics_never_break_the_request_when_the_table_is_missing(): void
    {
        DB::statement('DROP TABLE diagnostic_events');
        $this->resetDiagnostics();

        $this->assertNull(Diagnostics::record(Diagnostics::ERROR, 'Nothing to write to'));

        $this->resetDiagnostics();
    }

    /* ── helpers ──────────────────────────────────────────────────────────── */

    protected function resetDiagnostics(): void
    {
        (fn () => self::$ready = null)->bindTo(null, Diagnostics::class)();
    }

    /**
     * @param  list<string>  $roles
     */
    protected function anEmployee(string $staffId, string $name, array $roles = ['employee']): Employee
    {
        $user = User::factory()->create([
            'user_id' => $staffId,
            'name' => $name,
            'account_type' => Realm::STAFF,
            'staff_kind' => 'employee',
            'status' => 'active',
        ]);
        $user->roles()->sync(Role::whereIn('role_key', $roles)->pluck('id'));

        return Employee::create(['user_id' => $user->id, 'joined_on' => Carbon::now()->subYear()]);
    }

    protected function actAs(Employee $employee): void
    {
        app(Rbac::class)->forget($employee->user);
        $this->actingAs($employee->user);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function anOpenDay(Employee $employee, array $attributes = []): AttendanceRecord
    {
        return AttendanceRecord::create($attributes + [
            'employee_id' => $employee->id,
            'date' => Carbon::today()->subDays(2),
            'check_in' => '09:15',
            'check_out' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function ticketBy(Employee $raiser, string $reference, array $attributes = []): Ticket
    {
        return Ticket::create($attributes + [
            'reference' => $reference,
            'type' => 'internal',
            'subject' => 'The check-out button failed',
            'description' => 'Pressed it, nothing happened.',
            'raised_by' => $raiser->id,
            'status' => 'unassigned',
        ]);
    }

    protected function reference(AttendanceRecord $record): string
    {
        return $record->fresh('employee.user')->reference();
    }
}
