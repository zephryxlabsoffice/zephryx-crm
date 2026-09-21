<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Project;
use App\Support\Audit\AuditLog;
use App\Support\ClientDirectory;
use App\Support\Documents\DocumentStore;
use App\Support\InvoiceDirectory;
use App\Support\InvoicePresenter;
use App\Support\Money;
use App\Support\Rbac\Rbac;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Invoices — what clients owe us and what has been received.
 *
 * Three pages: the list (`/invoices`), the invoice document
 * (`/invoices/{invoice}`) and the create form (`/invoices/create`).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FOUR OBLIGATIONS
 *
 * 1. AMOUNTS ARE INTEGER MINOR UNITS. No float touches money at any layer — not
 *    the column, not the request, not the calculation. App\Support\Money is the
 *    only thing that holds an amount.
 *
 * 2. THE INVOICE IS AN UPLOADED DOCUMENT, NOT A GENERATED ONE (decided
 *    2026-09-11). The amount is typed once, the same way SalaryRecord's net
 *    figure is — this is not "less derived" than the old line-item sum, it is
 *    a different fact typed in a different number of fields. Status is still
 *    derived, from the amount, the payments and the dates.
 *
 * 3. OWNERSHIP. §6 names invoices as the canonical case: "a client requesting
 *    invoice 47 must be verified as the owner of invoice 47", enforced at the
 *    query layer. The client realm scopes on the signed-in client.
 *
 * 4. NUMBERS ARE GAPLESS AND INVOICES ARE NEVER DELETED. The number is issued
 *    inside the transaction that writes the invoice, against a locked read.
 *    Withdrawal is a cancellation that keeps the number.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class InvoiceController extends Controller
{
    protected const PER_PAGE = 8;

    /** Matches the 132×132 viewBox and 18px stroke the donut is drawn at. */
    protected const DONUT_RADIUS = 48;

    public function __construct(
        protected Rbac $rbac,
        protected AuditLog $audit,
        protected DocumentStore $documents,
    ) {}

    /**
     * GET /invoices
     */
    public function index(Request $request): Response
    {
        $tab = $request->validate([
            'tab' => ['nullable', Rule::in(['all', 'outstanding', 'overdue', 'paid', 'draft'])],
        ])['tab'] ?? 'all';

        $filters = $this->filters($request);

        /*
         * Everything, as rows, once. The tabs, the KPI bags and the donut are
         * all counts over the same set, and status is derived — so asking the
         * database five times would be five loads of the same rows plus five
         * re-derivations.
         */
        $all = InvoiceDirectory::rows(InvoiceDirectory::query());

        $invoices = match ($tab) {
            'outstanding' => $all->filter(fn (array $i) => InvoicePresenter::isOutstanding($i))->values(),
            'overdue' => $all->where('status', InvoicePresenter::OVERDUE)->values(),
            'paid' => $all->where('status', InvoicePresenter::PAID)->values(),
            'draft' => $all->where('status', InvoicePresenter::DRAFT)->values(),
            default => $all,
        };

        return response()->view('invoices.index', [
            'activeNav' => 'invoices',
            'invoices' => $this->paginate($this->matching($invoices, $filters), $request),
            'stats' => InvoiceDirectory::stats($all),
            'recentPayments' => InvoiceDirectory::recentPayments(),
            'mix' => $this->statusMix($all),
            'circumference' => 2 * M_PI * self::DONUT_RADIUS,
            'donutRadius' => self::DONUT_RADIUS,
            'tab' => $tab,
            'tabCounts' => [
                'all' => $all->count(),
                'outstanding' => $all->filter(fn (array $i) => InvoicePresenter::isOutstanding($i))->count(),
                'overdue' => $all->where('status', InvoicePresenter::OVERDUE)->count(),
                'paid' => $all->where('status', InvoicePresenter::PAID)->count(),
                'draft' => $all->where('status', InvoicePresenter::DRAFT)->count(),
            ],
            'mayManage' => $this->rbac->can($request->user(), 'invoices.manage'),
        ] + $filters + $this->options());
    }

    /**
     * GET /invoices/{invoice} — the document.
     */
    public function show(Request $request, string $invoice): Response
    {
        $record = $this->find($invoice);

        return response()->view('invoices.show', [
            'activeNav' => 'invoices',
            'invoice' => $record,
            'client' => $record['model']->client
                ? ClientDirectory::row($record['model']->client)
                : null,
            'mayManage' => $this->rbac->can($request->user(), 'invoices.manage'),
        ] + $this->options());
    }

    /**
     * GET /invoices/create
     */
    public function create(Request $request): Response
    {
        return response()->view('invoices.create', [
            'activeNav' => 'invoices',
            'nextNumber' => InvoiceDirectory::nextNumber(),
            'defaultDue' => Carbon::today()
                ->addDays((int) config('invoices.default_terms_days', 30))
                ->toDateString(),
        ] + $this->options());
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE WRITES
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * Raise an invoice.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THE NUMBER IS ALLOCATED INSIDE A TRANSACTION; THE UPLOAD NEVER IS
     *
     * The number is not read from the form — the form's copy is a preview,
     * and by the time somebody submits it another invoice may exist. The
     * allocation is a locked read of the table followed by the insert, in one
     * short, DB-only transaction, so two people pressing Create in the same
     * second get consecutive numbers rather than a unique-key error and a
     * lost invoice.
     *
     * The document upload happens AFTER that transaction commits, never
     * inside it — Drive is a network call, and a database lock has no
     * business waiting on one. If the upload fails, the invoice row already
     * exists (as a draft with no document yet) rather than being lost, and
     * the redirect says so: `send()` refuses a document-less invoice, so
     * nothing reaches a client half-finished.
     *
     * It is created as a DRAFT. Sending is a separate act, because an invoice
     * that reaches a client the instant somebody finishes typing is one nobody
     * can check first.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $invoice = DB::transaction(function () use ($data) {
            // Locked: the next number is decided against a read nobody else can
            // interleave with.
            Invoice::query()->lockForUpdate()->count();

            return Invoice::create([
                'number' => InvoiceDirectory::nextNumber(),
                'client_id' => $data['client_id'],
                'project_id' => $data['project_id'] ?? null,
                'currency' => $data['currency'],
                'amount_minor' => Money::fromMajor($data['amount'], $data['currency'])->minor,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'],
                'notes' => $data['notes'] ?? null,
            ]);
        });

        $this->audit->record(
            action: AuditLog::INVOICE_CREATED,
            actor: $request->user(),
            entityType: 'invoice',
            entityId: $invoice->number,
            after: $invoice->client?->name.' · '.$invoice->total()->format().' · due '
                .$invoice->due_date->format('d M Y'),
            request: $request,
        );

        $status = $invoice->number.' created as a draft. Send it when it has been checked.';
        $tone = 'success';

        try {
            $this->attachDocument($request, $invoice, $data['document']);
        } catch (Throwable $e) {
            $status = $invoice->number.' was created, but the document could not be stored: '
                .$e->getMessage().' Attach it again from the invoice page before sending.';
            $tone = 'warning';
        }

        return redirect()
            ->route('invoices.show', ['invoice' => $invoice->number])
            ->with('status', $status)
            ->with('status_tone', $tone);
    }

    /**
     * POST /invoices/{invoice}/document — attach or replace the PDF.
     *
     * The recovery path when `store()`'s own upload failed, and the ordinary
     * path for correcting a document before the invoice is sent. Reuses the
     * same audit action either way — see AuditLog::INVOICE_DOCUMENT_ADDED —
     * because "attached" and "replaced" are the same fact from the record's
     * point of view: which document is behind this invoice right now.
     */
    public function storeDocument(Request $request, string $invoice): RedirectResponse
    {
        $record = $this->find($invoice);
        $model = $record['model'];

        if ($model->isCancelled()) {
            throw ValidationException::withMessages([
                'document' => 'This invoice was cancelled. Nothing can be attached to it.',
            ]);
        }

        $data = $request->validate([
            'document' => [
                'required', 'file',
                'mimes:'.implode(',', DocumentStore::ALLOWED),
                'max:'.(int) (DocumentStore::MAX_BYTES / 1024),
            ],
        ]);

        try {
            $this->attachDocument($request, $model, $data['document']);
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'document' => 'Could not store the file: '.$e->getMessage(),
            ]);
        }

        return redirect()
            ->route('invoices.show', ['invoice' => $model->number])
            ->with('status', 'Document attached.')
            ->with('status_tone', 'success');
    }

    /**
     * GET /invoices/{invoice}/document/download
     */
    public function downloadDocument(Request $request, string $invoice): StreamedResponse
    {
        $model = $this->documentFor($invoice);

        $this->auditDocumentAccess($request, $model, 'Downloaded');

        return $this->documents->download($model->document_path, $model->document_name ?? $model->number.'.pdf');
    }

    /**
     * GET /invoices/{invoice}/document/view — opened rather than saved.
     */
    public function viewDocument(Request $request, string $invoice): StreamedResponse
    {
        $model = $this->documentFor($invoice);

        $this->auditDocumentAccess($request, $model, 'Viewed');

        return $this->documents->viewInline($model->document_path, $model->document_name ?? $model->number.'.pdf');
    }

    /**
     * Record money that arrived.
     *
     * The invoice's paid figure is the sum of these rows; nothing sets a status.
     * An overpayment is refused rather than absorbed — money arriving that
     * nobody expected is a conversation with the client, not a number to round
     * away, and an invoice showing a negative balance is a page nobody trusts.
     */
    public function storePayment(Request $request, string $invoice): RedirectResponse
    {
        $record = $this->find($invoice);
        $model = $record['model'];

        if ($model->isCancelled()) {
            throw ValidationException::withMessages([
                'amount' => 'This invoice was cancelled. A payment against it has to be recorded elsewhere.',
            ]);
        }

        if (! $model->isSent()) {
            throw ValidationException::withMessages([
                'amount' => 'This invoice has not been sent yet.',
            ]);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::in(InvoiceDirectory::paymentMethods())],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $amount = Money::fromMajor($data['amount'], $model->currency);

        if ($amount->minor > $model->balance()->minor) {
            throw ValidationException::withMessages([
                'amount' => 'That is more than the '.$model->balance()->format().' still outstanding.',
            ]);
        }

        InvoicePayment::create([
            'invoice_id' => $model->id,
            'amount_minor' => $amount->minor,
            'received_on' => $data['received_on'],
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'recorded_by' => $this->employeeFor($request)?->id,
        ]);

        $model->load('payments');

        $this->audit->record(
            action: AuditLog::INVOICE_PAYMENT_RECORDED,
            actor: $request->user(),
            entityType: 'invoice',
            entityId: $model->number,
            after: $amount->format().' by '.$data['method']
                .(($data['reference'] ?? null) ? ' ('.$data['reference'].')' : '')
                .' · '.$model->balance()->format().' still outstanding',
            request: $request,
        );

        return redirect()
            ->route('invoices.show', ['invoice' => $model->number])
            ->with('status', 'Payment recorded.')
            ->with('status_tone', 'success');
    }

    /**
     * Send it.
     *
     * Idempotent: `sent_at` is when it went, and sending twice does not move it
     * — the second press is a person checking, not a second invoice.
     */
    public function send(Request $request, string $invoice): RedirectResponse
    {
        $record = $this->find($invoice);
        $model = $record['model'];

        if ($model->isCancelled()) {
            throw ValidationException::withMessages([
                'send' => 'A cancelled invoice cannot be sent.',
            ]);
        }

        if (! $model->hasDocument()) {
            // An invoice with nothing behind it is not a document anybody
            // should be able to put in front of a client — the same rule
            // that used to be "no lines", read against the new shape.
            throw ValidationException::withMessages([
                'send' => 'This invoice has no document attached yet.',
            ]);
        }

        if ($model->isSent()) {
            return redirect()
                ->route('invoices.show', ['invoice' => $model->number])
                ->with('status', 'This was sent on '.$model->sent_at->format('d M Y').'.')
                ->with('status_tone', 'info');
        }

        $model->update(['sent_at' => now()]);

        $this->audit->record(
            action: AuditLog::INVOICE_SENT,
            actor: $request->user(),
            entityType: 'invoice',
            entityId: $model->number,
            after: 'Sent to '.$model->client?->name,
            request: $request,
        );

        return redirect()
            ->route('invoices.show', ['invoice' => $model->number])
            ->with('status', 'Marked as sent.')
            ->with('status_tone', 'success');
    }

    /**
     * Cancel it — and keep the number.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THIS IS WHY THERE IS NO DELETE ROUTE ANYWHERE IN THIS MODULE
     *
     * A missing number in a sequence is the first thing an auditor asks about,
     * and "we deleted it" is the wrong answer in every jurisdiction. The record
     * stays, the number stays in the sequence, and the reason is stored beside
     * it — because a cancelled invoice with no explanation is one somebody has
     * to reconstruct from memory a year later.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function cancel(Request $request, string $invoice): RedirectResponse
    {
        $record = $this->find($invoice);
        $model = $record['model'];

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if ($model->paid()->isPositive()) {
            /*
             * Money has already arrived against it. Cancelling would leave a
             * payment attached to an invoice nobody owes — that is a credit
             * note, which is a document this module does not issue yet, and
             * pretending otherwise would put the books out.
             */
            throw ValidationException::withMessages([
                'reason' => 'Payments have been recorded against this invoice. It needs a credit note rather than a cancellation.',
            ]);
        }

        if ($model->isCancelled()) {
            return redirect()
                ->route('invoices.show', ['invoice' => $model->number])
                ->with('status', 'This invoice was already cancelled.')
                ->with('status_tone', 'info');
        }

        $model->update([
            'cancelled_at' => now(),
            'cancellation_reason' => $data['reason'],
        ]);

        $this->audit->record(
            action: AuditLog::INVOICE_CANCELLED,
            actor: $request->user(),
            entityType: 'invoice',
            entityId: $model->number,
            before: $record['status'],
            after: 'Cancelled: '.$data['reason'],
            request: $request,
        );

        return redirect()
            ->route('invoices.show', ['invoice' => $model->number])
            ->with('status', 'Invoice cancelled. It keeps its number.')
            ->with('status_tone', 'info');
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE PIECES
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * Store or replace the document behind an invoice.
     *
     * A replaced document's old file goes. Nothing points at it any more, and
     * keeping a superseded invoice PDF with no record naming it is worse than
     * deleting it — the same rule SalaryController::storePayslip follows.
     */
    protected function attachDocument(Request $request, Invoice $invoice, UploadedFile $file): void
    {
        $wasAttached = $invoice->hasDocument();
        $previous = $invoice->document_path;

        $stored = $this->documents->put('invoices/'.$invoice->number, $file);

        $invoice->update([
            'document_path' => $stored['path'],
            'document_name' => $stored['name'],
            'document_bytes' => $stored['bytes'],
            'document_added_at' => now(),
            'document_added_by' => $this->employeeFor($request)?->id,
        ]);

        $this->documents->forget($previous);

        $this->audit->record(
            action: AuditLog::INVOICE_DOCUMENT_ADDED,
            actor: $request->user(),
            entityType: 'invoice',
            entityId: $invoice->number,
            after: $wasAttached ? 'Document replaced' : 'Document attached',
            request: $request,
        );
    }

    protected function documentFor(string $invoice): Invoice
    {
        $record = $this->find($invoice);
        $model = $record['model'];

        abort_if(! $model->hasDocument() || ! $this->documents->exists($model->document_path), 404);

        return $model;
    }

    protected function auditDocumentAccess(Request $request, Invoice $model, string $verb): void
    {
        $this->audit->record(
            action: AuditLog::INVOICE_DOWNLOADED,
            actor: $request->user(),
            entityType: 'invoice',
            entityId: $model->number,
            after: $verb.' the document',
            request: $request,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function find(string $number): array
    {
        $found = InvoiceDirectory::find($number);

        abort_if($found === null, 404);

        return $found;
    }

    protected function employeeFor(Request $request): ?Employee
    {
        return Employee::where('user_id', $request->user()?->id)->first();
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'client_id' => ['required', Rule::exists('clients', 'id')],
            'project_id' => ['nullable', Rule::exists('projects', 'id')],
            'currency' => ['required', Rule::in(Money::codes())],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'document' => [
                'required', 'file',
                'mimes:'.implode(',', DocumentStore::ALLOWED),
                'max:'.(int) (DocumentStore::MAX_BYTES / 1024),
            ],
        ]);

        if (($data['project_id'] ?? null) !== null) {
            /*
             * A project belonging to a different client would put one client's
             * work on another's invoice — checked here rather than trusted from
             * the form, because the dropdown filters by client in the browser
             * and the browser is not where that rule lives.
             */
            $project = Project::find($data['project_id']);

            if ($project !== null && $project->client_id !== (int) $data['client_id']) {
                throw ValidationException::withMessages([
                    'project_id' => 'That project belongs to a different client.',
                ]);
            }
        }

        return $data;
    }

    /**
     * The status breakdown behind the rail donut.
     *
     * @param  Collection<int, array<string, mixed>>  $invoices
     * @return list<array{status: string, name: string, count: int, share: float}>
     */
    protected function statusMix(Collection $invoices): array
    {
        $total = $invoices->count();

        if ($total === 0) {
            return [];
        }

        $mix = [];

        foreach (InvoicePresenter::statusOptions() as $status) {
            $count = $invoices->where('status', $status)->count();

            if ($count === 0) {
                continue;
            }

            $mix[] = [
                'status' => $status,
                'name' => InvoicePresenter::status($status)['label'],
                'count' => $count,
                'share' => round($count / $total * 100, 1),
            ];
        }

        return $mix;
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(InvoicePresenter::statusOptions())],
            'currency' => ['nullable', Rule::in(Money::codes())],
        ]);

        $search = trim($validated['q'] ?? '');

        return [
            'search' => $search,
            'status' => $validated['status'] ?? null,
            'currency' => $validated['currency'] ?? null,
            'filtered' => $search !== ''
                || ($validated['status'] ?? null) !== null
                || ($validated['currency'] ?? null) !== null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $invoices
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function matching(Collection $invoices, array $filters): Collection
    {
        return $invoices
            ->when($filters['search'] !== '', fn (Collection $rows) => $rows->filter(
                fn (array $i) => str_contains(
                    mb_strtolower($i['id'].' '.$i['client'].' '.($i['project'] ?? '')),
                    mb_strtolower($filters['search'])
                )
            ))
            ->when($filters['status'], fn (Collection $rows) => $rows->where('status', $filters['status']))
            ->when($filters['currency'], fn (Collection $rows) => $rows->where('currency', $filters['currency']))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    protected function options(): array
    {
        return [
            'currencies' => Money::options(),
            'clientOptions' => Client::query()->orderBy('name')->get(['id', 'name']),
            'projectOptions' => Project::query()
                ->with('client')
                ->orderBy('name')
                ->get()
                ->map(fn (Project $p) => [
                    'id' => $p->id,
                    'reference' => $p->reference,
                    'name' => $p->name,
                    'client' => $p->client?->name,
                ])
                ->all(),
            'paymentMethods' => InvoiceDirectory::paymentMethods(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function paginate(Collection $rows, Request $request): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            items: $rows->forPage($page, self::PER_PAGE)->values(),
            total: $rows->count(),
            perPage: self::PER_PAGE,
            currentPage: $page,
            options: ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
