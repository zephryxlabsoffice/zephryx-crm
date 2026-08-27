<?php

namespace Tests\Unit;

use App\Support\InvoicePresenter as P;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InvoicePresenterTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function invoice(array $overrides = []): array
    {
        return array_merge([
            'total' => Money::of(100000),
            'paid' => Money::zero(),
            'balance' => Money::of(100000),
            'invoice_date' => Carbon::today()->subDays(10)->toDateString(),
            'due_date' => Carbon::today()->addDays(20)->toDateString(),
            'issued' => true,
            'cancelled' => false,
        ], $overrides);
    }

    /* ────────────────  status is derived, not stored  ──────────────── */

    public function test_an_unpaid_sent_invoice_is_awaiting_payment(): void
    {
        $this->assertSame(P::SENT, P::statusOf($this->invoice()));
    }

    public function test_an_invoice_that_has_not_been_sent_is_a_draft(): void
    {
        $this->assertSame(P::DRAFT, P::statusOf($this->invoice(['issued' => false])));
    }

    public function test_a_part_paid_invoice_within_its_terms_is_partly_paid(): void
    {
        $this->assertSame(P::PARTIAL, P::statusOf($this->invoice([
            'paid' => Money::of(40000),
            'balance' => Money::of(60000),
        ])));
    }

    public function test_a_fully_paid_invoice_is_paid(): void
    {
        $this->assertSame(P::PAID, P::statusOf($this->invoice([
            'paid' => Money::of(100000),
            'balance' => Money::zero(),
        ])));
    }

    public function test_an_overpayment_still_reads_as_paid(): void
    {
        $this->assertSame(P::PAID, P::statusOf($this->invoice([
            'paid' => Money::of(120000),
            'balance' => Money::of(-20000),
        ])));
    }

    public function test_paid_outranks_overdue(): void
    {
        // Money that arrived late is history, not an outstanding debt — the
        // same rule Projects and Tasks use for late-but-finished work.
        $this->assertSame(P::PAID, P::statusOf($this->invoice([
            'paid' => Money::of(100000),
            'balance' => Money::zero(),
            'due_date' => Carbon::today()->subDays(30)->toDateString(),
        ])));
    }

    public function test_overdue_outranks_partly_paid(): void
    {
        // A half-paid invoice three weeks late is a collection problem, and
        // calling it "Partly paid" buries that.
        $this->assertSame(P::OVERDUE, P::statusOf($this->invoice([
            'paid' => Money::of(40000),
            'balance' => Money::of(60000),
            'due_date' => Carbon::today()->subDays(21)->toDateString(),
        ])));
    }

    public function test_cancelled_outranks_everything(): void
    {
        // A cancelled invoice past its due date is not overdue; nobody owes it.
        $this->assertSame(P::CANCELLED, P::statusOf($this->invoice([
            'cancelled' => true,
            'due_date' => Carbon::today()->subDays(60)->toDateString(),
        ])));
    }

    public function test_a_draft_is_never_overdue(): void
    {
        // The client has not seen it, so nothing can be late.
        $this->assertSame(P::DRAFT, P::statusOf($this->invoice([
            'issued' => false,
            'due_date' => Carbon::today()->subDays(30)->toDateString(),
        ])));
    }

    public function test_an_invoice_due_today_is_not_yet_overdue(): void
    {
        // Due-date arithmetic run to the end of the day, not the start — an
        // invoice due today is not late at 9am.
        $this->assertSame(P::SENT, P::statusOf($this->invoice([
            'due_date' => Carbon::today()->toDateString(),
        ])));
    }

    public function test_a_zero_total_invoice_is_not_silently_paid(): void
    {
        // 0 >= 0 is true, which would mark an empty invoice settled. It has no
        // lines yet, so "Sent" is the honest answer.
        $this->assertSame(P::SENT, P::statusOf($this->invoice([
            'total' => Money::zero(),
            'paid' => Money::zero(),
            'balance' => Money::zero(),
        ])));
    }

    /* ────────────────  outstanding  ──────────────── */

    public function test_only_unsettled_sent_invoices_count_as_outstanding(): void
    {
        $this->assertTrue(P::isOutstanding($this->invoice()));
        $this->assertTrue(P::isOutstanding($this->invoice(['paid' => Money::of(40000)])));
        $this->assertFalse(P::isOutstanding($this->invoice(['paid' => Money::of(100000)])));
        $this->assertFalse(P::isOutstanding($this->invoice(['cancelled' => true])));
        // A draft is not money anyone owes us.
        $this->assertFalse(P::isOutstanding($this->invoice(['issued' => false])));
    }

    /* ────────────────  due dates  ──────────────── */

    public function test_a_settled_invoice_is_never_coloured_as_a_problem(): void
    {
        // Colouring every past date red — which the handover did — means the
        // colour stops meaning anything.
        $due = P::dueState($this->invoice([
            'paid' => Money::of(100000),
            'due_date' => Carbon::today()->subDays(40)->toDateString(),
        ]));

        $this->assertSame('Settled', $due['label']);
        $this->assertNotSame('is-overdue', $due['tone']);
    }

    public function test_a_distant_due_date_gets_no_colour(): void
    {
        $due = P::dueState($this->invoice(['due_date' => Carbon::today()->addDays(20)->toDateString()]));

        $this->assertSame('Due in 20 days', $due['label']);
        $this->assertSame('', $due['tone']);
    }

    public function test_a_due_date_within_a_week_is_worth_noticing(): void
    {
        $this->assertSame('is-soon', P::dueState($this->invoice([
            'due_date' => Carbon::today()->addDays(5)->toDateString(),
        ]))['tone']);

        $this->assertSame('Due tomorrow', P::dueState($this->invoice([
            'due_date' => Carbon::today()->addDay()->toDateString(),
        ]))['label']);

        $this->assertSame('Due today', P::dueState($this->invoice([
            'due_date' => Carbon::today()->toDateString(),
        ]))['label']);
    }

    public function test_lateness_is_counted_in_days_and_reads_in_the_singular(): void
    {
        $this->assertSame('1 day overdue', P::dueState($this->invoice([
            'due_date' => Carbon::today()->subDay()->toDateString(),
        ]))['label']);

        $this->assertSame('18 days overdue', P::dueState($this->invoice([
            'due_date' => Carbon::today()->subDays(18)->toDateString(),
        ]))['label']);
    }

    /* ────────────────  terms  ──────────────── */

    public function test_terms_are_read_from_the_dates_so_they_cannot_contradict_them(): void
    {
        $this->assertSame('Net 30', P::terms([
            'invoice_date' => '2026-08-01',
            'due_date' => '2026-08-31',
        ]));

        $this->assertSame('Net 45', P::terms([
            'invoice_date' => '2026-08-01',
            'due_date' => '2026-09-15',
        ]));

        $this->assertSame('Due on receipt', P::terms([
            'invoice_date' => '2026-08-01',
            'due_date' => '2026-08-01',
        ]));
    }

    /* ────────────────  vocabulary  ──────────────── */

    public function test_every_status_has_words_a_tone_and_a_meaning(): void
    {
        foreach (P::statusOptions() as $status) {
            $rendered = P::status($status);

            $this->assertNotSame('', $rendered['label']);
            $this->assertStringStartsWith('pill-', $rendered['tone']);
            $this->assertNotSame('', $rendered['meaning'], "{$status} has no plain-English meaning");
        }
    }

    public function test_pending_and_overdue_do_not_share_a_colour(): void
    {
        // The handover coloured both red on the same screen, which makes the
        // distinction invisible exactly where it matters.
        $this->assertNotSame(P::status(P::SENT)['tone'], P::status(P::OVERDUE)['tone']);
        $this->assertNotSame(P::status(P::PARTIAL)['tone'], P::status(P::OVERDUE)['tone']);
    }

    public function test_an_unknown_status_is_shown_rather_than_swallowed(): void
    {
        $this->assertSame('Written off', P::status('written_off')['label']);
    }
}
