<?php

namespace App\Support\Demo;

use App\Support\Money;
use App\Support\SalaryPresenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sample salary structures and monthly runs, for reviewing the Salary pages
 * before the database exists. Local + debug only, like the other demo sources.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHAT IS CONTRACT HERE, NOT SAMPLE DATA
 *
 * 1. EARNINGS ARE LINES; GROSS IS THEIR SUM. Basic, HRA and special allowance
 *    are stored; the monthly gross is not. A stored gross could disagree with
 *    the lines that produced it, in front of the employee it belongs to.
 *
 * 2. DEDUCTIONS EXIST AS A CONCEPT AND ARE EMPTY TODAY (decided 2026-08-27:
 *    no EPF, no ESI, no professional tax, no TDS). Loss of pay lands here the
 *    moment Attendance and Leave exist — which is why the list is a list and
 *    not a missing feature.
 *
 * 3. AMOUNTS ARE INTEGER MINOR UNITS, via App\Support\Money. Payroll is the
 *    last place a float belongs.
 *
 * 4. IDENTIFIERS ARE MASKED BEFORE THEY LEAVE PHP. The full PAN, Aadhaar and
 *    account numbers below exist so the masking has something to mask; nothing
 *    hands them to a view. See App\Support\Sensitive, and note that in
 *    production these columns are encrypted at rest.
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
     * Monthly gross per employee, in paise, and their banking details.
     *
     * Twelve people, because that is how many the company has. The handover
     * showed forty salary records and "28 paid / 12 pending".
     *
     * @return array<string, array<string, mixed>>
     */
    protected static function structures(): array
    {
        return [
            'EMP001' => ['gross' => 6500000, 'bank' => 'HDFC Bank',  'ifsc' => 'HDFC0001234', 'account' => '50100234567890', 'pan' => 'ABCDE1234F', 'aadhaar' => '123456781234', 'type' => 'Full time'],
            'EMP002' => ['gross' => 7500000, 'bank' => 'ICICI Bank', 'ifsc' => 'ICIC0000456', 'account' => '004501556789',   'pan' => 'BCDEF2345G', 'aadhaar' => '234567892345', 'type' => 'Full time'],
            'EMP003' => ['gross' => 5500000, 'bank' => 'Axis Bank',  'ifsc' => 'UTIB0000789', 'account' => '917010045678',   'pan' => 'CDEFG3456H', 'aadhaar' => '345678903456', 'type' => 'Full time'],
            'EMP004' => ['gross' => 8000000, 'bank' => 'HDFC Bank',  'ifsc' => 'HDFC0000987', 'account' => '50100987654321', 'pan' => 'DEFGH4567I', 'aadhaar' => '456789014567', 'type' => 'Full time'],
            'EMP005' => ['gross' => 5000000, 'bank' => 'SBI',        'ifsc' => 'SBIN0011223', 'account' => '38291045612',    'pan' => 'EFGHI5678J', 'aadhaar' => '567890125678', 'type' => 'Full time'],
            'EMP006' => ['gross' => 7000000, 'bank' => 'ICICI Bank', 'ifsc' => 'ICIC0000456', 'account' => '004501778899',   'pan' => 'FGHIJ6789K', 'aadhaar' => '678901236789', 'type' => 'Full time'],
            'EMP007' => ['gross' => 4200000, 'bank' => 'Axis Bank',  'ifsc' => 'UTIB0000789', 'account' => '917010112233',   'pan' => 'GHIJK7890L', 'aadhaar' => '789012347890', 'type' => 'Full time'],
            'EMP008' => ['gross' => 4800000, 'bank' => 'SBI',        'ifsc' => 'SBIN0011223', 'account' => '38291099887',    'pan' => 'HIJKL8901M', 'aadhaar' => '890123458901', 'type' => 'Full time'],
            'EMP009' => ['gross' => 3800000, 'bank' => 'HDFC Bank',  'ifsc' => 'HDFC0001234', 'account' => '50100445566778', 'pan' => 'IJKLM9012N', 'aadhaar' => '901234569012', 'type' => 'Probation'],
            'EMP010' => ['gross' => 4500000, 'bank' => 'Kotak',      'ifsc' => 'KKBK0000321', 'account' => '7412583690',     'pan' => 'JKLMN0123O', 'aadhaar' => '012345670123', 'type' => 'Probation'],
            // EMP011 is deliberately absent: a recent joiner whose salary
            // structure nobody has set yet. That state has to be reviewable,
            // because it is the one where somebody quietly does not get paid.
            'EMP012' =>['gross' => 5200000, 'bank' => 'HDFC Bank',  'ifsc' => 'HDFC0000987', 'account' => '50100778899001', 'pan' => 'LMNOP2345Q', 'aadhaar' => '234561092345', 'type' => 'Full time'],
        ];
    }

    /**
     * Split a monthly gross into its earning lines.
     *
     * The conventional Indian split: half basic, a fifth HRA, the rest as a
     * special allowance. The last line is computed as the remainder rather than
     * as its own percentage, so the three ALWAYS sum to the gross exactly —
     * three percentages that each round independently do not.
     *
     * @return list<array{label: string, note: string, amount: Money}>
     */
    protected static function earningsFor(int $grossMinor): array
    {
        $basic = intdiv($grossMinor, 2);
        $hra = intdiv($grossMinor, 5);
        $special = $grossMinor - $basic - $hra;

        return [
            ['label' => 'Basic', 'note' => '50% of gross', 'amount' => Money::of($basic)],
            ['label' => 'House rent allowance', 'note' => '20% of gross', 'amount' => Money::of($hra)],
            ['label' => 'Special allowance', 'note' => 'Balance of gross', 'amount' => Money::of($special)],
        ];
    }

    /**
     * Deductions for a run.
     *
     * Empty by decision, not by omission — see SalaryPresenter. Loss of pay
     * belongs here and cannot be computed until Attendance and Leave exist, so
     * it is absent rather than guessed at.
     *
     * @return list<array{label: string, note: string, amount: Money}>
     */
    protected static function deductionsFor(string $employeeId, string $period): array
    {
        return [];
    }

    /**
     * Every monthly run, most recent month first.
     *
     * The current month is generated but unpaid; earlier months are paid. One
     * person is deliberately on hold so that state is reviewable, and the two
     * most recent joiners have no run before they started — a salary record for
     * a month somebody did not work is exactly the sort of thing payroll must
     * not invent.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function all(): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        $employees = DemoEmployees::all()->keyBy('user_id');
        $structures = self::structures();
        $runs = collect();

        for ($monthsBack = 0; $monthsBack < 6; $monthsBack++) {
            $month = Carbon::today()->startOfMonth()->subMonths($monthsBack);
            $period = $month->format('Y-m');

            foreach ($structures as $employeeId => $structure) {
                $employee = $employees->get($employeeId);

                if ($employee === null) {
                    continue;
                }

                // Nobody is paid for a month before they joined.
                if (Carbon::parse($employee['joined'])->startOfMonth()->greaterThan($month)) {
                    continue;
                }

                // Nor after they left. EMP012 is inactive.
                if ($employee['status'] === 'inactive' && $monthsBack < 2) {
                    continue;
                }

                $status = match (true) {
                    $monthsBack > 0 => SalaryPresenter::PAID,
                    // One held back on purpose, so the state is reviewable.
                    $employeeId === 'EMP005' => SalaryPresenter::ON_HOLD,
                    default => SalaryPresenter::PENDING,
                };

                $runs->push([
                    'id' => $period.'-'.$employeeId,
                    'period' => $period,
                    'employee' => $employeeId,
                    'employee_record' => $employee,
                    'currency' => Money::DEFAULT_CURRENCY,
                    'earnings' => self::earningsFor($structure['gross']),
                    'deductions' => self::deductionsFor($employeeId, $period),
                    'status' => $status,
                    'paid_on' => $status === SalaryPresenter::PAID
                        ? $month->copy()->day(7)->toDateString()
                        : null,
                    'method' => 'Bank transfer',
                    'structure' => $structure,
                ]);
            }
        }

        return $runs->values();
    }

    /**
     * The runs for one month.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function forPeriod(string $period): Collection
    {
        return self::all()->where('period', $period)->values();
    }

    /**
     * One person's runs, most recent first.
     *
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
     * The most recent run for a person — what "My Salary" leads with.
     *
     * @return array<string, mixed>|null
     */
    public static function latestFor(string $employeeId): ?array
    {
        return self::forEmployee($employeeId)->first();
    }

    /**
     * The month the pages default to: the one the company is currently in.
     */
    public static function currentPeriod(): string
    {
        return Carbon::today()->format('Y-m');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $runs
     * @return array<string, mixed>
     */
    public static function stats(Collection $runs): array
    {
        $countOf = fn (string $status) => $runs->where('status', $status)->count();

        // Only what has actually gone out. A "total payroll" figure that
        // includes unpaid and held rows describes an intention, not a payment.
        $paidOut = \App\Support\MoneyBag::of(
            $runs->where('status', SalaryPresenter::PAID)
                ->map(fn (array $run) => SalaryPresenter::net($run))
        );

        $committed = \App\Support\MoneyBag::of(
            $runs->whereIn('status', [SalaryPresenter::PENDING, SalaryPresenter::PAID])
                ->map(fn (array $run) => SalaryPresenter::net($run))
        );

        return [
            'total' => $runs->count(),
            'paid' => $countOf(SalaryPresenter::PAID),
            'pending' => $countOf(SalaryPresenter::PENDING),
            'on_hold' => $countOf(SalaryPresenter::ON_HOLD),
            'paid_out' => $paidOut,
            'committed' => $committed,
        ];
    }

    /**
     * Employees with no run for a period — the gap the "Generate" step fills.
     *
     * Worth stating explicitly on the page: an employee silently missing from
     * payroll is a person who does not get paid, and nothing about a list of
     * everyone who IS in it makes that visible.
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
            ->whereIn('user_id', array_keys(self::structures()))
            ->reject(fn (array $employee) => $covered->contains($employee['user_id']))
            ->reject(fn (array $employee) => $employee['status'] === 'inactive')
            ->values();
    }

    /**
     * Employees who have no salary structure recorded at all.
     *
     * Different from the above and worse: no structure means there is nothing
     * to generate FROM, so this person cannot be paid until somebody sets one.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function withoutStructure(): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        $known = array_keys(self::structures());

        return DemoEmployees::all()
            ->reject(fn (array $employee) => in_array($employee['user_id'], $known, true))
            ->values();
    }
}
