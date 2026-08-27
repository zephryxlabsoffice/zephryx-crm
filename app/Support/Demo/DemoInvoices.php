<?php

namespace App\Support\Demo;

use App\Support\InvoicePresenter;
use App\Support\Money;
use App\Support\MoneyBag;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sample invoices for reviewing the Invoices pages before the database exists.
 *
 * Local + debug only, like the other demo sources. Clients and projects are
 * drawn from DemoClients and DemoProjects so the modules agree with each other.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FOUR THINGS HERE ARE THE MODULE'S CONTRACT, NOT SAMPLE DATA
 *
 * 1. AMOUNTS ARE INTEGERS. Every figure below is in minor units — paise for
 *    INR, cents for USD. Nothing in this module holds a float. See App\Support\Money.
 *
 * 2. TOTALS ARE COMPUTED, NEVER STORED. An invoice's total is the sum of its
 *    lines; the amount paid is the sum of its payments. There is no `total`
 *    column to drift out of step with the lines that produced it.
 *
 * 3. STATUS IS DERIVED. See App\Support\InvoicePresenter — nobody sets an
 *    invoice to "Paid"; they record the payment that makes it paid.
 *
 * 4. NUMBERS ARE GAPLESS AND INVOICES ARE NEVER DELETED. INV-2026-004 below is
 *    cancelled and keeps its number. A missing number in a sequence is the
 *    first thing an auditor asks about, and "we deleted it" is the wrong
 *    answer in every jurisdiction. Cancel, or issue a credit note; never
 *    DELETE. This costs nothing now and is most of the work of adding GST later.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class DemoInvoices
{
    public static function enabled(): bool
    {
        return app()->environment('local') && config('app.debug');
    }

    /**
     * The raw rows. `issued_ago` and `due_in` are day offsets from today, for
     * the same reason DemoProjects uses them: fixed 2024 dates would read as
     * universally overdue and make the due-date states impossible to review.
     *
     * @return list<array<string, mixed>>
     */
    protected static function rows(): array
    {
        return [
            [
                'id' => 'INV-2026-014', 'client' => 'DGL International School', 'project' => 'WD-2024-001',
                'currency' => 'INR', 'issued_ago' => 4, 'due_in' => 26, 'issued' => true, 'cancelled' => false,
                'lines' => [
                    ['description' => 'Website redesign — design phase', 'qty' => 1, 'unit' => 4500000],
                    ['description' => 'Content migration', 'qty' => 1, 'unit' => 1200000],
                ],
                'payments' => [],
                'notes' => 'Second milestone of the redesign engagement.',
            ],
            [
                'id' => 'INV-2026-013', 'client' => 'Innovate Hub', 'project' => 'EC-2024-005',
                'currency' => 'USD', 'issued_ago' => 9, 'due_in' => 21, 'issued' => true, 'cancelled' => false,
                'lines' => [
                    ['description' => 'E-commerce build — sprint 3', 'qty' => 1, 'unit' => 320000],
                    ['description' => 'Payment gateway integration', 'qty' => 1, 'unit' => 95000],
                ],
                'payments' => [
                    ['ago' => 3, 'amount' => 200000, 'method' => 'Wire transfer', 'reference' => 'SWIFT-88214'],
                ],
                'notes' => 'Billed in USD at the client\'s request.',
            ],
            [
                'id' => 'INV-2026-012', 'client' => 'Bright Future Academy', 'project' => 'LMS-2024-009',
                'currency' => 'INR', 'issued_ago' => 48, 'due_in' => -18, 'issued' => true, 'cancelled' => false,
                'lines' => [
                    ['description' => 'Learning platform — phase 1', 'qty' => 1, 'unit' => 9000000],
                    ['description' => 'Staff training sessions', 'qty' => 3, 'unit' => 1500000],
                ],
                'payments' => [
                    ['ago' => 30, 'amount' => 4000000, 'method' => 'NEFT', 'reference' => 'NEFT-4471902'],
                ],
                'notes' => 'Balance chased twice; finance contact on leave.',
            ],
            [
                'id' => 'INV-2026-011', 'client' => 'GreenLeaf Foods', 'project' => 'SMC-2024-002',
                'currency' => 'INR', 'issued_ago' => 12, 'due_in' => -2, 'issued' => true, 'cancelled' => false,
                'lines' => [
                    ['description' => 'Social media campaign — May retainer', 'qty' => 1, 'unit' => 5000000],
                ],
                'payments' => [],
                'notes' => '',
            ],
            [
                'id' => 'INV-2026-010', 'client' => 'TechNova Solutions', 'project' => 'CRM-2024-003',
                'currency' => 'INR', 'issued_ago' => 20, 'due_in' => 10, 'issued' => true, 'cancelled' => false,
                'lines' => [
                    ['description' => 'CRM setup — licences and configuration', 'qty' => 1, 'unit' => 8000000],
                    ['description' => 'Data import', 'qty' => 1, 'unit' => 4000000],
                ],
                'payments' => [
                    ['ago' => 6, 'amount' => 6000000, 'method' => 'NEFT', 'reference' => 'NEFT-4468120'],
                ],
                'notes' => 'Client paid half up front as agreed.',
            ],
            [
                'id' => 'INV-2026-009', 'client' => 'Urban Nest Interiors', 'project' => 'ST-2024-011',
                'currency' => 'INR', 'issued_ago' => 26, 'due_in' => -8, 'issued' => true, 'cancelled' => false,
                'lines' => [
                    ['description' => 'Storefront build — front end', 'qty' => 1, 'unit' => 6500000],
                ],
                'payments' => [],
                'notes' => '',
            ],
            [
                'id' => 'INV-2026-008', 'client' => 'MediCare Services', 'project' => 'CW-2024-007',
                'currency' => 'INR', 'issued_ago' => 40, 'due_in' => -25, 'issued' => true, 'cancelled' => false,
                'lines' => [
                    ['description' => 'Content writing — 12 articles', 'qty' => 12, 'unit' => 400000],
                ],
                'payments' => [
                    ['ago' => 22, 'amount' => 4800000, 'method' => 'UPI', 'reference' => 'UPI-2291884'],
                ],
                'notes' => '',
            ],
            [
                'id' => 'INV-2026-007', 'client' => 'Sunrise Logistics', 'project' => 'FL-2024-010',
                'currency' => 'INR', 'issued_ago' => 0, 'due_in' => 30, 'issued' => false, 'cancelled' => false,
                'lines' => [
                    ['description' => 'Fleet tracking dashboard — discovery', 'qty' => 1, 'unit' => 2500000],
                ],
                'payments' => [],
                'notes' => 'Waiting on the signed scope before sending.',
            ],
            [
                'id' => 'INV-2026-006', 'client' => 'ABC Pvt Ltd', 'project' => 'BR-2024-004',
                'currency' => 'INR', 'issued_ago' => 34, 'due_in' => -4, 'issued' => true, 'cancelled' => false,
                'lines' => [
                    ['description' => 'Branding kit — logo suite', 'qty' => 1, 'unit' => 3500000],
                    ['description' => 'Brand guidelines document', 'qty' => 1, 'unit' => 2500000],
                ],
                'payments' => [
                    ['ago' => 10, 'amount' => 1000000, 'method' => 'Cheque', 'reference' => 'CHQ-004512'],
                ],
                'notes' => '',
            ],
            [
                'id' => 'INV-2026-005', 'client' => 'Kolkata Craft Collective', 'project' => 'PH-2024-012',
                'currency' => 'INR', 'issued_ago' => 55, 'due_in' => -25, 'issued' => true, 'cancelled' => false,
                'lines' => [
                    ['description' => 'Brand photography — full day', 'qty' => 2, 'unit' => 1800000],
                ],
                'payments' => [
                    ['ago' => 28, 'amount' => 3600000, 'method' => 'UPI', 'reference' => 'UPI-2288301'],
                ],
                'notes' => '',
            ],
            [
                'id' => 'INV-2026-004', 'client' => 'Innovate Hub', 'project' => 'EC-2024-005',
                'currency' => 'USD', 'issued_ago' => 60, 'due_in' => -30, 'issued' => true, 'cancelled' => true,
                'lines' => [
                    ['description' => 'E-commerce build — sprint 2 (superseded)', 'qty' => 1, 'unit' => 280000],
                ],
                'payments' => [],
                'notes' => 'Cancelled — scope moved into INV-2026-013. Kept so the sequence has no gap.',
            ],
            [
                'id' => 'INV-2026-003', 'client' => 'DGL International School', 'project' => 'SEO-2024-008',
                'currency' => 'INR', 'issued_ago' => 70, 'due_in' => -40, 'issued' => true, 'cancelled' => false,
                'lines' => [
                    ['description' => 'SEO optimisation — quarterly retainer', 'qty' => 3, 'unit' => 1500000],
                ],
                'payments' => [
                    ['ago' => 45, 'amount' => 4500000, 'method' => 'NEFT', 'reference' => 'NEFT-4451007'],
                ],
                'notes' => '',
            ],
            [
                'id' => 'INV-2026-002', 'client' => 'Bright Future Academy', 'project' => 'APP-2024-006',
                'currency' => 'INR', 'issued_ago' => 80, 'due_in' => -50, 'issued' => true, 'cancelled' => false,
                'lines' => [
                    ['description' => 'Mobile app — discovery and wireframes', 'qty' => 1, 'unit' => 4000000],
                ],
                'payments' => [
                    ['ago' => 52, 'amount' => 4000000, 'method' => 'NEFT', 'reference' => 'NEFT-4442188'],
                ],
                'notes' => '',
            ],
            [
                'id' => 'INV-2026-001', 'client' => 'TechNova Solutions', 'project' => 'CRM-2024-003',
                'currency' => 'INR', 'issued_ago' => 95, 'due_in' => -65, 'issued' => true, 'cancelled' => false,
                'lines' => [
                    ['description' => 'CRM discovery workshop', 'qty' => 1, 'unit' => 2000000],
                ],
                'payments' => [
                    ['ago' => 70, 'amount' => 2000000, 'method' => 'UPI', 'reference' => 'UPI-2270442'],
                ],
                'notes' => '',
            ],
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function all(): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        $projects = DemoProjects::all()->keyBy('id');

        return collect(self::rows())->map(function (array $row) use ($projects) {
            $currency = $row['currency'];

            $lines = array_map(function (array $line) use ($currency) {
                $unit = Money::of($line['unit'], $currency);

                return $line + [
                    'unit_price' => $unit,
                    'amount' => $unit->times($line['qty']),
                ];
            }, $row['lines']);

            $payments = array_map(function (array $payment) use ($currency) {
                return $payment + [
                    'amount_money' => Money::of($payment['amount'], $currency),
                    'received_on' => Carbon::today()->subDays($payment['ago'])->toDateString(),
                ];
            }, $row['payments']);

            // Both totals are sums of the things beneath them, never a stored
            // figure that could disagree with them.
            $total = array_reduce(
                $lines,
                fn (Money $carry, array $line) => $carry->plus($line['amount']),
                Money::zero($currency)
            );

            $paid = array_reduce(
                $payments,
                fn (Money $carry, array $payment) => $carry->plus($payment['amount_money']),
                Money::zero($currency)
            );

            // array_merge, not `+`: the union operator keeps the LEFT value for
            // a duplicate key, which would silently throw away the enriched
            // `lines` and `payments` in favour of the raw ones.
            $invoice = array_merge($row, [
                'lines' => $lines,
                'payments' => $payments,
                'total' => $total,
                'paid' => $paid,
                'balance' => $total->minus($paid),
                'invoice_date' => Carbon::today()->subDays($row['issued_ago'])->toDateString(),
                'due_date' => Carbon::today()->addDays($row['due_in'])->toDateString(),
                'project_record' => $projects->get($row['project']),
            ]);

            $invoice['status'] = InvoicePresenter::statusOf($invoice);

            return $invoice;
        });
    }

    public static function find(string $id): ?array
    {
        return self::all()->firstWhere('id', $id);
    }

    /**
     * The number the next invoice would take.
     *
     * Sequential and gapless — see the header. Real numbering has to be issued
     * by the database inside the same transaction that writes the invoice, or
     * two people clicking "Create" at once get the same number.
     */
    public static function nextNumber(): string
    {
        $year = Carbon::today()->year;
        $highest = self::all()
            ->pluck('id')
            ->map(fn (string $id) => (int) substr($id, -3))
            ->max() ?? 0;

        return sprintf('INV-%d-%03d', $year, $highest + 1);
    }

    /**
     * @param  Collection<int, array<string, mixed>>|null  $invoices
     * @return array<string, mixed>
     */
    public static function stats(?Collection $invoices = null): array
    {
        $invoices ??= self::all();

        $countOf = fn (string $status) => $invoices->where('status', $status)->count();

        // Money totals are bags, not numbers — a mixed-currency set has no
        // single total. See App\Support\MoneyBag.
        $outstanding = MoneyBag::of(
            $invoices->filter(fn (array $i) => InvoicePresenter::isOutstanding($i))
                ->map(fn (array $i) => $i['balance'])
        );

        $collected = MoneyBag::of(
            $invoices->reject(fn (array $i) => $i['cancelled'])
                ->map(fn (array $i) => $i['paid'])
        );

        $overdueBag = MoneyBag::of(
            $invoices->where('status', InvoicePresenter::OVERDUE)->map(fn (array $i) => $i['balance'])
        );

        return [
            'total' => $invoices->count(),
            'draft' => $countOf(InvoicePresenter::DRAFT),
            'sent' => $countOf(InvoicePresenter::SENT),
            'partial' => $countOf(InvoicePresenter::PARTIAL),
            'paid' => $countOf(InvoicePresenter::PAID),
            'overdue' => $countOf(InvoicePresenter::OVERDUE),
            'cancelled' => $countOf(InvoicePresenter::CANCELLED),
            'outstanding' => $outstanding,
            'collected' => $collected,
            'overdue_value' => $overdueBag,
        ];
    }

    /**
     * Payments across every invoice, most recent first — the rail list.
     *
     * @return list<array<string, mixed>>
     */
    public static function recentPayments(int $limit = 4): array
    {
        if (! self::enabled()) {
            return [];
        }

        return self::all()
            ->flatMap(fn (array $invoice) => array_map(
                fn (array $payment) => $payment + [
                    'invoice' => $invoice['id'],
                    'client' => $invoice['client'],
                ],
                $invoice['payments']
            ))
            ->sortByDesc('received_on')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Invoices for one client — the shape the client realm will need, and a
     * reminder that its version must be scoped to the signed-in client (§6).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function forClient(string $client): Collection
    {
        return self::all()->where('client', $client)->values();
    }

    /**
     * The methods a payment can be recorded against. Master data (§8);
     * hard-coded here only until that module exists.
     *
     * @return list<string>
     */
    public static function paymentMethods(): array
    {
        return ['NEFT', 'UPI', 'Wire transfer', 'Cheque', 'Card', 'Cash'];
    }
}
