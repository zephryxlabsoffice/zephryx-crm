<?php

namespace App\Http\Controllers;

use App\Support\Demo\DemoEmployees;
use App\Support\Demo\DemoSalaries;
use App\Support\SalaryPresenter;
use App\Support\Sensitive;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Salary — recording payslips and tracking who has been paid.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THIS APPLICATION DOES NOT CALCULATE PAYROLL
 *
 * Decided 2026-08-27. Pay is worked out in Excel today and will come from
 * payroll software over an API later. Here, somebody adds the payslip that
 * calculation produced, types the net figure it states, and marks the transfer
 * done. Nothing is derived.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * AND IT IS STILL THE MOST SENSITIVE MODULE IN THE APPLICATION
 *
 * 1. `salary.view.all` IS ITS OWN PERMISSION. Seeing what a colleague earns is
 *    itself the harm; it must not be implied by `employees.view`.
 *
 * 2. THE OWN-PAYSLIP ROUTE TAKES NO EMPLOYEE. `/salary/payslip/{period}`
 *    resolves the person from the session, so there is no identifier to tamper
 *    with and no ownership check anybody can forget to write. The management
 *    view of somebody else's record is a separate, separately guarded route.
 *
 * 3. IDENTIFIERS ARE MASKED IN PHP, AND ONLY ON YOUR OWN RECORD. Payroll
 *    screens carry no bank, PAN or Aadhaar at all. Paying people is the bank
 *    transfer file's job — see App\Support\Sensitive for why that is not the
 *    same problem as putting the numbers on a page.
 *
 * 4. MARKING PAID IS A FINANCIAL WRITE. Single or bulk, it needs an audit
 *    entry, and it must be idempotent — marking an already-paid record must
 *    not move its payment date.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class SalaryController extends Controller
{
    protected const PER_PAGE = 12;

    /**
     * GET /salary — the month's payroll.
     */
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $period = $filters['period'];

        $records = DemoSalaries::forPeriod($period);

        return response()->view('salary.index', [
            'activeNav' => 'salary',
            'records' => $this->paginate($this->matching($records, $filters), $request),
            'stats' => DemoSalaries::stats($records),
            'periods' => SalaryPresenter::periodOptions(),
            'departments' => DemoEmployees::all()->pluck('department')->unique()->sort()->values()->all(),
            'missing' => DemoSalaries::missingFrom($period),
            'withoutBanking' => DemoSalaries::withoutBanking(),
        ] + $filters);
    }

    /**
     * GET /salary/mine — the signed-in person's own pay.
     */
    public function mine(): Response
    {
        $viewer = DemoSalaries::VIEWER;
        $latest = DemoSalaries::latestFor($viewer);

        return response()->view('salary.mine', [
            'activeNav' => 'salary',
            'employee' => DemoEmployees::all()->firstWhere('user_id', $viewer),
            'latest' => $latest,
            'history' => DemoSalaries::forEmployee($viewer),
            // Masked here, in PHP. The unmasked values never enter the view.
            'identity' => $this->maskedIdentity($viewer, $viewer),
            // TODO (backend phase): `salary.view.all`. This page is reached by a
            // button on payroll, so it needs a way back — but only for the
            // people who could have come from there.
            'canViewPayroll' => true,
        ]);
    }

    /**
     * GET /salary/payslip/{period} — the viewer's own payslip for a month.
     *
     * No employee parameter by design; see the class comment.
     */
    public function payslip(string $period): Response
    {
        abort_if(! $this->isPeriod($period), 404);

        $viewer = DemoSalaries::VIEWER;
        $record = DemoSalaries::find($period, $viewer);

        abort_if($record === null, 404);

        return response()->view('salary.record', [
            'activeNav' => 'salary',
            'record' => $record,
            'own' => true,
            'identity' => $this->maskedIdentity($viewer, $viewer),
        ]);
    }

    /**
     * GET /salary/{employee}/{period} — one person's record, for whoever runs
     * payroll. This is where a payslip is added and the transfer marked done.
     *
     * Guarded by `salary.view.all` when the RBAC engine lands (§5).
     */
    public function show(string $employee, string $period): Response
    {
        abort_if(! $this->isPeriod($period), 404);

        $record = DemoSalaries::find($period, $employee);

        abort_if($record === null, 404);

        return response()->view('salary.record', [
            'activeNav' => 'salary',
            'record' => $record,
            'own' => $employee === DemoSalaries::VIEWER,
            // Not the viewer's own record, so nothing sensitive is passed at
            // all — not even a masked string, because "•••• 4567" still
            // confirms an account exists.
            'identity' => $this->maskedIdentity(DemoSalaries::VIEWER, $employee),
        ]);
    }

    /**
     * POST /salary/pay/confirm — name who is about to be marked paid.
     *
     * A read, not a write: it renders the list and asks. Marking twelve people
     * paid by mis-click is a hard thing to notice and an awkward thing to undo,
     * so the destructive step is always the second one.
     *
     * POST rather than GET so a dozen employee ids do not end up in a URL that
     * gets bookmarked, shared or logged.
     */
    public function confirmPayment(Request $request): Response
    {
        $validated = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'employees' => ['required', 'array', 'min:1'],
            'employees.*' => ['string', 'max:32'],
        ]);

        $records = DemoSalaries::forPeriod($validated['period'])
            ->whereIn('employee', $validated['employees'])
            // Only what can actually be paid. A record with no payslip, or one
            // already paid, is silently dropped here rather than being carried
            // into a confirmation that promises something it cannot do.
            ->filter(fn (array $r) => SalaryPresenter::isPayable($r))
            ->values();

        return response()->view('salary.confirm-payment', [
            'activeNav' => 'salary',
            'period' => $validated['period'],
            'records' => $records,
            // The gap between what was ticked and what can be paid, stated
            // rather than swallowed.
            'skipped' => count($validated['employees']) - $records->count(),
        ]);
    }

    /**
     * Mask a record's identifiers — or withhold them entirely.
     *
     * @return array<string, string>|null
     */
    protected function maskedIdentity(?string $viewerId, string $subjectId): ?array
    {
        if (! Sensitive::viewerMaySee($viewerId, $subjectId)) {
            return null;
        }

        $banking = DemoSalaries::banked($subjectId);

        if ($banking === null) {
            return null;
        }

        return [
            'bank' => $banking['bank'],
            'ifsc' => Sensitive::ifsc($banking['ifsc']),
            'account' => Sensitive::accountNumber($banking['account']),
            'pan' => Sensitive::pan($banking['pan']),
            'aadhaar' => Sensitive::aadhaar($banking['aadhaar']),
        ];
    }

    protected function isPeriod(string $period): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period);
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(Request $request): array
    {
        $validated = $request->validate([
            'period' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'q' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', Rule::in(SalaryPresenter::statusOptions())],
        ]);

        $search = trim($validated['q'] ?? '');

        return [
            'period' => $validated['period'] ?? DemoSalaries::currentPeriod(),
            'search' => $search,
            'department' => $validated['department'] ?? null,
            'status' => $validated['status'] ?? null,
            'filtered' => $search !== ''
                || ($validated['department'] ?? null) !== null
                || ($validated['status'] ?? null) !== null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $records
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function matching(Collection $records, array $filters): Collection
    {
        return $records
            ->when($filters['search'] !== '', fn (Collection $rows) => $rows->filter(
                fn (array $r) => str_contains(
                    mb_strtolower($r['employee_record']['name'].' '.$r['employee']),
                    mb_strtolower($filters['search'])
                )
            ))
            ->when($filters['department'], fn (Collection $rows) => $rows->filter(
                fn (array $r) => $r['employee_record']['department'] === $filters['department']
            ))
            ->when($filters['status'], fn (Collection $rows) => $rows->filter(
                fn (array $r) => SalaryPresenter::statusOf($r) === $filters['status']
            ))
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function paginate(Collection $rows, Request $request): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            items: $rows->forPage($page, self::PER_PAGE)->values(),
            total: $rows->count(),
            perPage: self::PER_PAGE,
            currentPage: $page,
            options: ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
