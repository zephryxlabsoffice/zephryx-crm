<?php

namespace Tests\Unit;

use App\Support\Money;
use App\Support\MoneyBag;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The arithmetic under every rupee figure in the application.
 *
 * This is the first module with real logic to test rather than markup to
 * assert, and it is the one where being wrong costs actual money.
 */
class MoneyTest extends TestCase
{
    public function test_an_amount_is_stored_as_an_integer_count_of_minor_units(): void
    {
        $amount = Money::of(7500000, 'INR');

        $this->assertSame(7500000, $amount->minor);
        $this->assertIsInt($amount->minor);
        $this->assertSame('₹75,000.00', $amount->format());
    }

    public function test_repeated_addition_does_not_drift(): void
    {
        // The whole reason money is not a float. Ten cents added ten times is
        // exactly one dollar here; as doubles, 0.1 * 10 !== 1.0.
        $total = Money::zero('USD');

        for ($i = 0; $i < 10; $i++) {
            $total = $total->plus(Money::of(10, 'USD'));
        }

        $this->assertSame(100, $total->minor);
        $this->assertTrue($total->equals(Money::fromMajor(1, 'USD')));
    }

    public function test_a_thousand_awkward_lines_still_reconcile(): void
    {
        $total = Money::zero('INR');

        for ($i = 0; $i < 1000; $i++) {
            $total = $total->plus(Money::of(3333, 'INR')); // ₹33.33
        }

        $this->assertSame(3333000, $total->minor);
        $this->assertSame('₹33,330.00', $total->format());
    }

    public function test_currencies_cannot_be_combined(): void
    {
        // Deliberately fatal. A silent coercion produces a total that looks
        // authoritative and means nothing.
        $this->expectException(InvalidArgumentException::class);

        Money::of(7500000, 'INR')->plus(Money::of(200000, 'USD'));
    }

    public function test_an_unsupported_currency_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of(100, 'XYZ');
    }

    /* ─────────────────────────  formatting  ───────────────────────── */

    public function test_rupees_use_indian_digit_grouping(): void
    {
        // 1,20,000 — not 120,000. Western grouping on an invoice sent to an
        // Indian client reads as a mistake, because it is one.
        $this->assertSame('₹1,20,000.00', Money::of(12000000)->format());
        $this->assertSame('₹1,00,00,000.00', Money::of(1000000000)->format());
        $this->assertSame('₹999.00', Money::of(99900)->format());
        $this->assertSame('₹1,000.00', Money::of(100000)->format());
        $this->assertSame('₹12,45,000.00', Money::of(124500000)->format());
    }

    public function test_other_currencies_group_in_threes(): void
    {
        $this->assertSame('$120,000.00', Money::of(12000000, 'USD')->format());
        $this->assertSame('£1,500.50', Money::of(150050, 'GBP')->format());
    }

    public function test_decimals_are_never_dropped_from_an_exact_amount(): void
    {
        // A figure that sometimes shows paise and sometimes does not leaves the
        // reader unsure whether ₹75,000 is exact or rounded.
        $this->assertSame('₹75,000.00', Money::of(7500000)->format());
        $this->assertSame('₹75,000.50', Money::of(7500050)->format());
    }

    public function test_the_short_form_drops_only_zero_decimals(): void
    {
        $this->assertSame('₹75,000', Money::of(7500000)->short());
        // Anything with paise in it keeps them, rather than the system quietly
        // rounding an amount somebody has to pay.
        $this->assertSame('₹75,000.50', Money::of(7500050)->short());
    }

    public function test_a_negative_amount_puts_the_sign_outside_the_symbol(): void
    {
        $this->assertSame('-₹1,000.00', Money::of(-100000)->format());
        $this->assertSame('-$50.25', Money::of(-5025, 'USD')->format());
    }

    public function test_a_major_unit_figure_rounds_half_up_at_the_minor_unit(): void
    {
        // Truncating would quietly take somebody's money away.
        $this->assertSame(1235, Money::fromMajor('12.345', 'USD')->minor);
        $this->assertSame(1000, Money::fromMajor(10, 'USD')->minor);
        $this->assertSame(1050, Money::fromMajor(10.5, 'USD')->minor);
    }

    /* ─────────────────────────  comparisons  ───────────────────────── */

    public function test_a_share_is_clamped_rather_than_reporting_an_overpayment_as_140_percent(): void
    {
        $total = Money::of(100000);

        $this->assertSame(50.0, Money::of(50000)->percentageOf($total));
        $this->assertSame(100.0, Money::of(100000)->percentageOf($total));
        $this->assertSame(100.0, Money::of(140000)->percentageOf($total));
        $this->assertSame(0.0, Money::of(50000)->percentageOf(Money::zero()));
    }

    public function test_greater_than_or_equal_is_what_decides_paid(): void
    {
        $total = Money::of(100000);

        $this->assertTrue(Money::of(100000)->greaterThanOrEqual($total));
        $this->assertTrue(Money::of(100001)->greaterThanOrEqual($total));
        $this->assertFalse(Money::of(99999)->greaterThanOrEqual($total));
    }

    /* ─────────────────────────  the bag  ───────────────────────── */

    public function test_a_mixed_bag_keeps_one_subtotal_per_currency(): void
    {
        $bag = MoneyBag::of([
            Money::of(7500000, 'INR'),
            Money::of(200000, 'USD'),
            Money::of(2500000, 'INR'),
        ]);

        $this->assertFalse($bag->isSingleCurrency());
        $this->assertSame(2, $bag->currencyCount());
        $this->assertSame(10000000, $bag->in('INR')->minor);
        $this->assertSame(200000, $bag->in('USD')->minor);
    }

    public function test_a_bag_never_invents_an_exchange_rate(): void
    {
        // There is deliberately no ->total(). If one is ever added, this test
        // should be the thing that makes somebody stop and think about where
        // the rate came from.
        $this->assertFalse(method_exists(MoneyBag::class, 'total'));
        $this->assertFalse(method_exists(MoneyBag::class, 'convertTo'));
    }

    public function test_a_bag_reads_as_two_honest_lines(): void
    {
        $bag = MoneyBag::of([Money::of(40500000, 'INR'), Money::of(200000, 'USD')]);

        $this->assertSame('₹4,05,000 and $2,000', $bag->format());
    }

    public function test_a_headline_names_the_other_currency_rather_than_counting_it(): void
    {
        // "plus $2,000" is actionable; "plus 1 other currency" sends the reader
        // looking for a figure the tile is withholding.
        $headline = MoneyBag::of([Money::of(40500000, 'INR'), Money::of(200000, 'USD')])->headline();

        $this->assertSame('₹4,05,000', $headline['lead']);
        $this->assertSame('plus $2,000', $headline['note']);
    }

    public function test_a_single_currency_headline_carries_no_note(): void
    {
        $headline = MoneyBag::of([Money::of(40500000, 'INR')])->headline();

        $this->assertSame('₹4,05,000', $headline['lead']);
        $this->assertSame('', $headline['note']);
    }

    public function test_the_dominant_currency_leads(): void
    {
        $bag = MoneyBag::of([Money::of(200000, 'USD'), Money::of(40500000, 'INR')]);

        $this->assertSame('₹4,05,000', $bag->headline()['lead']);
    }

    public function test_an_empty_bag_reads_as_zero_not_as_a_blank(): void
    {
        $this->assertSame('₹0', MoneyBag::of()->format());
        $this->assertTrue(MoneyBag::of()->isEmpty());
    }
}
