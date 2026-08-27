<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Turns a salary run into the things its pages need to draw it.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * NET IS COMPUTED, NEVER STORED
 *
 * A payslip is an arithmetic claim: these earnings, less these deductions,
 * equals this amount, and it was paid on this date. If net were a column, it
 * could disagree with the lines above it — and the person it disagrees with is
 * an employee looking at their own pay, which is the worst possible audience
 * for a number that does not add up.
 *
 * So net = sum(earnings) − sum(deductions), computed on every render, and a
 * test asserts it reconciles for every run in the system.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * DEDUCTIONS TODAY: NONE
 *
 * Decided 2026-08-27. ZephryxLabs runs no statutory deduction at present — EPF
 * is not mandatory below twenty employees, ESI below ten, and no professional
 * tax or TDS is being withheld. So gross equals net.
 *
 * The deductions section still exists and reads "None", rather than the concept
 * being absent. Two reasons: an employee should be able to see that nothing was
 * withheld, which is different from not being told; and loss-of-pay is a
 * deduction that lands the moment Attendance and Leave exist.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class SalaryPresenter
{
    public const NOT_GENERATED = 'not_generated';
    public const PENDING = 'pending';
    public const ON_HOLD = 'on_hold';
    public const PAID = 'paid';

    /** @var array<string, array{0: string, 1: string, 2: string}> tone, label, meaning */
    protected const STATUSES = [
        self::NOT_GENERATED => ['pill-gray', 'Not generated', 'No run exists for this month yet'],
        self::PENDING => ['pill-amber', 'Pending', 'Generated but not yet paid'],
        self::ON_HOLD => ['pill-red', 'On hold', 'Held back deliberately — someone decided this'],
        self::PAID => ['pill-green', 'Paid', 'Sent to the employee’s bank'],
    ];

    /**
     * @return array{tone: string, label: string, meaning: string}
     */
    public static function status(string $status): array
    {
        [$tone, $label, $meaning] = self::STATUSES[$status]
            ?? ['pill-gray', ucfirst(str_replace('_', ' ', $status)), ''];

        return ['tone' => $tone, 'label' => $label, 'meaning' => $meaning];
    }

    /**
     * @return list<string>
     */
    public static function statusOptions(): array
    {
        return array_keys(self::STATUSES);
    }

    /**
     * The net of a run: earnings less deductions.
     *
     * @param  array<string, mixed>  $run
     */
    public static function net(array $run): Money
    {
        return self::totalEarnings($run)->minus(self::totalDeductions($run));
    }

    /**
     * @param  array<string, mixed>  $run
     */
    public static function totalEarnings(array $run): Money
    {
        return array_reduce(
            $run['earnings'],
            fn (Money $carry, array $line) => $carry->plus($line['amount']),
            Money::zero($run['currency'] ?? Money::DEFAULT_CURRENCY)
        );
    }

    /**
     * @param  array<string, mixed>  $run
     */
    public static function totalDeductions(array $run): Money
    {
        return array_reduce(
            $run['deductions'],
            fn (Money $carry, array $line) => $carry->plus($line['amount']),
            Money::zero($run['currency'] ?? Money::DEFAULT_CURRENCY)
        );
    }

    /**
     * Cost to company, annualised from the monthly gross.
     *
     * Derived, not stored, so it cannot drift from the structure beneath it —
     * the handover showed a CTC of ₹12,60,000 (₹1,05,000 a month) beside a net
     * of ₹85,800 and a table row reading ₹80,000, three figures for one person
     * with nothing connecting them.
     *
     * @param  array<string, mixed>  $run
     */
    public static function annualCtc(array $run): Money
    {
        return self::totalEarnings($run)->times(12);
    }

    /**
     * `May 2026` — the month a run belongs to.
     */
    public static function period(string $period): string
    {
        return Carbon::createFromFormat('Y-m', $period)->format('F Y');
    }

    /**
     * `May 2026` shortened for a table cell.
     */
    public static function periodShort(string $period): string
    {
        return Carbon::createFromFormat('Y-m', $period)->format('M Y');
    }

    /**
     * The months a period picker offers, most recent first.
     *
     * @return array<string, string>
     */
    public static function periodOptions(int $months = 12): array
    {
        $options = [];
        $cursor = Carbon::today()->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $key = $cursor->format('Y-m');
            $options[$key] = $cursor->format('F Y');
            $cursor = $cursor->subMonth();
        }

        return $options;
    }

    public static function date(Carbon|string|null $when): string
    {
        return $when === null ? '—' : Carbon::parse($when)->format('d M Y');
    }

    /**
     * A payment date only means something once the money has gone.
     *
     * The handover printed "--" for unpaid rows, which reads as missing data
     * rather than as a thing that has not happened yet.
     *
     * @param  array<string, mixed>  $run
     */
    public static function paidOn(array $run): string
    {
        return $run['status'] === self::PAID && $run['paid_on'] !== null
            ? self::date($run['paid_on'])
            : 'Not paid yet';
    }
}
