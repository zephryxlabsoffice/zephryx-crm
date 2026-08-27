<?php

namespace App\Support;

/**
 * A total over a mixed-currency set: one subtotal per currency, never one
 * number.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHY THERE IS NO SINGLE TOTAL
 *
 * ₹75,000 + $2,000 is not an amount. Producing one requires an exchange rate,
 * and this system has no rate source — no feed, no stored rate, nobody entering
 * one. Inventing a conversion would put a figure on a KPI tile that looks
 * authoritative, is wrong by however much the rate has moved, and that somebody
 * will eventually quote in a meeting.
 *
 * So a mixed set totals to "₹4,05,000 and $2,000". Two honest lines beat one
 * confident fiction. If real-time conversion is wanted later, the rate has to
 * be recorded on each invoice at its issue date, and that is a data model
 * decision, not a display one.
 * ─────────────────────────────────────────────────────────────────────────────
 */
final class MoneyBag
{
    /** @var array<string, Money> keyed by currency code */
    private array $totals = [];

    /**
     * @param  iterable<Money>  $amounts
     */
    public static function of(iterable $amounts = []): self
    {
        $bag = new self;

        foreach ($amounts as $amount) {
            $bag->add($amount);
        }

        return $bag;
    }

    public function add(Money $amount): self
    {
        $this->totals[$amount->currency] = isset($this->totals[$amount->currency])
            ? $this->totals[$amount->currency]->plus($amount)
            : $amount;

        return $this;
    }

    public function isEmpty(): bool
    {
        return $this->totals === [];
    }

    /**
     * True when everything in the bag is one currency — the common case, and
     * the one where a view can show a single figure without qualification.
     */
    public function isSingleCurrency(): bool
    {
        return count($this->totals) === 1;
    }

    public function currencyCount(): int
    {
        return count($this->totals);
    }

    /**
     * The subtotals, largest first so the dominant currency leads.
     *
     * @return list<Money>
     */
    public function subtotals(): array
    {
        $totals = $this->totals;

        uasort($totals, fn (Money $a, Money $b) => $b->minor <=> $a->minor);

        return array_values($totals);
    }

    public function in(string $currency): Money
    {
        return $this->totals[strtoupper($currency)] ?? Money::zero($currency);
    }

    /**
     * The whole bag as one string: `₹4,05,000 and $2,000`.
     *
     * Deliberately not a number. A caller that wants to lay the subtotals out
     * itself should use subtotals() — this is for the single-line case.
     */
    public function format(): string
    {
        if ($this->isEmpty()) {
            return Money::zero()->short();
        }

        $parts = array_map(fn (Money $m) => $m->short(), $this->subtotals());

        if (count($parts) === 1) {
            return $parts[0];
        }

        $last = array_pop($parts);

        return implode(', ', $parts).' and '.$last;
    }

    /**
     * A short form for a KPI tile, where two full amounts will not fit:
     * the dominant currency, plus how many others there are.
     *
     * @return array{lead: string, note: string}
     */
    public function headline(): array
    {
        if ($this->isEmpty()) {
            return ['lead' => Money::zero()->short(), 'note' => ''];
        }

        $subtotals = $this->subtotals();
        $lead = array_shift($subtotals);

        if ($subtotals === []) {
            return ['lead' => $lead->short(), 'note' => ''];
        }

        // Naming the other currencies matters more than counting them: "plus
        // $2,000" is actionable, "plus 1 other currency" sends the reader
        // looking for a figure the tile is withholding.
        return [
            'lead' => $lead->short(),
            'note' => 'plus '.implode(' and ', array_map(fn (Money $m) => $m->short(), $subtotals)),
        ];
    }
}
