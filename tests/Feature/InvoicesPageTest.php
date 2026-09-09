<?php

namespace Tests\Feature;

use App\Support\InvoiceDirectory;
use App\Support\InvoicePresenter;
use App\Support\Money;
use Tests\TestCase;

class InvoicesPageTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Every route in the staff realm is behind `realm:staff` now (§3.1), so
         * a page test has to be somebody. A CEO, because this file is about
         * what the page renders rather than about who may see it — the guard
         * and the permission filtering have their own tests.
         */
        $this->signInAsStaff();
    }
    /**
     * The demo invoices, their lines and their payments, as real rows.
     *
     * The environment flip this replaced did nothing once the module read the
     * `invoices` table: there was no row to find, and a test "passing" against
     * an empty page asserts the empty state while claiming to assert the list.
     */
    protected function withDemoData(): void
    {
        $this->seedDemoWorkforce();
    }

    /**
     * Every invoice, as rows.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function allInvoices(): \Illuminate\Support\Collection
    {
        return InvoiceDirectory::rows(InvoiceDirectory::query());
    }

    public function test_the_three_pages_render(): void
    {
        $this->withDemoData();

        $this->get('/invoices')->assertOk()->assertSee('Invoice Management', false);
        $this->get('/invoices/create')->assertOk()->assertSee('Create Invoice', false);
        $this->get('/invoices/INV-2026-012')->assertOk()->assertSee('Learning platform', false);
    }

    public function test_create_is_not_read_as_an_invoice_number(): void
    {
        $this->get('/invoices/create')->assertOk();
    }

    /* ────────────────  totals reconcile  ──────────────── */

    public function test_a_total_is_the_sum_of_its_lines(): void
    {
        // Not a stored column that could drift away from the rows above it.
        $this->withDemoData();

        foreach ($this->allInvoices() as $invoice) {
            $sum = Money::zero($invoice['currency']);

            foreach ($invoice['lines'] as $line) {
                $sum = $sum->plus($line['unit_price']->times($line['qty']));
            }

            $this->assertTrue(
                $sum->equals($invoice['total']),
                "{$invoice['id']}: total {$invoice['total']} does not match its lines ({$sum})"
            );
        }
    }

    public function test_the_amount_paid_is_the_sum_of_its_payments(): void
    {
        $this->withDemoData();

        foreach ($this->allInvoices() as $invoice) {
            $sum = Money::zero($invoice['currency']);

            foreach ($invoice['payments'] as $payment) {
                $sum = $sum->plus($payment['amount_money']);
            }

            $this->assertTrue($sum->equals($invoice['paid']), "{$invoice['id']}: paid does not match its payments");
            $this->assertTrue($invoice['total']->minus($sum)->equals($invoice['balance']), "{$invoice['id']}: balance does not reconcile");
        }
    }

    public function test_nobody_is_ever_recorded_as_paying_more_than_they_owe(): void
    {
        // Not a rule of arithmetic — a rule about the sample data being sane,
        // and about the backend refusing an overpayment rather than absorbing
        // it. An overpayment is a credit note, not a bigger number.
        $this->withDemoData();

        foreach ($this->allInvoices() as $invoice) {
            $this->assertFalse(
                $invoice['balance']->isNegative(),
                "{$invoice['id']}: more has been recorded against it than it is for"
            );
        }
    }

    public function test_every_line_and_payment_is_in_the_invoices_own_currency(): void
    {
        // Per-line currency is not a feature; it is a total that cannot be
        // computed.
        $this->withDemoData();

        foreach ($this->allInvoices() as $invoice) {
            foreach ($invoice['lines'] as $line) {
                $this->assertSame($invoice['currency'], $line['amount']->currency);
            }
            foreach ($invoice['payments'] as $payment) {
                $this->assertSame($invoice['currency'], $payment['amount_money']->currency);
            }
        }
    }

    public function test_no_amount_anywhere_is_held_as_a_float(): void
    {
        $this->withDemoData();

        foreach ($this->allInvoices() as $invoice) {
            foreach (['total', 'paid', 'balance'] as $field) {
                $this->assertInstanceOf(Money::class, $invoice[$field]);
                $this->assertIsInt($invoice[$field]->minor);
            }
        }
    }

    /* ────────────────  multi-currency  ──────────────── */

    public function test_a_mixed_currency_total_is_never_collapsed_into_one_figure(): void
    {
        $this->withDemoData();

        $stats = InvoiceDirectory::stats($this->allInvoices());

        // The sample set has to actually contain two currencies, or this test
        // proves nothing.
        $this->assertGreaterThan(1, $this->allInvoices()->pluck('currency')->unique()->count());
        $this->assertGreaterThan(1, $stats['collected']->currencyCount());

        $headline = $stats['collected']->headline();
        $this->assertStringContainsString('plus', $headline['note']);
    }

    public function test_the_summary_names_the_second_currency_on_the_page(): void
    {
        $this->withDemoData();

        $this->get('/invoices')->assertSee('plus $', false);
    }

    public function test_the_amount_column_carries_a_symbol_on_every_row(): void
    {
        // With a mixed list, a bare "2,000" under a header reading "Amount" is
        // genuinely ambiguous.
        $this->withDemoData();

        $html = $this->get('/invoices')->getContent();

        $this->assertStringContainsString('₹', $html);
        $this->assertStringContainsString('$', $html);
    }

    public function test_rupees_are_grouped_the_indian_way_on_the_page(): void
    {
        $this->withDemoData();

        $this->get('/invoices/INV-2026-012')->assertSee('₹1,35,000.00', false);
    }

    /* ────────────────  status and queues  ──────────────── */

    public function test_the_tabs_filter_the_list(): void
    {
        $this->withDemoData();

        $draft = $this->get('/invoices?tab=draft');
        $draft->assertSee('/invoices/INV-2026-007', false);
        $draft->assertDontSee('/invoices/INV-2026-012', false);

        $overdue = $this->get('/invoices?tab=overdue');
        $overdue->assertSee('/invoices/INV-2026-012', false);
        $overdue->assertDontSee('/invoices/INV-2026-007', false);
    }

    public function test_an_invalid_tab_is_rejected(): void
    {
        $this->get('/invoices?tab=everything')->assertSessionHasErrors('tab');
    }

    public function test_the_currency_filter_works(): void
    {
        $this->withDemoData();

        $usd = $this->get('/invoices?currency=USD');
        $usd->assertSee('/invoices/INV-2026-013', false);
        $usd->assertDontSee('/invoices/INV-2026-012', false);
    }

    public function test_an_invalid_currency_is_rejected(): void
    {
        $this->get('/invoices?currency=XYZ')->assertSessionHasErrors('currency');
    }

    /* ────────────────  the rules that outlive this phase  ──────────────── */

    public function test_there_is_no_route_that_deletes_an_invoice(): void
    {
        // An invoice number must never leave the sequence. Withdrawal is a
        // cancellation that keeps the record. If this test ever fails, the
        // question to ask is not how to fix the test.
        foreach (app('router')->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'invoices')) {
                $this->assertNotContains('DELETE', $route->methods(), "a DELETE route exists at {$route->uri()}");
            }
        }

        $this->assertFalse(app('router')->has('invoices.destroy'));
    }

    public function test_the_numbering_is_gapless_and_the_cancelled_one_keeps_its_number(): void
    {
        $this->withDemoData();

        $numbers = $this->allInvoices()
            ->pluck('id')
            ->map(fn (string $id) => (int) substr($id, -3))
            ->sort()
            ->values()
            ->all();

        $this->assertSame(range(min($numbers), max($numbers)), $numbers, 'the invoice sequence has a gap');

        // And the cancelled one is still in it.
        $cancelled = $this->allInvoices()->where('status', InvoicePresenter::CANCELLED);
        $this->assertNotEmpty($cancelled, 'no cancelled invoice in the sample set to prove the point');
    }

    public function test_the_next_number_follows_the_highest_issued(): void
    {
        $this->withDemoData();

        $this->assertSame('INV-'.now()->year.'-015', InvoiceDirectory::nextNumber());
    }

    public function test_the_number_on_the_create_form_cannot_be_typed(): void
    {
        // If the form chose it, two people clicking Create at the same moment
        // would get the same number.
        $this->withDemoData();

        $html = $this->get('/invoices/create')->getContent();

        $this->assertMatchesRegularExpression('/id="inv-number"[^>]*readonly/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="inv-number"[^>]*name=/', $html);
    }

    public function test_there_is_no_mark_as_paid_control(): void
    {
        // Status is derived from the payment ledger. Marking without recording
        // would be marking without evidence.
        $this->withDemoData();

        foreach (['/invoices', '/invoices/INV-2026-012', '/invoices/INV-2026-007'] as $url) {
            $html = $this->get($url)->getContent();

            $this->assertStringNotContainsStringIgnoringCase('mark as paid', $html);
            $this->assertStringNotContainsStringIgnoringCase('mark paid', $html);
        }
    }

    public function test_cancelling_is_not_offered_once_money_has_been_received(): void
    {
        // That case needs a credit note, which is its own record.
        $this->withDemoData();

        // Part-paid: no cancel.
        $this->get('/invoices/INV-2026-010')->assertDontSee('Cancel invoice', false);
        // Untouched and unpaid: cancel is available.
        $this->get('/invoices/INV-2026-011')->assertSee('Cancel invoice', false);
    }

    public function test_a_cancelled_invoice_says_so_and_owes_nothing(): void
    {
        $this->withDemoData();

        $response = $this->get('/invoices/INV-2026-004');
        $response->assertSee('This invoice was cancelled', false);
        $response->assertSee('keeps its number', false);
        $response->assertDontSee('Record a payment', false);
    }

    public function test_a_draft_is_private_and_offers_sending_rather_than_a_reminder(): void
    {
        $this->withDemoData();

        $response = $this->get('/invoices/INV-2026-007');
        $response->assertSee('Not sent yet', false);
        $response->assertSee('Send to client', false);
        $response->assertDontSee('Send reminder', false);
        // Nothing can be received against something the client has not seen.
        $response->assertDontSee('Record a payment', false);
    }

    public function test_recording_a_payment_defaults_to_the_outstanding_balance(): void
    {
        $this->withDemoData();

        /*
         * Ungrouped in the field, grouped in the hint beside it. A prefilled
         * "95,000.00" is a value the validator that rendered it would refuse —
         * see Money::plain, which exists because of exactly this.
         */
        $response = $this->get('/invoices/INV-2026-012');

        $response->assertSee('value="95000.00"', false);
        $response->assertSee('₹95,000.00 outstanding', false);
    }

    /* ────────────────  the usual guards  ──────────────── */

    public function test_an_empty_database_produces_an_empty_module(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.debug' => false]);

        /*
         * This replaced "the demo source is inert outside local + debug", which
         * was true only because the fixture switched itself off. Invoices come
         * from a table now, and in production real ones SHOULD be shown.
         *
         * What survives is the guarantee underneath it: no figure is invented.
         */
        $this->assertTrue($this->allInvoices()->isEmpty());
        $this->assertSame([], InvoiceDirectory::recentPayments());
        $this->assertNull(InvoiceDirectory::find('INV-2026-014'));
        // And the first number of a fresh year is 001, not a guess.
        $this->assertSame('INV-'.now()->year.'-001', InvoiceDirectory::nextNumber());
    }

    public function test_no_figure_is_written_into_the_markup(): void
    {
        // The handover hardcoded 28 / 16 / 8 / 4 and ₹12,45,000, plus an
        // invented "18.6%" month-on-month delta.
        $response = $this->get('/invoices');

        $response->assertSee('Total Invoices', false);
        $response->assertDontSee('12,45,000', false);
        $response->assertDontSee('18.6%', false);
    }

    public function test_an_unknown_invoice_is_not_found(): void
    {
        $this->withDemoData();

        $this->get('/invoices/INV-9999-999')->assertNotFound();
        $this->get('/invoices/'.urlencode('<script>'))->assertNotFound();
    }

    public function test_the_write_routes_exist_so_the_forms_are_real(): void
    {
        $this->assertTrue(app('router')->has('invoices.store'));
        $this->assertTrue(app('router')->has('invoices.payments.store'));
        $this->assertTrue(app('router')->has('invoices.send'));
        $this->assertTrue(app('router')->has('invoices.cancel'));
    }

    public function test_the_pages_render_nothing_the_content_security_policy_would_block(): void
    {
        $this->withDemoData();

        foreach (['/invoices', '/invoices/create', '/invoices/INV-2026-012', '/invoices/INV-2026-004'] as $url) {
            $html = $this->get($url)->getContent();

            $this->assertSame(0, preg_match_all('/<style[\s>]/i', $html), "inline <style> in {$url}");
            $this->assertSame(0, preg_match_all('/\sstyle="/i', $html), "inline style attribute in {$url}");
            $this->assertSame(0, preg_match_all('/\son[a-z]+="/i', $html), "inline event handler in {$url}");
            $this->assertSame(0, preg_match_all('/<script(?![^>]*\ssrc=)/i', $html), "inline <script> in {$url}");
        }
    }

    public function test_the_sidebar_marks_invoices_as_current(): void
    {
        $this->withDemoData();

        foreach (['/invoices', '/invoices/create', '/invoices/INV-2026-012'] as $url) {
            $this->assertSame(
                1,
                substr_count($this->get($url)->getContent(), 'class="sb-link active"'),
                "sidebar current marker wrong on {$url}"
            );
        }
    }
}
