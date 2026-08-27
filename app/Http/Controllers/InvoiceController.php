<?php

namespace App\Http\Controllers;

use App\Support\Demo\DemoClients;
use App\Support\Demo\DemoInvoices;
use App\Support\Demo\DemoProjects;
use App\Support\InvoicePresenter;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Invoices — what clients owe us and what has been received.
 *
 * Three pages: the list (`/invoices`), the invoice document
 * (`/invoices/{invoice}`) and the create form (`/invoices/create`).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FRONT END ONLY, and this module has four obligations the backend must honour:
 *
 * 1. AMOUNTS ARE INTEGER MINOR UNITS. No float touches money, at any layer —
 *    not the column, not the request, not the calculation. App\Support\Money is
 *    the only thing that should hold an amount.
 *
 * 2. TOTALS AND STATUS ARE DERIVED. The total is the sum of the lines; the
 *    status falls out of the payments and the due date. Neither is a column
 *    somebody can set, so neither can contradict the records beneath it.
 *
 * 3. OWNERSHIP. §6 names invoices as the canonical case: "a client requesting
 *    invoice 47 must be verified as the owner of invoice 47", enforced at the
 *    query layer. When the client realm gets an invoice view, it scopes on the
 *    signed-in client and re-checks on the detail route — a client reading
 *    another company's invoice reveals what we charge them.
 *
 * 4. NUMBERS ARE GAPLESS AND INVOICES ARE NEVER DELETED. The next number is
 *    issued by the database inside the transaction that writes the invoice, or
 *    two simultaneous creates collide. Withdrawal is a cancellation, not a
 *    DELETE.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class InvoiceController extends Controller
{
    protected const PER_PAGE = 8;

    /** Matches the 132×132 viewBox and 18px stroke the donut is drawn at. */
    protected const DONUT_RADIUS = 48;

    /**
     * GET /invoices
     */
    public function index(Request $request): Response
    {
        $tab = $request->validate([
            'tab' => ['nullable', Rule::in(['all', 'outstanding', 'overdue', 'paid', 'draft'])],
        ])['tab'] ?? 'all';

        $all = DemoInvoices::all();

        $invoices = match ($tab) {
            'outstanding' => $all->filter(fn (array $i) => InvoicePresenter::isOutstanding($i))->values(),
            'overdue' => $all->where('status', InvoicePresenter::OVERDUE)->values(),
            'paid' => $all->where('status', InvoicePresenter::PAID)->values(),
            'draft' => $all->where('status', InvoicePresenter::DRAFT)->values(),
            default => $all,
        };

        $filters = $this->filters($request);

        return response()->view('invoices.index', [
            'activeNav' => 'invoices',
            'invoices' => $this->paginate($this->matching($invoices, $filters), $request),
            'stats' => DemoInvoices::stats($all),
            'recentPayments' => DemoInvoices::recentPayments(),
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
        ] + $filters + $this->options());
    }

    /**
     * GET /invoices/{invoice} — the document.
     */
    public function show(string $invoice): Response
    {
        $record = DemoInvoices::find($invoice);

        abort_if($record === null, 404);

        return response()->view('invoices.show', [
            'activeNav' => 'invoices',
            'invoice' => $record,
            'client' => DemoClients::all()->firstWhere('name', $record['client']),
        ] + $this->options());
    }

    /**
     * GET /invoices/create
     */
    public function create(): Response
    {
        return response()->view('invoices.create', [
            'activeNav' => 'invoices',
            'nextNumber' => DemoInvoices::nextNumber(),
        ] + $this->options());
    }

    /**
     * The status breakdown behind the rail donut.
     *
     * Kept in status order rather than sorted by size, so the legend reads the
     * same way every time somebody opens the page — a legend that reshuffles as
     * the data moves is one nobody learns to scan. Statuses with nothing in
     * them are dropped rather than drawn as a zero-width segment.
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
                    mb_strtolower($i['id'].' '.$i['client'].' '.($i['project_record']['name'] ?? '')),
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
            'clientOptions' => DemoClients::all()->pluck('name')->all(),
            'projectOptions' => DemoProjects::all()
                ->map(fn (array $p) => ['id' => $p['id'], 'name' => $p['name'], 'client' => $p['client']])
                ->values()
                ->all(),
            'paymentMethods' => DemoInvoices::paymentMethods(),
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
