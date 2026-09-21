<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Project;
use App\Support\Demo\DemoInvoices;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * The demo invoices and their payments. Local + debug only.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE CANCELLED ONE KEEPS ITS NUMBER, AND THAT IS THE POINT OF SEEDING IT
 *
 * INV-2026-004 is cancelled in the fixture and stays in the sequence here. A
 * seed with a tidy, gapless run of live invoices would demonstrate nothing; the
 * gap that is not a gap is the thing worth having on screen.
 *
 * NO DOCUMENT IS SEEDED, for the same reason SalarySeeder does not seed a
 * payslip file: there is no PDF to invent, and a row whose `document_path`
 * pointed at nothing would break the download route rather than demonstrate
 * it. The demo records carry the figure and the metadata; a real file arrives
 * when somebody uploads one from the invoice page.
 *
 * The mixed currencies are deliberate too: one invoice in USD is what makes the
 * KPI tiles bags rather than numbers, and a seed in one currency would let that
 * quietly regress.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        if (! (app()->environment('local') && config('app.debug'))) {
            return;
        }

        $clients = Client::pluck('id', 'name');
        $projects = Project::pluck('id', 'reference');
        $recorder = Employee::query()
            ->whereHas('user', fn ($q) => $q->where('user_id', 'EMP006'))
            ->value('id');

        foreach (DemoInvoices::all() as $row) {
            $clientId = $clients[$row['client']] ?? null;

            if ($clientId === null) {
                continue;
            }

            $invoice = Invoice::updateOrCreate(
                ['number' => $row['id']],
                [
                    'client_id' => $clientId,
                    'project_id' => $row['project'] ? ($projects[$row['project']] ?? null) : null,
                    'currency' => $row['currency'],
                    'amount_minor' => $row['amount'],
                    'invoice_date' => $row['invoice_date'],
                    'due_date' => $row['due_date'],
                    // The two stored facts. Everything else about where the
                    // invoice stands is derived from the amount and payments.
                    'sent_at' => $row['issued'] ? Carbon::parse($row['invoice_date'])->setTime(10, 0) : null,
                    'cancelled_at' => $row['cancelled'] ? Carbon::parse($row['invoice_date'])->addDay() : null,
                    'cancellation_reason' => $row['cancelled']
                        ? 'Raised against the wrong project and reissued.'
                        : null,
                    'notes' => $row['notes'] ?: null,
                ],
            );

            // Re-seeded whole: the fixture is the invoice, and a second run
            // must not double its payments.
            $invoice->payments()->delete();

            foreach ($row['payments'] as $payment) {
                InvoicePayment::create([
                    'invoice_id' => $invoice->id,
                    'amount_minor' => $payment['amount'],
                    'received_on' => $payment['received_on'],
                    'method' => $payment['method'],
                    'reference' => $payment['reference'],
                    'recorded_by' => $recorder,
                ]);
            }
        }
    }
}
