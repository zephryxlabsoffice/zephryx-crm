<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeSalaryStructure as Structure;

/**
 * Which pay components an engagement actually has, and what to call them.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ONE PLACE, BECAUSE THREE SURFACES ASK THE SAME QUESTION
 *
 * The form decides which boxes to draw, the validator decides which fields to
 * accept, and the record card decides which lines to print. Written out three
 * times, the day an intern stops being paid a flat stipend is the day two of
 * the three change — and the one that did not is a form that collects a figure
 * nothing ever reads.
 *
 * WHY EARNINGS AND DEDUCTIONS ARE SEPARATE LISTS
 *
 * Not for layout. PF, PT and TDS are money that does NOT reach the person, and
 * a card that ran all six down one column in the same tone would read as a
 * gross of six added figures. Two lists is the smallest thing that stops the
 * page implying arithmetic it is not doing.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class SalaryStructure
{
    /**
     * What the engagement's agreement is shaped like.
     */
    public static function kindFor(string $employmentType): string
    {
        return match ($employmentType) {
            Employee::INTERN => Structure::STIPEND,
            Employee::FREELANCE => Structure::RATE,
            default => Structure::BREAKDOWN,
        };
    }

    /**
     * Money that reaches the person, as column => label.
     *
     * @return array<string, string>
     */
    public static function earnings(string $kind): array
    {
        return match ($kind) {
            Structure::BREAKDOWN => [
                'basic_minor' => 'Basic',
                'hra_minor' => 'HRA',
                'allowances_minor' => 'Other allowances',
            ],
            Structure::STIPEND => ['stipend_minor' => 'Monthly stipend'],
            Structure::RATE => ['rate_minor' => 'Rate'],
            default => [],
        };
    }

    /**
     * Money withheld from it.
     *
     * Empty for a stipend and for a rate, which is the point: there is no PF on
     * an intern's stipend, and a freelancer invoices us rather than being paid
     * through payroll at all.
     *
     * @return array<string, string>
     */
    public static function deductions(string $kind): array
    {
        return $kind === Structure::BREAKDOWN
            ? [
                'pf_minor' => 'PF',
                'pt_minor' => 'Professional tax',
                'tds_minor' => 'TDS',
            ]
            : [];
    }

    /**
     * Every amount column this kind uses.
     *
     * @return list<string>
     */
    public static function columns(string $kind): array
    {
        return array_merge(
            array_keys(self::earnings($kind)),
            array_keys(self::deductions($kind)),
        );
    }

    public static function label(string $column): string
    {
        foreach (Structure::KINDS as $kind) {
            $labels = self::earnings($kind) + self::deductions($kind);

            if (isset($labels[$column])) {
                return $labels[$column];
            }
        }

        return $column;
    }

    /**
     * What a rate is charged against.
     *
     * @return array<string, string>
     */
    public static function basisOptions(): array
    {
        return [
            'project' => 'Per project',
            'hour' => 'Per hour',
        ];
    }

    /**
     * The card's rows, ready to print — or null when there is nothing on file.
     *
     * NO TOTAL IS RETURNED, and that is deliberate. A "gross" here would be
     * this application's own arithmetic sitting next to a payslip produced by
     * somebody else's, and they will differ in any month carrying a deduction,
     * an arrear or a day of loss of pay. The payslip is what the person was
     * paid; this is what was agreed. The screen states both and computes
     * neither.
     *
     * @return array{kind: string, earnings: list<array{label: string, amount: string}>,
     *               deductions: list<array{label: string, amount: string}>,
     *               basis: ?string, currency: string}|null
     */
    public static function card(?Structure $structure): ?array
    {
        if ($structure === null) {
            return null;
        }

        $currency = $structure->currency ?: Money::DEFAULT_CURRENCY;

        $rows = function (array $labels) use ($structure, $currency): array {
            $out = [];

            foreach ($labels as $column => $label) {
                $minor = $structure->{$column};

                // A null component is one nobody has stated; a zero is a
                // statement that it is nil. Both are printed, differently.
                $out[] = [
                    'label' => $label,
                    'amount' => $minor === null
                        ? 'Not stated'
                        : Money::of((int) $minor, $currency)->format(),
                ];
            }

            return $out;
        };

        return [
            'kind' => $structure->kind,
            'earnings' => $rows(self::earnings($structure->kind)),
            'deductions' => $rows(self::deductions($structure->kind)),
            'basis' => $structure->rate_basis
                ? (self::basisOptions()[$structure->rate_basis] ?? $structure->rate_basis)
                : null,
            'currency' => $currency,
        ];
    }
}
