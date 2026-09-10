<?php

namespace App\Http\Controllers\Client;

use App\Models\Client;
use App\Support\Audit\AuditLog;
use App\Support\ClientPortal;
use App\Support\InvoicePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * The client's invoices.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE DOCUMENT IS THE SAME DOCUMENT
 *
 * §9 makes this the one screen where the staff view and the client view must
 * show the same thing, and the reason is practical: this is the page that gets
 * argued about on a call. If our copy and their copy differ by a line, a date
 * or a total, the call is about the difference rather than the payment.
 *
 * So the detail page renders the same invoice partials the staff module built,
 * against the same records, with the same presenter. Nothing here re-derives a
 * figure and nothing re-words a status.
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

    public function __construct(protected AuditLog $audit)
    {
    }

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
     * GET /client/invoices/{invoice}/download
     *
     * ─────────────────────────────────────────────────────────────────────────
     * IT IS A PRINTABLE PAGE, NOT A PDF, AND THAT IS A DECISION
     *
     * §6 asked for a generated PDF streamed through an authorising route. This
     * host has no PDF library — no dompdf, no wkhtmltopdf, no imagick — and
     * adding one is a deployment decision rather than a code change, the same
     * wall the profile photo hit.
     *
     * So the route returns the invoice as a page built for printing, which
     * every browser turns into a PDF with one keystroke and which is generated
     * from the same figures as the screen. What it does NOT do is pretend: the
     * page says it is the printable version, and there is no file that claims
     * to be a PDF and is not.
     *
     * The two properties §6 actually cares about hold either way. The ownership
     * check is the query — `ClientPortal::invoice` cannot fetch somebody else's
     * — and the download is audited before anything is rendered, because an
     * entry written after a `return` is an entry that does not exist.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function download(Request $request, string $invoice): Response
    {
        $client = $this->client($request);

        $record = ClientPortal::invoice($client, $invoice);

        abort_if($record === null, 404);

        $this->audit->record(
            action: AuditLog::INVOICE_DOWNLOADED,
            actor: $request->user(),
            entityType: 'invoice',
            entityId: $record['id'],
            after: $client->name.' downloaded '.$record['id'],
            request: $request,
        );

        return response()->view('client.invoices.print', [
            'invoice' => $this->decorate($client, $record),
            'client' => $client,
        ]);
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
