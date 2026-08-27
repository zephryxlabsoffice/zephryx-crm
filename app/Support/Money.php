<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * An amount of money: an integer number of minor units, plus a currency.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHY THIS IS NOT A FLOAT
 *
 * `0.1 + 0.2 === 0.30000000000000004` in PHP, as in every language using IEEE
 * 754 doubles. Sum a few hundred invoice lines that way and the total is off by
 * a paisa; reconcile that against a bank statement and somebody spends an
 * afternoon looking for it. Money is stored and passed around as an integer
 * count of the smallest unit — paise for INR, cents for USD — and only ever
 * becomes a decimal at the moment it is printed.
 *
 * When the database lands, the column is a BIGINT of minor units beside a
 * three-letter currency column. Never DECIMAL, never FLOAT.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Two amounts in different currencies cannot be added. That is not a limitation
 * to work around — it is the point. See MoneyBag for totalling a mixed set.
 */
final class Money
{
    /**
     * The currencies we can invoice in.
     *
     * `grouping` matters: Indian digit grouping is 1,20,000 (last three, then
     * pairs), not 120,000. Printing a rupee amount in Western grouping on an
     * invoice sent to an Indian client looks like a mistake, because it is one.
     *
     * @var array<string, array{symbol: string, name: string, grouping: string, minor: int}>
     */
    public const CURRENCIES = [
        'INR' => ['symbol' => '₹', 'name' => 'Indian Rupee',   'grouping' => 'indian',  'minor' => 2],
        'USD' => ['symbol' => '$', 'name' => 'US Dollar',      'grouping' => 'western', 'minor' => 2],
        'EUR' => ['symbol' => '€', 'name' => 'Euro',           'grouping' => 'western', 'minor' => 2],
        'GBP' => ['symbol' => '£', 'name' => 'Pound Sterling', 'grouping' => 'western', 'minor' => 2],
        'AED' => ['symbol' => 'AED ', 'name' => 'UAE Dirham',  'grouping' => 'western', 'minor' => 2],
        'SGD' => ['symbol' => 'S$', 'name' => 'Singapore Dollar', 'grouping' => 'western', 'minor' => 2],
        'AUD' => ['symbol' => 'A$', 'name' => 'Australian Dollar', 'grouping' => 'western', 'minor' => 2],
    ];

    public const DEFAULT_CURRENCY = 'INR';

    private function __construct(
        public readonly int $minor,
        public readonly string $currency,
    ) {}

    /**
     * @param  int  $minor  paise, cents — never rupees or dollars
     */
    public static function of(int $minor, string $currency = self::DEFAULT_CURRENCY): self
    {
        $currency = strtoupper($currency);

        if (! isset(self::CURRENCIES[$currency])) {
            throw new InvalidArgumentException("Unsupported currency '{$currency}'.");
        }

        return new self($minor, $currency);
    }

    public static function zero(string $currency = self::DEFAULT_CURRENCY): self
    {
        return self::of(0, $currency);
    }

    /**
     * Build from a major-unit figure — a form field, a spreadsheet import.
     *
     * Rounds half up at the minor unit, because the alternative is truncating
     * somebody's money away. This is the only place a float is allowed near an
     * amount, and it stops here.
     */
    public static function fromMajor(int|float|string $major, string $currency = self::DEFAULT_CURRENCY): self
    {
        $currency = strtoupper($currency);
        $scale = 10 ** (self::CURRENCIES[$currency]['minor'] ?? 2);

        return self::of((int) round(((float) $major) * $scale), $currency);
    }

    /* ─────────────────────────────  arithmetic  ───────────────────────────── */

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    public function times(int $quantity): self
    {
        return new self($this->minor * $quantity, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor && $this->currency === $other->currency;
    }

    public function greaterThanOrEqual(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor >= $other->minor;
    }

    /**
     * What share of another amount this is, 0–100. Used for progress bars, so
     * it clamps rather than reporting 140% when somebody overpays.
     */
    public function percentageOf(self $total): float
    {
        $this->assertSameCurrency($total);

        if ($total->minor === 0) {
            return 0.0;
        }

        return max(0.0, min(100.0, round($this->minor / $total->minor * 100, 1)));
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            // Deliberately fatal. Silently coercing would produce a total that
            // looks right and means nothing.
            throw new InvalidArgumentException(
                "Cannot combine {$this->currency} with {$other->currency}. ".
                'Amounts in different currencies have no common total — use MoneyBag.'
            );
        }
    }

    /* ─────────────────────────────  printing  ───────────────────────────── */

    public function symbol(): string
    {
        return self::CURRENCIES[$this->currency]['symbol'];
    }

    /**
     * The full amount, always with its minor units: `₹1,20,000.00`.
     *
     * Decimals are never dropped, even when they are zero. A figure that
     * sometimes shows paise and sometimes does not leaves the reader unsure
     * whether ₹75,000 is exact or rounded, and on an invoice that matters.
     */
    public function format(): string
    {
        // The sign belongs outside the symbol: -₹1,000.00, not ₹-1,000.00.
        return $this->minor < 0
            ? '-'.$this->symbol().substr($this->decimal(), 1)
            : $this->symbol().$this->decimal();
    }

    /**
     * The number alone, grouped and with decimals, no symbol. For a column
     * that states its currency in the header.
     */
    public function decimal(): string
    {
        $places = self::CURRENCIES[$this->currency]['minor'];
        $scale = 10 ** $places;

        $sign = $this->minor < 0 ? '-' : '';
        $abs = abs($this->minor);

        $units = intdiv($abs, $scale);
        $fraction = str_pad((string) ($abs % $scale), $places, '0', STR_PAD_LEFT);

        return $sign.$this->group($units).($places > 0 ? '.'.$fraction : '');
    }

    /**
     * A short form for tight spaces — no decimals when they are zero.
     * Only for aggregates that are already approximate in the reader's mind
     * (a KPI tile), never for an amount somebody has to pay.
     */
    public function short(): string
    {
        $scale = 10 ** self::CURRENCIES[$this->currency]['minor'];

        // Anything with paise in it keeps them. Rounding here would be the
        // system quietly lying about an amount.
        if ($this->minor % $scale !== 0) {
            return $this->format();
        }

        $sign = $this->minor < 0 ? '-' : '';

        return $sign.$this->symbol().$this->group(intdiv(abs($this->minor), $scale));
    }

    /**
     * Digit grouping. Indian numbering groups the last three digits, then in
     * pairs: 1,20,000 and 1,00,00,000. Everything else groups in threes.
     */
    private function group(int $units): string
    {
        $digits = (string) $units;

        if (self::CURRENCIES[$this->currency]['grouping'] !== 'indian' || strlen($digits) <= 3) {
            return number_format($units);
        }

        $last = substr($digits, -3);
        $rest = substr($digits, 0, -3);

        // Pairs, right to left.
        $rest = strrev(implode(',', str_split(strrev($rest), 2)));

        return $rest.','.$last;
    }

    public function __toString(): string
    {
        return $this->format();
    }

    /**
     * The options a currency picker needs.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::CURRENCIES as $code => $meta) {
            $options[$code] = $code.' — '.$meta['name'];
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::CURRENCIES);
    }
}
