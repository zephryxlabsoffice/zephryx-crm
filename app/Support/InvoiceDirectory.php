<?php

namespace App\Support;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Invoices, read from the database.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * FILTERING BY STATUS HAPPENS IN PHP, AND THAT IS NOT AN OVERSIGHT
 *
 * Status is derived from the lines, the payments and today's date — there is no
 * column to put in a WHERE clause, deliberately. Writing one in SQL would mean
 * a second implementation of InvoicePresenter::statusOf living in a query, and
 * the two would disagree the first time either changed.
 *
 * So the rows are narrowed in SQL by everything that IS a column — client,
 * search, sent, cancelled — and the status filter is applied to what comes
 * back. At the scale this application bills at that is a few dozen rows; if it
 * ever is not, the fix is a materialised view, not a status column.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class InvoiceDirectory
{
    /**
     * The list query, narrowed by everything that is a column.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Invoice>
     */
    public static function query(array $filters = []): Builder
    {
        $search = ($filters['search'] ?? '') !== '' ? $filters['search'] : null;

        return Invoice::query()
            ->with(['client', 'project.client', 'lines', 'payments'])
            ->when($search, fn (Builder $q, string $term) => $q->where(function (Builder $q) use ($term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

                $q->where('number', 'like', $like)
                    ->orWhereHas('client', fn (Builder $c) => $c->where('name', 'like', $like));
            }))
            ->when($filters['client'] ?? null, fn (Builder $q, string $name) => $q->whereHas(
                'client', fn (Builder $c) => $c->where('name', $name)
            ))
            // Newest first: an invoice list is read from the top.
            ->orderByDesc('invoice_date')
            ->orderByDesc('id');
    }

    /**
     * Rows, with the status filter applied after they are built.
     *
     * @param  Builder<Invoice>  $query
     * @return Collection<int, array<string, mixed>>
     */
    public static function rows(Builder $query, ?string $status = null): Collection
    {
        return $query->get()
            ->map(fn (Invoice $i) => $i->toRecordArray())
            ->when($status, fn (Collection $rows) => $rows->where('status', $status))
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $number): ?array
    {
        $invoice = Invoice::query()
            ->with(['client', 'project.client', 'lines', 'payments.recorder.user'])
            ->where('number', $number)
            ->first();

        return $invoice === null ? null : $invoice->toRecordArray() + [
            'model' => $invoice,
            'project_record' => $invoice->project ? ProjectDirectory::row($invoice->project) : null,
        ];
    }

    /**
     * The number the next invoice would take.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * ISSUED INSIDE THE TRANSACTION THAT WRITES THE ROW
     *
     * This method is what the FORM shows, and it is a preview. The real
     * allocation happens in InvoiceController::store, inside a transaction and
     * against a locked read of the table — two people pressing Create at the
     * same second must not be handed the same number, and the unique index is
     * the last line of defence rather than the plan.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public static function nextNumber(): string
    {
        $prefix = 'INV-'.Carbon::today()->year.'-';

        $highest = Invoice::query()
            ->where('number', 'like', $prefix.'%')
            ->selectRaw('max(cast(substr(number, ?) as integer)) as n', [strlen($prefix) + 1])
            ->value('n');

        return $prefix.str_pad((string) (((int) $highest) + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * The headline figures.
     *
     * Money totals are BAGS, not numbers: a set of invoices in two currencies
     * has no single total, and adding rupees to dollars to get a KPI is the
     * kind of number somebody puts in a board pack.
     *
     * @param  Collection<int, array<string, mixed>>  $invoices
     * @return array<string, mixed>
     */
    public static function stats(Collection $invoices): array
    {
        $countOf = fn (string $status) => $invoices->where('status', $status)->count();

        $outstanding = MoneyBag::of(
            $invoices->filter(fn (array $i) => InvoicePresenter::isOutstanding($i))
                ->map(fn (array $i) => $i['balance'])
        );

        $overdue = MoneyBag::of(
            $invoices->where('status', InvoicePresenter::OVERDUE)->map(fn (array $i) => $i['balance'])
        );

        $collected = MoneyBag::of($invoices->map(fn (array $i) => $i['paid']));

        return [
            'total' => $invoices->count(),
            'draft' => $countOf(InvoicePresenter::DRAFT),
            'sent' => $countOf(InvoicePresenter::SENT),
            'partial' => $countOf(InvoicePresenter::PARTIAL),
            'paid' => $countOf(InvoicePresenter::PAID),
            'overdue' => $countOf(InvoicePresenter::OVERDUE),
            'cancelled' => $countOf(InvoicePresenter::CANCELLED),
            'outstanding' => $outstanding,
            'overdue_value' => $overdue,
            'collected' => $collected,
        ];
    }

    /**
     * The most recent payments across every invoice.
     *
     * @return list<array<string, mixed>>
     */
    public static function recentPayments(int $limit = 4): array
    {
        return DB::table('invoice_payments')
            ->join('invoices', 'invoices.id', '=', 'invoice_payments.invoice_id')
            ->join('clients', 'clients.id', '=', 'invoices.client_id')
            ->orderByDesc('invoice_payments.received_on')
            ->orderByDesc('invoice_payments.id')
            ->limit($limit)
            ->get([
                'invoice_payments.amount_minor',
                'invoice_payments.received_on',
                'invoice_payments.method',
                'invoice_payments.reference',
                'invoices.number',
                'invoices.currency',
                'clients.name as client',
            ])
            ->map(fn (object $row) => [
                'invoice' => $row->number,
                'client' => $row->client,
                'amount' => $row->amount_minor,
                'amount_money' => Money::of((int) $row->amount_minor, $row->currency),
                'received_on' => Carbon::parse($row->received_on)->toDateString(),
                'method' => $row->method,
                'reference' => $row->reference,
            ])
            ->all();
    }

    /**
     * One client's invoices — what the portal reads, scoped by the caller.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function forClient(string $clientName): Collection
    {
        return self::rows(self::query(['client' => $clientName]));
    }

    /**
     * The methods a payment can be recorded against.
     *
     * @return list<string>
     */
    public static function paymentMethods(): array
    {
        return (array) config('invoices.payment_methods', []);
    }
}
