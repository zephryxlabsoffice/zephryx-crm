<?php

namespace App\Http\Controllers\Client;

use App\Models\Client;
use App\Models\Invoice;
use App\Support\Audit\AuditLog;
use App\Support\ClientPortal;
use App\Support\Documents\DocumentStore;
use App\Support\InvoicePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The client's invoices.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE DOCUMENT IS THE SAME DOCUMENT
 *
 * §9 makes this the one screen where the staff view and the client view must
 * show the same thing, and the reason is practical: this is the page that gets
 * argued about on a call. Since an invoice is an uploaded PDF now (decided
 * 2026-09-11), that requirement is met by construction — there is exactly one
 * file, wherever it lives, and both realms stream the same bytes back through
 * App\Support\Documents\DocumentStore. Nothing here re-derives a figure and
 * nothing re-renders the document as a second copy of itself.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Drafts never appear. An unsent invoice is a document somebody here is still
 * writing — figures that may change, and a number not yet committed to the
 * sequence. That filter lives in ClientPortal::invoices, so it applies to
 * the tiles and the detail lookup as well as the table.
 */
class InvoiceController extends PortalController
{
    protected const PER_PAGE = 10;

    public function __construct(protected AuditLog $audit, protected DocumentStore $documents) {}

    public function index(Request $request): Response
    {
        $client = $this->client($request);

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(InvoicePresenter::statusOptions())],
        ]);

        $invoices = ClientPortal::invoices($client);

        $matching = $invoices
            ->when(
                $filters['status'] ?? null,
                fn (Collection $rows, string $status) => $rows->filter(
                    fn (array $i) => InvoicePresenter::statusOf($i) === $status
                )
            )
            // Newest first. An invoice book is read from the recent end.
            ->sortByDesc('invoice_date')
            ->values();

        return response()->view('client.invoices.index', $this->shell($request, 'invoices') + [
            'invoices' => $this->paginate($matching, $request),
            // Computed from the same collection the table lists, so a tile and
            // the rows beneath it cannot contradict each other.
            'stats' => ClientPortal::stats($client)['invoices'],
            'status' => $filters['status'] ?? null,
            'recentlyPaid' => $invoices
                ->filter(fn (array $i) => InvoicePresenter::statusOf($i) === InvoicePresenter::PAID)
                ->sortByDesc('invoice_date')
                ->take(3)
                ->values(),
        ]);
    }

    public function show(Request $request, string $invoice): Response
    {
        $client = $this->client($request);

        $record = ClientPortal::invoice($client, $invoice);

        // Somebody else's invoice and an imaginary one get the same answer.
        abort_if($record === null, 404);

        return response()->view('client.invoices.show', $this->shell($request, 'invoices') + [
            'invoice' => $this->decorate($client, $record),
        ]);
    }

    /**
     * GET /client/invoices/{invoice}/document/view — the uploaded PDF, opened
     * rather than saved.
     */
    public function viewDocument(Request $request, string $invoice): StreamedResponse
    {
        $model = $this->documentFor($request, $invoice);

        $this->auditDocumentAccess($request, $model, 'Viewed');

        return $this->documents->viewInline($model->document_path, $model->document_name ?? $model->number.'.pdf');
    }

    /**
     * GET /client/invoices/{invoice}/document/download
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THE REAL FILE, THROUGH A ROUTE THAT CHECKS OWNERSHIP AND AUDITS IT
     *
     * Never a static path — the same rule as profile documents, for the same
     * reason: an invoice names what a company pays and for what. Ownership is
     * the query — `ClientPortal::invoice` cannot fetch somebody else's — and
     * the access is audited before anything is streamed, because an entry
     * written after a `return` is an entry that does not exist.
     *
     * Until 2026-09-21 this route generated a printable HTML page instead: the
     * host had no PDF library, so a real PDF could not be produced here.
     * Invoices becoming an upload rather than a generated document removed
     * that problem along with the question that caused it — the file this
     * route now serves is the one somebody in Finance attached, not something
     * built on the fly.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function downloadDocument(Request $request, string $invoice): StreamedResponse
    {
        $model = $this->documentFor($request, $invoice);

        $this->auditDocumentAccess($request, $model, 'Downloaded');

        return $this->documents->download($model->document_path, $model->document_name ?? $model->number.'.pdf');
    }

    protected function documentFor(Request $request, string $invoice): Invoice
    {
        $client = $this->client($request);

        $record = ClientPortal::invoice($client, $invoice);

        abort_if($record === null, 404);

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
            after: $verb.' '.$model->number,
            request: $request,
        );
    }

    /**
     * Attach the project the document names.
     *
     * Resolved through ClientPortal rather than InvoiceDirectory, so it is
     * scoped like everything else. An invoice referencing a project that is not
     * this client's would render without the name instead of fetching it —
     * which should never happen, and if it does, is a data problem worth seeing
     * rather than papering over.
     *
     * @param  array<string, mixed>  $invoice
     * @return array<string, mixed>
     */
    protected function decorate(Client $client, array $invoice): array
    {
        $project = $invoice['project'] === null
            ? null
            : ClientPortal::project($client, $invoice['project']);

        // `array_merge`, not `+`: the invoice row already carries a
        // `project_record` built for the staff page, and the union operator
        // keeps the left side's value for a key that exists — so this would
        // have been a no-op that looked like a scope.
        return array_merge($invoice, ['project_record' => $project]);
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
