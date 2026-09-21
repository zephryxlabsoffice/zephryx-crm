<?php

namespace App\Support\Demo;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sample invoices — fixture data for InvoiceSeeder, and nothing else reads it.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHY THIS CLASS GOT SMALLER
 *
 * Before 2026-09-21 this was a full stand-in for the `invoices` table — it
 * computed totals, statuses and money bags so the pages had something to show
 * before the database existed. That job is finished: `App\Models\Invoice` and
 * App\Support\InvoiceDirectory do all of it for real now, from real rows. What
 * is left is only what InvoiceSeeder needs to build those rows — a client, a
 * project, a currency, dates, an amount and a list of payments.
 *
 * `amount` REPLACES A LIST OF LINES, per the decision that ended the line-item
 * builder (2026-09-11): "invoices are uploaded like payslips, not generated."
 * Each figure below is what the old sample lines for that invoice used to sum
 * to, so the totals this fixture has always produced do not move.
 *
 * NO DOCUMENT IS SEEDED, on the same reasoning SalarySeeder gives for not
 * seeding a payslip file: there is no PDF to invent, and a row whose
 * `document_path` pointed at nothing would break the download route rather
 * than demonstrate it. A real file arrives when somebody uploads one.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class DemoInvoices
{
    public static function enabled(): bool
    {
        return app()->environment('local') && config('app.debug');
    }

    /**
     * Amounts and `payment.amount` are minor units throughout — paise for
     * INR, cents for USD. `issued_ago` / `due_in` / payment `ago` are day
     * offsets from today, so the sample set's due-date states stay reviewable
     * instead of drifting into "universally overdue" as the calendar moves.
     *
     * INV-2026-004 is cancelled and keeps its number — a tidy, gapless run of
     * live invoices would demonstrate nothing; the gap that is not a gap is
     * the point. The mixed currencies are deliberate too: USD is what makes
     * the KPI tiles bags rather than numbers.
     *
     * @return list<array<string, mixed>>
     */
    public static function rows(): array
    {
        return [
            [
                'id' => 'INV-2026-014', 'client' => 'DGL International School', 'project' => 'WD-2024-001',
                'currency' => 'INR', 'amount' => 5700000, 'issued_ago' => 4, 'due_in' => 26,
                'issued' => true, 'cancelled' => false, 'payments' => [],
                'notes' => 'Second milestone of the redesign engagement.',
            ],
            [
                'id' => 'INV-2026-013', 'client' => 'Innovate Hub', 'project' => 'EC-2024-005',
                'currency' => 'USD', 'amount' => 415000, 'issued_ago' => 9, 'due_in' => 21,
                'issued' => true, 'cancelled' => false,
                'payments' => [
                    ['ago' => 3, 'amount' => 200000, 'method' => 'Wire transfer', 'reference' => 'SWIFT-88214'],
                ],
                'notes' => 'Billed in USD at the client\'s request.',
            ],
            [
                'id' => 'INV-2026-012', 'client' => 'Bright Future Academy', 'project' => 'LMS-2024-009',
                'currency' => 'INR', 'amount' => 13500000, 'issued_ago' => 48, 'due_in' => -18,
                'issued' => true, 'cancelled' => false,
                'payments' => [
                    ['ago' => 30, 'amount' => 4000000, 'method' => 'NEFT', 'reference' => 'NEFT-4471902'],
                ],
                'notes' => 'Balance chased twice; finance contact on leave.',
            ],
            [
                'id' => 'INV-2026-011', 'client' => 'GreenLeaf Foods', 'project' => 'SMC-2024-002',
                'currency' => 'INR', 'amount' => 5000000, 'issued_ago' => 12, 'due_in' => -2,
                'issued' => true, 'cancelled' => false, 'payments' => [], 'notes' => '',
            ],
            [
                'id' => 'INV-2026-010', 'client' => 'TechNova Solutions', 'project' => 'CRM-2024-003',
                'currency' => 'INR', 'amount' => 12000000, 'issued_ago' => 20, 'due_in' => 10,
                'issued' => true, 'cancelled' => false,
                'payments' => [
                    ['ago' => 6, 'amount' => 6000000, 'method' => 'NEFT', 'reference' => 'NEFT-4468120'],
                ],
                'notes' => 'Client paid half up front as agreed.',
            ],
            [
                'id' => 'INV-2026-009', 'client' => 'Urban Nest Interiors', 'project' => 'ST-2024-011',
                'currency' => 'INR', 'amount' => 6500000, 'issued_ago' => 26, 'due_in' => -8,
                'issued' => true, 'cancelled' => false, 'payments' => [], 'notes' => '',
            ],
            [
                'id' => 'INV-2026-008', 'client' => 'MediCare Services', 'project' => 'CW-2024-007',
                'currency' => 'INR', 'amount' => 4800000, 'issued_ago' => 40, 'due_in' => -25,
                'issued' => true, 'cancelled' => false,
                'payments' => [
                    ['ago' => 22, 'amount' => 4800000, 'method' => 'UPI', 'reference' => 'UPI-2291884'],
                ],
                'notes' => '',
            ],
            [
                'id' => 'INV-2026-007', 'client' => 'Sunrise Logistics', 'project' => 'FL-2024-010',
                'currency' => 'INR', 'amount' => 2500000, 'issued_ago' => 0, 'due_in' => 30,
                'issued' => false, 'cancelled' => false, 'payments' => [],
                'notes' => 'Waiting on the signed scope before sending.',
            ],
            [
                'id' => 'INV-2026-006', 'client' => 'ABC Pvt Ltd', 'project' => 'BR-2024-004',
                'currency' => 'INR', 'amount' => 6000000, 'issued_ago' => 34, 'due_in' => -4,
                'issued' => true, 'cancelled' => false,
                'payments' => [
                    ['ago' => 10, 'amount' => 1000000, 'method' => 'Cheque', 'reference' => 'CHQ-004512'],
                ],
                'notes' => '',
            ],
            [
                'id' => 'INV-2026-005', 'client' => 'Kolkata Craft Collective', 'project' => 'PH-2024-012',
                'currency' => 'INR', 'amount' => 3600000, 'issued_ago' => 55, 'due_in' => -25,
                'issued' => true, 'cancelled' => false,
                'payments' => [
                    ['ago' => 28, 'amount' => 3600000, 'method' => 'UPI', 'reference' => 'UPI-2288301'],
                ],
                'notes' => '',
            ],
            [
                'id' => 'INV-2026-004', 'client' => 'Innovate Hub', 'project' => 'EC-2024-005',
                'currency' => 'USD', 'amount' => 280000, 'issued_ago' => 60, 'due_in' => -30,
                'issued' => true, 'cancelled' => true, 'payments' => [],
                'notes' => 'Cancelled — scope moved into INV-2026-013. Kept so the sequence has no gap.',
            ],
            [
                'id' => 'INV-2026-003', 'client' => 'DGL International School', 'project' => 'SEO-2024-008',
                'currency' => 'INR', 'amount' => 4500000, 'issued_ago' => 70, 'due_in' => -40,
                'issued' => true, 'cancelled' => false,
                'payments' => [
                    ['ago' => 45, 'amount' => 4500000, 'method' => 'NEFT', 'reference' => 'NEFT-4451007'],
                ],
                'notes' => '',
            ],
            [
                'id' => 'INV-2026-002', 'client' => 'Bright Future Academy', 'project' => 'APP-2024-006',
                'currency' => 'INR', 'amount' => 4000000, 'issued_ago' => 80, 'due_in' => -50,
                'issued' => true, 'cancelled' => false,
                'payments' => [
                    ['ago' => 52, 'amount' => 4000000, 'method' => 'NEFT', 'reference' => 'NEFT-4442188'],
                ],
                'notes' => '',
            ],
            [
                'id' => 'INV-2026-001', 'client' => 'TechNova Solutions', 'project' => 'CRM-2024-003',
                'currency' => 'INR', 'amount' => 2000000, 'issued_ago' => 95, 'due_in' => -65,
                'issued' => true, 'cancelled' => false,
                'payments' => [
                    ['ago' => 70, 'amount' => 2000000, 'method' => 'UPI', 'reference' => 'UPI-2270442'],
                ],
                'notes' => '',
            ],
        ];
    }

    /**
     * The dated rows, ready for InvoiceSeeder to write.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function all(): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        return collect(self::rows())->map(fn (array $row) => array_merge($row, [
            'invoice_date' => Carbon::today()->subDays($row['issued_ago'])->toDateString(),
            'due_date' => Carbon::today()->addDays($row['due_in'])->toDateString(),
            'payments' => array_map(fn (array $payment) => $payment + [
                'received_on' => Carbon::today()->subDays($payment['ago'])->toDateString(),
            ], $row['payments']),
        ]));
    }
}
