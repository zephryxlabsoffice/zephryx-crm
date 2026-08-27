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
 * Salary — payroll for the company, and each person's own pay.
 *
 * Three pages: the payroll list (`/salary`), a person's own summary
 * (`/salary/mine`) and a payslip (`/salary/payslip/{period}`).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THIS IS THE MOST SENSITIVE MODULE IN THE APPLICATION
 *
 * It holds what every person earns, their bank account, their PAN and their
 * Aadhaar. Five obligations the backend must honour:
 *
 * 1. `salary.view.all` IS ITS OWN PERMISSION. Seeing what a colleague earns is
 *    itself the harm — there is no "read-only so it is fine" here. It must not
 *    be implied by `employees.view`, and holding it must be rare.
 *
 * 2. THE PAYSLIP ROUTE TAKES NO EMPLOYEE. `/salary/payslip/{period}` resolves
 *    the person from the session, so there is no identifier to tamper with and
 *    no ownership check anybody can forget to write. The management view of
 *    somebody else's run is a separate route, separately guarded. This is
 *    deliberate: the common path is safe by construction.
 *
 * 3. IDENTIFIERS ARE MASKED IN PHP, AND ONLY ON YOUR OWN RECORD. The payroll
 *    list carries no bank, PAN or Aadhaar at all — not masked, absent. A person
 *    sees their own via App\Support\Sensitive; anybody else needs an audited
 *    reveal route that does not exist yet.
 *
 * 4. THE QUERY SCOPES, NOT THE VIEW. §6: ownership is enforced where the data
 *    is fetched. A view that omits a field it was handed has still had that
 *    field in memory, in the response buffer, and possibly in a log.
 *
 * 5. GENERATING PAYROLL MUST BE IDEMPOTENT. See the route comment — running it
 *    twice for one month must not produce two runs, and must not pay anybody
 *    twice.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class SalaryController extends Controller
{
    protected const PER_PAGE = 10;

    /**
     * GET /salary — payroll for a month.
     */
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $period = $filters['period'];

        $runs = DemoSalaries::forPeriod($period);

        return response()->view('salary.index', [
            'activeNav' => 'salary',
            'runs' => $this->paginate($this->matching($runs, $filters), $request),
            'stats' => DemoSalaries::stats($runs),
            'periods' => SalaryPresenter::periodOptions(),
            'departments' => DemoEmployees::all()->pluck('department')->unique()->sort()->values()->all(),
            // Two different gaps, and the difference matters: one is "payroll
            // has not been run for them", the other is "there is nothing to run".
            'missing' => DemoSalaries::missingFrom($period),
            'withoutStructure' => DemoSalaries::withoutStructure(),
        ] + $filters);
    }

    /**
     * GET /salary/mine — the signed-in person's own pay.
     *
     * The one page in this module where PAN, Aadhaar and bank details appear,
     * and only because it is the viewer's own record.
     */
    public function mine(): Response
    {
        $viewer = DemoSalaries::VIEWER;
        $latest = DemoSalaries::latestFor($viewer);
        $history = DemoSalaries::forEmployee($viewer);

        return response()->view('salary.mine', [
            'activeNav' => 'salary',
            'employee' => DemoEmployees::all()->firstWhere('user_id', $viewer),
            'latest' => $latest,
            'history' => $history,
            // Masked here, in PHP. The unmasked values never enter the view.
            'identity' => $latest === null ? null : $this->maskedIdentity($latest, $viewer, $viewer),
        ]);
    }

    /**
     * GET /salary/payslip/{period} — the viewer's own payslip.
     *
     * No employee parameter by design; see the class comment.
     */
    public function payslip(string $period): Response
    {
        $viewer = DemoSalaries::VIEWER;

        abort_if(! $this->isPeriod($period), 404);

        $run = DemoSalaries::find($period, $viewer);

        abort_if($run === null, 404);

        return response()->view('salary.payslip', [
            'activeNav' => 'salary',
            'run' => $run,
            'own' => true,
            'identity' => $this->maskedIdentity($run, $viewer, $viewer),
        ]);
    }

    /**
     * GET /salary/{employee}/{period} — the management view of one run.
     *
     * Guarded by `salary.view.all` when the RBAC engine lands (§5). It shows
     * the pay breakdown, and deliberately NOT the bank, PAN or Aadhaar — those
     * need an audited reveal, not a page render.
     */
    public function show(string $employee, string $period): Response
    {
        abort_if(! $this->isPeriod($period), 404);

        $run = DemoSalaries::find($period, $employee);

        abort_if($run === null, 404);

        return response()->view('salary.payslip', [
            'activeNav' => 'salary',
            'run' => $run,
            'own' => $employee === DemoSalaries::VIEWER,
            // Not the viewer's own record, so nothing sensitive is passed at
            // all — `maskedIdentity` returns null rather than a masked string,
            // because "•••• 4567" still confirms an account exists.
            'identity' => $this->maskedIdentity($run, DemoSalaries::VIEWER, $employee),
        ]);
    }

    /**
     * Mask a run's identifiers — or withhold them entirely.
     *
     * @param  array<string, mixed>  $run
     * @return array<string, string>|null
     */
    protected function maskedIdentity(array $run, ?string $viewerId, string $subjectId): ?array
    {
        if (! Sensitive::viewerMaySee($viewerId, $subjectId)) {
            return null;
        }

        $structure = $run['structure'];

        return [
            'bank' => $structure['bank'],
            'ifsc' => Sensitive::ifsc($structure['ifsc']),
            'account' => Sensitive::accountNumber($structure['account']),
            'pan' => Sensitive::pan($structure['pan']),
            'aadhaar' => Sensitive::aadhaar($structure['aadhaar']),
            'type' => $structure['type'],
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
     * @param  Collection<int, array<string, mixed>>  $runs
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function matching(Collection $runs, array $filters): Collection
    {
        return $runs
            ->when($filters['search'] !== '', fn (Collection $rows) => $rows->filter(
                fn (array $run) => str_contains(
                    mb_strtolower($run['employee_record']['name'].' '.$run['employee']),
                    mb_strtolower($filters['search'])
                )
            ))
            ->when($filters['department'], fn (Collection $rows) => $rows->filter(
                fn (array $run) => $run['employee_record']['department'] === $filters['department']
            ))
            ->when($filters['status'], fn (Collection $rows) => $rows->where('status', $filters['status']))
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
