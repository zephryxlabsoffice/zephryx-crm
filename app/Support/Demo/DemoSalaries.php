<?php

namespace App\Support\Demo;

use App\Support\Money;
use App\Support\MoneyBag;
use App\Support\SalaryPresenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sample salary records, for reviewing the Salary pages before the database
 * exists. Local + debug only, like the other demo sources.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHAT A SALARY RECORD IS
 *
 * One per person per month, holding three things and no more:
 *
 *   payslip  the document produced by whatever worked the pay out — Excel
 *            today, payroll software over an API later. Uploaded, not generated.
 *   net      the figure that document states, typed once when it is added, so
 *            the payroll page can answer "what did we pay out this month".
 *   paid_on  when the transfer was made, or null.
 *
 * There is deliberately no salary structure, no earnings breakdown and no
 * deductions engine. This application does not calculate payroll — see
 * App\Support\SalaryPresenter for why holding a second version of somebody
 * else's calculation is a liability rather than a feature.
 *
 * The banking identifiers below exist so the masking has something to mask.
 * Nothing hands them to a view unmasked; see App\Support\Sensitive, and note
 * that in production these columns are encrypted at rest.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class DemoSalaries
{
    /** The person the "my" pages stand in for until authentication lands. */
    public const VIEWER = 'EMP002';

    public static function enabled(): bool
    {
        return app()->environment('local') && config('app.debug');
    }

    /**
     * Banking details per employee — where their pay goes.
     *
     * Separate from the monthly records on purpose: this is a standing fact
     * about a person, and it is the only thing about pay that this application
     * is the source of truth for.
     *
     * @return array<string, array<string, string>>
     */
    protected static function banking(): array
    {
        return [
            'EMP001' => ['bank' => 'HDFC Bank',  'ifsc' => 'HDFC0001234', 'account' => '50100234567890', 'pan' => 'ABCDE1234F', 'id_proof_number' => '123456781234'],
            'EMP002' => ['bank' => 'ICICI Bank', 'ifsc' => 'ICIC0000456', 'account' => '004501556789',   'pan' => 'BCDEF2345G', 'id_proof_number' => '234567892345'],
            'EMP003' => ['bank' => 'Axis Bank',  'ifsc' => 'UTIB0000789', 'account' => '917010045678',   'pan' => 'CDEFG3456H', 'id_proof_number' => '345678903456'],
            'EMP004' => ['bank' => 'HDFC Bank',  'ifsc' => 'HDFC0000987', 'account' => '50100987654321', 'pan' => 'DEFGH4567I', 'id_proof_number' => '456789014567'],
            'EMP005' => ['bank' => 'SBI',        'ifsc' => 'SBIN0011223', 'account' => '38291045612',    'pan' => 'EFGHI5678J', 'id_proof_number' => '567890125678'],
            'EMP006' => ['bank' => 'ICICI Bank', 'ifsc' => 'ICIC0000456', 'account' => '004501778899',   'pan' => 'FGHIJ6789K', 'id_proof_number' => '678901236789'],
            'EMP007' => ['bank' => 'Axis Bank',  'ifsc' => 'UTIB0000789', 'account' => '917010112233',   'pan' => 'GHIJK7890L', 'id_proof_number' => '789012347890'],
            'EMP008' => ['bank' => 'SBI',        'ifsc' => 'SBIN0011223', 'account' => '38291099887',    'pan' => 'HIJKL8901M', 'id_proof_number' => '890123458901'],
            'EMP009' => ['bank' => 'HDFC Bank',  'ifsc' => 'HDFC0001234', 'account' => '50100445566778', 'pan' => 'IJKLM9012N', 'id_proof_number' => '901234569012'],
            'EMP010' => ['bank' => 'Kotak',      'ifsc' => 'KKBK0000321', 'account' => '7412583690',     'pan' => 'JKLMN0123O', 'id_proof_number' => '012345670123'],
            // EMP011 deliberately has no banking details on file: a recent
            // joiner nobody has set up yet. That state has to be reviewable,
            // because it is the one where somebody quietly does not get paid.
            'EMP012' => ['bank' => 'HDFC Bank',  'ifsc' => 'HDFC0000987', 'account' => '50100778899001', 'pan' => 'LMNOP2345Q', 'id_proof_number' => '234561092345'],
        ];
    }

    /**
     * The net figure each person's payslip states, in paise.
     *
     * In the real system this is typed once, with the payslip, from whatever
     * Excel produced. Here it stands in for that.
     *
     * @return array<string, int>
     */
    protected static function netPay(): array
    {
        return [
            'EMP001' => 6500000, 'EMP002' => 7500000, 'EMP003' => 5500000,
            'EMP004' => 8000000, 'EMP005' => 5000000, 'EMP006' => 7000000,
            'EMP007' => 4200000, 'EMP008' => 4800000, 'EMP009' => 3800000,
            'EMP010' => 4500000, 'EMP012' => 5200000,
        ];
    }

    public static function banked(string $employeeId): ?array
    {
        return self::banking()[$employeeId] ?? null;
    }

    /**
     * Every salary record, most recent month first.
     *
     * Past months are paid. The current month is mid-run on purpose, so all
     * three states are reviewable: some payslips added and paid, some added and
     * awaiting payment, some with nothing added at all.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function all(): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        $employees = DemoEmployees::all()->keyBy('user_id');
        $nets = self::netPay();
        $records = collect();

        for ($monthsBack = 0; $monthsBack < 6; $monthsBack++) {
            $month = Carbon::today()->startOfMonth()->subMonths($monthsBack);
            $period = $month->format('Y-m');

            foreach ($employees as $employeeId => $employee) {
                // Nobody has a salary record for a month before they joined.
                if (Carbon::parse($employee['joined'])->startOfMonth()->greaterThan($month)) {
                    continue;
                }

                // Nor after they left. EMP012 is inactive.
                if ($employee['status'] === 'inactive' && $monthsBack < 2) {
                    continue;
                }

                $net = $nets[$employeeId] ?? null;

                // Somebody with no net on file has had no payslip added.
                $hasPayslip = $net !== null && ($monthsBack > 0 || self::addedThisMonth($employeeId));
                $isPaid = $hasPayslip && ($monthsBack > 0 || self::paidThisMonth($employeeId));

                $records->push([
                    'id' => $period.'-'.$employeeId,
                    'period' => $period,
                    'employee' => $employeeId,
                    'employee_record' => $employee,
                    'currency' => Money::DEFAULT_CURRENCY,
                    'net' => $hasPayslip ? Money::of($net) : null,
                    'payslip' => $hasPayslip ? [
                        'name' => 'payslip-'.strtolower($employeeId).'-'.$period.'.pdf',
                        'size' => '68 KB',
                        'added_on' => $month->copy()->day(3)->toDateString(),
                        'added_by' => 'Pooja Singh',
                    ] : null,
                    'paid_on' => $isPaid ? $month->copy()->day(7)->toDateString() : null,
                    'method' => 'Bank transfer',
                    'banking' => self::banked($employeeId),
                ]);
            }
        }

        return $records->values();
    }

    /** Who already has a payslip added for the month in progress. */
    protected static function addedThisMonth(string $employeeId): bool
    {
        return ! in_array($employeeId, ['EMP007', 'EMP008', 'EMP010'], true);
    }

    /** Who has already been paid for the month in progress. */
    protected static function paidThisMonth(string $employeeId): bool
    {
        return in_array($employeeId, ['EMP001', 'EMP003', 'EMP006'], true);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function forPeriod(string $period): Collection
    {
        return self::all()->where('period', $period)->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function forEmployee(string $employeeId): Collection
    {
        return self::all()->where('employee', $employeeId)->values();
    }

    public static function find(string $period, string $employeeId): ?array
    {
        return self::all()->firstWhere('id', $period.'-'.$employeeId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function latestFor(string $employeeId): ?array
    {
        return self::forEmployee($employeeId)->first();
    }

    public static function currentPeriod(): string
    {
        return Carbon::today()->format('Y-m');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $records
     * @return array<string, mixed>
     */
    public static function stats(Collection $records): array
    {
        $withStatus = fn (string $status) => $records->filter(
            fn (array $r) => SalaryPresenter::statusOf($r) === $status
        );

        // Only what has actually gone out. A total that folds in unpaid rows
        // describes an intention, not a payment.
        $paidOut = MoneyBag::of(
            $withStatus(SalaryPresenter::PAID)->map(fn (array $r) => $r['net'])
        );

        // Everything with a payslip on file, paid or not — what the month costs.
        $onFile = MoneyBag::of(
            $records->reject(fn (array $r) => $r['net'] === null)->map(fn (array $r) => $r['net'])
        );

        return [
            'total' => $records->count(),
            'no_payslip' => $withStatus(SalaryPresenter::NO_PAYSLIP)->count(),
            'awaiting_payment' => $withStatus(SalaryPresenter::AWAITING_PAYMENT)->count(),
            'paid' => $withStatus(SalaryPresenter::PAID)->count(),
            'paid_out' => $paidOut,
            'on_file' => $onFile,
        ];
    }

    /**
     * Employees with no salary record for a period at all.
     *
     * Different from "no payslip yet", and worse: they are not even on the
     * month's list, so nothing about that list makes their absence visible.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function missingFrom(string $period): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        $covered = self::forPeriod($period)->pluck('employee');

        return DemoEmployees::all()
            ->reject(fn (array $e) => $covered->contains($e['user_id']))
            ->reject(fn (array $e) => $e['status'] === 'inactive')
            ->values();
    }

    /**
     * Employees with no banking details on file.
     *
     * They cannot be paid at all: the bank transfer file has nothing to send.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function withoutBanking(): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        $known = array_keys(self::banking());

        return DemoEmployees::all()
            ->reject(fn (array $e) => in_array($e['user_id'], $known, true))
            ->reject(fn (array $e) => $e['status'] === 'inactive')
            ->values();
    }
}
