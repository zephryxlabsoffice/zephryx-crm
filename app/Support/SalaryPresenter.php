<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Turns a salary record into the things its pages need to draw it.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THIS APPLICATION DOES NOT CALCULATE PAYROLL
 *
 * Decided 2026-08-27. Payroll is worked out in Excel today and will come from
 * payroll software over an API later. The CRM's job is to hold the payslip that
 * calculation produced, record the net figure it states, and track whether the
 * money has gone.
 *
 * So there is no basic/HRA split here, no deductions engine and no derived CTC.
 * Holding our own version of a calculation somebody else owns would give the
 * company two sources of truth for what it pays people, and they would
 * eventually disagree — in front of the employee.
 *
 * The net amount IS stored, typed once when the payslip is added, because a
 * payroll page that cannot say what was paid out this month is not much of a
 * payroll page. It is one number, taken from the document beside it.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class SalaryPresenter
{
    /** Nothing has been added for this person this month. */
    public const NO_PAYSLIP = 'no_payslip';

    /** The payslip is on file; the money has not gone yet. */
    public const AWAITING_PAYMENT = 'awaiting_payment';

    /** Paid. */
    public const PAID = 'paid';

    /** @var array<string, array{0: string, 1: string, 2: string}> tone, label, meaning */
    protected const STATUSES = [
        self::NO_PAYSLIP => ['pill-gray', 'No payslip', 'Nothing has been added for this month'],
        self::AWAITING_PAYMENT => ['pill-amber', 'Awaiting payment', 'Payslip is on file; the transfer has not been made'],
        self::PAID => ['pill-green', 'Paid', 'The transfer has been made'],
    ];

    /**
     * Where a record stands.
     *
     * Derived from two facts — is there a payslip, and has it been paid — so
     * there is no status column anybody can set to something the record does
     * not support. Nobody marks a person paid who has no payslip on file.
     *
     * @param  array<string, mixed>  $record
     */
    public static function statusOf(array $record): string
    {
        if ($record['payslip'] === null) {
            return self::NO_PAYSLIP;
        }

        return $record['paid_on'] === null ? self::AWAITING_PAYMENT : self::PAID;
    }

    /**
     * Whether this record is ready to be paid.
     *
     * The guard behind both the single and the bulk "payment done" action: you
     * cannot pay against a payslip that does not exist, and you cannot pay
     * twice.
     *
     * @param  array<string, mixed>  $record
     */
    public static function isPayable(array $record): bool
    {
        return self::statusOf($record) === self::AWAITING_PAYMENT;
    }

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
     * `May 2026` — the month a record belongs to.
     */
    public static function period(string $period): string
    {
        return Carbon::createFromFormat('Y-m', $period)->format('F Y');
    }

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
            $options[$cursor->format('Y-m')] = $cursor->format('F Y');
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
     * @param  array<string, mixed>  $record
     */
    public static function paidOn(array $record): string
    {
        return $record['paid_on'] === null ? 'Not paid yet' : self::date($record['paid_on']);
    }

    /**
     * What the net figure reads as before a payslip has been added.
     *
     * Deliberately not "₹0.00" — nothing has been recorded, and a zero is a
     * claim that somebody was paid nothing.
     *
     * @param  array<string, mixed>  $record
     */
    public static function net(array $record): string
    {
        return $record['net'] === null ? 'Not recorded' : $record['net']->format();
    }
}
