<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\InvoicePresenter as P;
use App\Support\Rbac\Rbac;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Invoices — raising one, sending it, recording money, and cancelling it.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FIVE RULES, AND EVERY TEST BELOW IS ONE OF THEM
 *
 * Amounts stay integer minor units. The total is typed once, like a payslip's
 * net figure, and it is still a method rather than a column. Numbers are
 * gapless and issued inside the transaction. Nothing is ever deleted — a
 * cancelled invoice keeps its number. And an invoice is an uploaded document
 * (decided 2026-09-11): it cannot be sent without one, and the upload goes
 * through the same Drive-backed DocumentStore payslips and profile documents
 * use.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class InvoiceWritesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The local-fake-file fixtures (anInvoice()'s document) go here;
        // nothing here writes to the real storage directory.
        Storage::fake('local');
    }

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
        $this->connectGoogleDrive();
        $this->fakeDriveUpload();

        $this->post('/invoices', $this->validPayload())->assertRedirect();

        $invoice = Invoice::with('payments')->firstOrFail();

        $this->assertNull($invoice->sent_at);
        $this->assertSame(P::DRAFT, $invoice->status());
    }

    public function test_the_number_is_allocated_by_the_server_and_is_gapless(): void
    {
        $this->signInAsFinance();
        $this->connectGoogleDrive();
        $this->fakeDriveUpload();

        $this->post('/invoices', $this->validPayload(['number' => 'INV-9999-999']));
        $this->post('/invoices', $this->validPayload(['number' => 'INV-9999-998']));

        $numbers = Invoice::orderBy('id')->pluck('number')->all();

        $this->assertSame(
            ['INV-'.now()->year.'-001', 'INV-'.now()->year.'-002'],
            $numbers,
            'a number posted with the form was honoured',
        );
    }

    public function test_the_amount_is_typed_once_and_is_not_a_separate_total_column(): void
    {
        // 45,678.90 — chosen so a float would round it wrong. It stays in
        // integer paise the whole way through.
        $this->signInAsFinance();
        $this->connectGoogleDrive();
        $this->fakeDriveUpload();

        $this->post('/invoices', $this->validPayload(['amount' => '45678.90']))
            ->assertRedirect();

        $invoice = Invoice::with('payments')->firstOrFail();

        $this->assertSame(4567890, $invoice->amount_minor);
        $this->assertSame(4567890, $invoice->total()->minor);
        // No column holds a separately-computed total. If one ever appears,
        // this fails.
        $this->assertArrayNotHasKey('total', $invoice->getAttributes());
        $this->assertArrayNotHasKey('status', $invoice->getAttributes());
    }

    public function test_an_invoice_with_no_amount_is_refused(): void
    {
        $this->signInAsFinance();

        $this->post('/invoices', $this->validPayload(['amount' => '']))
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Invoice::count());
    }

    public function test_an_invoice_with_no_document_is_refused(): void
    {
        $this->signInAsFinance();

        $this->post('/invoices', $this->validPayload(['document' => null]))
            ->assertSessionHasErrors('document');

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

    public function test_a_drive_failure_creating_the_invoice_leaves_a_draft_with_no_document_rather_than_nothing(): void
    {
        /*
         * The number allocation is a short, DB-only transaction; the upload
         * happens after it commits (see InvoiceController::store). If Drive
         * refuses, the invoice the person just typed up must not vanish —
         * only `send()` has to notice the document is missing.
         */
        $this->signInAsFinance();
        // Deliberately no connectGoogleDrive() — the upload fails.

        $this->post('/invoices', $this->validPayload())->assertRedirect();

        $invoice = Invoice::firstOrFail();

        $this->assertFalse($invoice->hasDocument());
        $this->assertNull($invoice->sent_at);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE DOCUMENT
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_document_can_be_attached_after_the_invoice_is_created(): void
    {
        // Recovers exactly the state the Drive-failure test above leaves.
        $invoice = $this->anInvoice(document: false);
        $this->signInAsFinance();
        $this->connectGoogleDrive();
        $this->fakeDriveUpload();

        $this->post('/invoices/'.$invoice->number.'/document', [
            'document' => UploadedFile::fake()->create('invoice.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        $this->assertTrue($invoice->fresh()->hasDocument());
    }

    public function test_replacing_the_document_forgets_the_old_one_and_is_audited(): void
    {
        $invoice = $this->anInvoice();
        $old = $invoice->document_path;
        $this->signInAsFinance();
        $this->connectGoogleDrive();
        $this->fakeDriveUpload();

        $this->post('/invoices/'.$invoice->number.'/document', [
            'document' => UploadedFile::fake()->create('corrected.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        $fresh = $invoice->fresh();

        $this->assertNotSame($old, $fresh->document_path);
        $this->assertFalse(Storage::disk('local')->exists($old), 'the superseded document was not forgotten');

        $entry = DB::table('audit_log')->where('action', AuditLog::INVOICE_DOCUMENT_ADDED)->latest('id')->first();
        $this->assertNotNull($entry);
        $this->assertStringContainsString('replaced', $entry->after_json);
    }

    public function test_nothing_can_be_attached_to_a_cancelled_invoice(): void
    {
        $invoice = $this->anInvoice(sent: true);
        $invoice->update(['cancelled_at' => now(), 'cancellation_reason' => 'Test.']);
        $this->signInAsFinance();

        $this->post('/invoices/'.$invoice->number.'/document', [
            'document' => UploadedFile::fake()->create('invoice.pdf', 20, 'application/pdf'),
        ])->assertSessionHasErrors('document');
    }

    public function test_a_document_upload_that_cannot_reach_drive_is_a_validation_error_not_a_500(): void
    {
        $invoice = $this->anInvoice(document: false);
        $this->signInAsFinance();
        // No connectGoogleDrive() — Drive is not connected.

        $this->post('/invoices/'.$invoice->number.'/document', [
            'document' => UploadedFile::fake()->create('invoice.pdf', 20, 'application/pdf'),
        ])->assertSessionHasErrors('document');
    }

    public function test_somebody_with_only_invoices_view_cannot_attach_a_document(): void
    {
        $invoice = $this->anInvoice();
        $this->signInAsStaff(['employee', 'mentor']);
        $this->grant('invoices.view');

        $this->post('/invoices/'.$invoice->number.'/document', [
            'document' => UploadedFile::fake()->create('invoice.pdf', 20, 'application/pdf'),
        ])->assertForbidden();
    }

    public function test_the_document_can_be_downloaded_and_viewed_inline(): void
    {
        $invoice = $this->anInvoice();
        $this->signInAsFinance();

        $download = $this->get('/invoices/'.$invoice->number.'/document/download');
        $download->assertOk();
        $this->assertStringContainsString('attachment', $download->headers->get('content-disposition'));

        $view = $this->get('/invoices/'.$invoice->number.'/document/view');
        $view->assertOk();
        $this->assertStringContainsString('inline', $view->headers->get('content-disposition'));

        $this->assertSame(
            2,
            DB::table('audit_log')->where('action', AuditLog::INVOICE_DOWNLOADED)->count(),
        );
    }

    public function test_an_invoice_with_no_document_404s_on_both_routes(): void
    {
        $invoice = $this->anInvoice(document: false);
        $this->signInAsFinance();

        $this->get('/invoices/'.$invoice->number.'/document/download')->assertNotFound();
        $this->get('/invoices/'.$invoice->number.'/document/view')->assertNotFound();
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

    public function test_an_invoice_with_no_document_cannot_be_sent(): void
    {
        // An invoice with nothing behind it is not a document to put in
        // front of a client — the same rule "no lines" used to express.
        $invoice = $this->anInvoice(document: false);
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

        $invoice = $invoice->fresh('payments');

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

        $invoice = $invoice->fresh('payments');

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

        $invoice = $invoice->fresh('payments');

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

        $row = $invoice->fresh('payments')->toRecordArray();

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
            'amount' => '45000',
            'document' => UploadedFile::fake()->create('invoice.pdf', 20, 'application/pdf'),
        ];
    }

    protected function aClient(): Client
    {
        return Client::firstOrCreate(
            ['name' => 'A Client'],
            ['reference' => 'CLT910', 'status' => 'active'],
        );
    }

    protected function anInvoice(bool $sent = false, bool $document = true, ?Carbon $due = null): Invoice
    {
        $documentPath = null;

        if ($document) {
            $documentPath = 'invoices/'.Str::random(8).'/stored.pdf';
            Storage::disk('local')->put($documentPath, 'not a real pdf');
        }

        $invoice = Invoice::create([
            'number' => 'INV-TEST-001',
            'client_id' => $this->aClient()->id,
            'currency' => 'INR',
            'amount_minor' => 1000000,
            'invoice_date' => Carbon::today()->subDays(3),
            'due_date' => $due ?? Carbon::today()->addDays(27),
            'sent_at' => $sent ? Carbon::now()->subDay() : null,
            'document_path' => $documentPath,
            'document_name' => $documentPath ? 'invoice.pdf' : null,
            'document_bytes' => $documentPath ? 14 : null,
        ]);

        return $invoice->fresh('payments');
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
            Permission::whereIn('permission_key', $permissions)->pluck('id')
        );

        auth()->user()->roles()->syncWithoutDetaching([$role->id]);
        app(Rbac::class)->forget(auth()->user());
    }
}
