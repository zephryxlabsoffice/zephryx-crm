<?php

namespace App\Http\Controllers\Client;

use App\Support\Demo\DemoClientPortal;
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
 * sequence. That filter lives in DemoClientPortal::invoices, so it applies to
 * the tiles and the detail lookup as well as the table.
 */
class InvoiceController extends PortalController
{
    protected const PER_PAGE = 10;

    public function index(Request $request): Response
    {
        $client = $this->client($request);

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(InvoicePresenter::statusOptions())],
        ]);

        $invoices = DemoClientPortal::invoices($client);

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
            'stats' => DemoClientPortal::stats($client)['invoices'],
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
        $record = DemoClientPortal::invoice($this->client($request), $invoice);

        // Somebody else's invoice and an imaginary one get the same answer.
        abort_if($record === null, 404);

        return response()->view('client.invoices.show', $this->shell($request, 'invoices') + [
            'invoice' => $this->decorate($record),
        ]);
    }

    /**
     * Attach the project the document names.
     *
     * Resolved through DemoClientPortal rather than DemoProjects, so it is
     * scoped like everything else. An invoice referencing a project that is not
     * this client's would render without the name instead of fetching it —
     * which should never happen, and if it does, is a data problem worth seeing
     * rather than papering over.
     *
     * @param  array<string, mixed>  $invoice
     * @return array<string, mixed>
     */
    protected function decorate(array $invoice): array
    {
        $project = $invoice['project'] === null
            ? null
            : DemoClientPortal::project($invoice['client'], $invoice['project']);

        return $invoice + ['project_record' => $project];
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
