<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePayment;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\InvoicePresenter as P;
use App\Support\Rbac\Rbac;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Invoices — raising one, sending it, recording money, and cancelling it.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FOUR RULES, AND EVERY TEST BELOW IS ONE OF THEM
 *
 * Amounts stay integer minor units. Totals and status are derived, never set.
 * Numbers are gapless and issued inside the transaction. Nothing is ever
 * deleted — a cancelled invoice keeps its number.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class InvoiceWritesTest extends TestCase
{
    /* ══════════════════════════════════════════════════════════════════════
       THE GUARDS
       ══════════════════════════════════════════════════════════════════════ */

    public function test_reading_invoices_and_changing_them_are_different_permissions(): void
    {
        // Somebody trusted to see what a client owes is not thereby somebody
        // who may raise an invoice or record a payment.
        $invoice = $this->anInvoice();

        $this->signInAsStaff(['employee', 'mentor']);
        $this->grant('invoices.view');

        $this->get('/invoices')->assertOk();
        $this->get('/invoices/create')->assertForbidden();
        $this->post('/invoices/'.$invoice->number.'/send')->assertForbidden();
    }

    /* ══════════════════════════════════════════════════════════════════════
       RAISING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_an_invoice_is_created_as_a_draft(): void
    {
        // An invoice that reaches a client the instant somebody finishes typing
        // is one nobody can check first.
        $this->signInAsFinance();

        $this->post('/invoices', $this->validPayload())->assertRedirect();

        $invoice = Invoice::with(['lines', 'payments'])->firstOrFail();

        $this->assertNull($invoice->sent_at);
        $this->assertSame(P::DRAFT, $invoice->status());
    }

    public function test_the_number_is_allocated_by_the_server_and_is_gapless(): void
    {
        $this->signInAsFinance();

        $this->post('/invoices', $this->validPayload(['number' => 'INV-9999-999']));
        $this->post('/invoices', $this->validPayload(['number' => 'INV-9999-998']));

        $numbers = Invoice::orderBy('id')->pluck('number')->all();

        $this->assertSame(
            ['INV-'.now()->year.'-001', 'INV-'.now()->year.'-002'],
            $numbers,
            'a number posted with the form was honoured',
        );
    }

    public function test_the_total_is_the_sum_of_the_lines_and_is_not_stored(): void
    {
        /*
         * 2 × 1,500.50 plus 1 × 45,000 — chosen so a float would round it
         * wrong. Everything stays in integer paise.
         */
        $this->signInAsFinance();

        $this->post('/invoices', $this->validPayload([
            'lines' => [
                ['description' => 'Training sessions', 'qty' => 2, 'unit' => '1500.50'],
                ['description' => 'Design phase', 'qty' => 1, 'unit' => '45000'],
            ],
        ]))->assertRedirect();

        $invoice = Invoice::with(['lines', 'payments'])->firstOrFail();

        $this->assertSame(4800100, $invoice->total()->minor);
        // No column holds it. If one ever appears, this fails.
        $this->assertArrayNotHasKey('total', $invoice->getAttributes());
        $this->assertArrayNotHasKey('status', $invoice->getAttributes());
    }

    public function test_blank_lines_are_dropped_rather_than_refused(): void
    {
        // The form offers three rows and most invoices use one. Refusing the
        // empty ones would make somebody delete placeholder text to save.
        $this->signInAsFinance();

        $this->post('/invoices', $this->validPayload([
            'lines' => [
                ['description' => 'The only line', 'qty' => 1, 'unit' => '1000'],
                ['description' => '', 'qty' => 1, 'unit' => ''],
                ['description' => '', 'qty' => 1, 'unit' => ''],
            ],
        ]))->assertRedirect();

        $this->assertSame(1, InvoiceLine::count());
    }

    public function test_an_invoice_with_no_lines_at_all_is_refused(): void
    {
        $this->signInAsFinance();

        $this->post('/invoices', $this->validPayload([
            'lines' => [['description' => '', 'qty' => 1, 'unit' => '']],
        ]))->assertSessionHasErrors('lines');

        $this->assertSame(0, Invoice::count());
    }

    public function test_a_project_belonging_to_another_client_is_refused(): void
    {
        // Otherwise one client's work ends up on another's invoice.
        $theirs = Client::create(['reference' => 'CLT911', 'name' => 'Another Client', 'status' => 'active']);
        $project = Project::create([
            'reference' => 'PRJ-OTHER-9', 'name' => 'Their Project', 'client_id' => $theirs->id,
            'progress' => 0, 'status' => 'planning', 'priority' => 'medium',
            'deadline' => Carbon::today()->addMonth(),
        ]);

        $this->signInAsFinance();

        $this->post('/invoices', $this->validPayload(['project_id' => $project->id]))
            ->assertSessionHasErrors('project_id');
    }

    /* ══════════════════════════════════════════════════════════════════════
       SENDING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_sending_stamps_when_and_a_second_send_does_not_move_it(): void
    {
        $invoice = $this->anInvoice();
        $this->signInAsFinance();

        $this->post('/invoices/'.$invoice->number.'/send')->assertRedirect();
        $first = $invoice->fresh()->sent_at;

        $this->assertNotNull($first);

        Carbon::setTestNow(Carbon::now()->addHour());
        $this->post('/invoices/'.$invoice->number.'/send')->assertRedirect();
        Carbon::setTestNow();

        $this->assertEquals($first, $invoice->fresh()->sent_at);
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::INVOICE_SENT)->count());
    }

    public function test_an_invoice_with_no_lines_cannot_be_sent(): void
    {
        // An invoice for nothing is not a document to put in front of a client.
        $invoice = $this->anInvoice(lines: false);
        $this->signInAsFinance();

        $this->post('/invoices/'.$invoice->number.'/send')->assertSessionHasErrors('send');

        $this->assertNull($invoice->fresh()->sent_at);
    }

    /* ══════════════════════════════════════════════════════════════════════
       PAYMENTS
       ══════════════════════════════════════════════════════════════════════ */

    public function test_recording_a_payment_is_what_makes_an_invoice_paid(): void
    {
        // There is no "mark as paid" anywhere in this module: status is derived
        // from the ledger, so marking without recording would be marking
        // without evidence.
        $invoice = $this->anInvoice(sent: true);
        $this->signInAsFinance();

        $this->post('/invoices/'.$invoice->number.'/payments', [
            'amount' => '10000',
            'received_on' => Carbon::today()->toDateString(),
            'method' => 'NEFT',
            'reference' => 'NEFT-1',
        ])->assertRedirect();

        $invoice = $invoice->fresh(['lines', 'payments']);

        $this->assertSame(1000000, $invoice->paid()->minor);
        $this->assertSame(P::PAID, $invoice->status());
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::INVOICE_PAYMENT_RECORDED)->count());
    }

    public function test_a_part_payment_leaves_it_partly_paid(): void
    {
        $invoice = $this->anInvoice(sent: true);
        $this->signInAsFinance();

        $this->post('/invoices/'.$invoice->number.'/payments', [
            'amount' => '4000',
            'received_on' => Carbon::today()->toDateString(),
            'method' => 'UPI',
        ])->assertRedirect();

        $invoice = $invoice->fresh(['lines', 'payments']);

        $this->assertSame(P::PARTIAL, $invoice->status());
        $this->assertSame(600000, $invoice->balance()->minor);
    }

    public function test_more_than_the_balance_is_refused_rather_than_absorbed(): void
    {
        /*
         * Money arriving that nobody expected is a conversation with the
         * client, not a number to round away — and an invoice showing a
         * negative balance is a page nobody trusts.
         */
        $invoice = $this->anInvoice(sent: true);
        $this->signInAsFinance();

        $this->post('/invoices/'.$invoice->number.'/payments', [
            'amount' => '10001',
            'received_on' => Carbon::today()->toDateString(),
            'method' => 'NEFT',
        ])->assertSessionHasErrors('amount');

        $this->assertSame(0, InvoicePayment::count());
    }

    public function test_a_draft_takes_no_payment(): void
    {
        $invoice = $this->anInvoice();
        $this->signInAsFinance();

        $this->post('/invoices/'.$invoice->number.'/payments', [
            'amount' => '100',
            'received_on' => Carbon::today()->toDateString(),
            'method' => 'Cash',
        ])->assertSessionHasErrors('amount');
    }

    public function test_a_payment_records_who_took_it_and_when_it_arrived(): void
    {
        // A payment is a real event: reconciling it against a bank statement
        // needs the date, the method and the reference.
        $invoice = $this->anInvoice(sent: true);
        $me = $this->signInAsFinance();

        $this->post('/invoices/'.$invoice->number.'/payments', [
            'amount' => '2500',
            'received_on' => Carbon::today()->subDays(2)->toDateString(),
            'method' => 'Cheque',
            'reference' => 'CHQ-99120',
        ]);

        $payment = InvoicePayment::firstOrFail();

        $this->assertSame(250000, $payment->amount_minor);
        $this->assertSame('CHQ-99120', $payment->reference);
        $this->assertSame($me->id, $payment->recorded_by);
        $this->assertTrue($payment->received_on->isSameDay(Carbon::today()->subDays(2)));
    }

    /* ══════════════════════════════════════════════════════════════════════
       CANCELLING — AND THE NUMBER STAYS
       ══════════════════════════════════════════════════════════════════════ */

    public function test_cancelling_keeps_the_record_and_its_number(): void
    {
        $invoice = $this->anInvoice(sent: true);
        $this->signInAsFinance();

        $this->post('/invoices/'.$invoice->number.'/cancel', [
            'reason' => 'Raised against the wrong project and reissued.',
        ])->assertRedirect();

        $invoice = $invoice->fresh(['lines', 'payments']);

        $this->assertNotNull($invoice->cancelled_at);
        $this->assertSame(P::CANCELLED, $invoice->status());
        $this->assertSame('INV-TEST-001', $invoice->number);
        $this->assertStringContainsString('wrong project', $invoice->cancellation_reason);
    }

    public function test_cancelling_needs_a_reason(): void
    {
        // A cancelled invoice with no explanation is one somebody has to
        // reconstruct from memory a year later.
        $invoice = $this->anInvoice(sent: true);
        $this->signInAsFinance();

        $this->post('/invoices/'.$invoice->number.'/cancel', ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertNull($invoice->fresh()->cancelled_at);
    }

    public function test_an_invoice_with_money_against_it_cannot_be_cancelled(): void
    {
        /*
         * That payment would be left attached to a withdrawn document. It needs
         * a credit note, which is its own record and its own decision.
         */
        $invoice = $this->anInvoice(sent: true);
        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'amount_minor' => 100000,
            'received_on' => Carbon::today(),
            'method' => 'NEFT',
        ]);

        $this->signInAsFinance();

        $this->post('/invoices/'.$invoice->number.'/cancel', ['reason' => 'Changed our minds about it.'])
            ->assertSessionHasErrors('reason');

        $this->assertNull($invoice->fresh()->cancelled_at);
    }

    public function test_a_cancelled_invoice_is_not_overdue_and_owes_nothing(): void
    {
        // Nobody owes a withdrawn invoice, however long ago its date passed.
        $invoice = $this->anInvoice(sent: true, due: Carbon::today()->subMonth());
        $this->signInAsFinance();

        $this->post('/invoices/'.$invoice->number.'/cancel', ['reason' => 'Duplicate of INV-TEST-002.']);

        $row = $invoice->fresh(['lines', 'payments'])->toRecordArray();

        $this->assertSame(P::CANCELLED, $row['status']);
        $this->assertFalse(P::isOutstanding($row));
    }

    public function test_there_is_no_delete_route_anywhere_in_the_module(): void
    {
        // A number must never leave the sequence.
        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'invoices')) {
                continue;
            }

            $this->assertNotContains('DELETE', $route->methods(), "a DELETE route exists at {$route->uri()}");
        }
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
            'client_id' => $this->aClient()->id,
            'currency' => 'INR',
            'invoice_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(30)->toDateString(),
            'lines' => [
                ['description' => 'Website redesign — design phase', 'qty' => 1, 'unit' => '45000'],
            ],
        ];
    }

    protected function aClient(): Client
    {
        return Client::firstOrCreate(
            ['name' => 'A Client'],
            ['reference' => 'CLT910', 'status' => 'active'],
        );
    }

    protected function anInvoice(bool $sent = false, bool $lines = true, ?Carbon $due = null): Invoice
    {
        $invoice = Invoice::create([
            'number' => 'INV-TEST-001',
            'client_id' => $this->aClient()->id,
            'currency' => 'INR',
            'invoice_date' => Carbon::today()->subDays(3),
            'due_date' => $due ?? Carbon::today()->addDays(27),
            'sent_at' => $sent ? Carbon::now()->subDay() : null,
        ]);

        if ($lines) {
            InvoiceLine::create([
                'invoice_id' => $invoice->id,
                'description' => 'One thing',
                'quantity' => 1,
                'unit_price_minor' => 1000000,
                'position' => 0,
            ]);
        }

        return $invoice->fresh(['lines', 'payments']);
    }

    protected function signInAsFinance(): Employee
    {
        $user = User::factory()->create([
            'user_id' => 'EMP910',
            'account_type' => 'staff',
            'staff_kind' => 'employee',
            'status' => 'active',
        ]);

        $user->roles()->sync(Role::whereIn('role_key', ['employee', 'ceo'])->pluck('id'));

        app(Rbac::class)->forget($user);
        $this->actingAs($user);

        return Employee::create(['user_id' => $user->id, 'joined_on' => Carbon::now()->subYear()]);
    }

    /**
     * Give the signed-in account extra permissions without inventing a role.
     */
    protected function grant(string ...$permissions): void
    {
        $role = Role::firstOrCreate(
            ['role_key' => 'test_grant'],
            ['role_name' => 'Test grant', 'is_active' => true],
        );

        $role->permissions()->syncWithoutDetaching(
            \App\Models\Permission::whereIn('permission_key', $permissions)->pluck('id')
        );

        auth()->user()->roles()->syncWithoutDetaching([$role->id]);
        app(Rbac::class)->forget(auth()->user());
    }
}
