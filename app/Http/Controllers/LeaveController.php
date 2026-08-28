<?php

namespace App\Http\Controllers;

use App\Support\Demo\DemoEmployees;
use App\Support\Demo\DemoLeave;
use App\Support\LeavePolicy;
use App\Support\LeavePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Leave — requesting time off, and deciding on it.
 *
 * Four pages: the approval queue (`/leave`), a person's own leave
 * (`/leave/mine`), the request form (`/leave/request`) and one request
 * (`/leave/{request}`).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THIS MODULE COUNTS. IT DOES NOT DECIDE.
 *
 * Decided 2026-08-28. There is no working-day calculator, no holiday calendar
 * and no automatic deduction: the requester states how many days it costs and
 * the approver agrees it. What is computed is the sum of what was recorded.
 * See App\Support\LeavePolicy.
 *
 * The policy itself — which leave types exist and how many days each carries —
 * belongs to the Admin Panel (§12) and is only read here.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHAT THE BACKEND OWES
 *
 * 1. `leave.approve` IS ITS OWN PERMISSION, separate from `leave.view`. One
 *    approver (decided 2026-08-28), whoever holds it.
 *
 * 2. NOBODY DECIDES THEIR OWN REQUEST. Even the owner. It is the one rule in
 *    this module that cannot be delegated away, because an approver who can
 *    grant themselves leave makes the whole record meaningless.
 *
 * 3. A DECISION IS ONLY VALID ON A PENDING REQUEST. Two approvers opening the
 *    same request must not both be able to decide it — the write checks the
 *    status inside the transaction, not before it.
 *
 * 4. THE REASON AND CONTACT NUMBER ARE PERSONAL DATA. "Fever, seeing a doctor"
 *    is health information. It reaches the approver and nobody else; it is not
 *    listed on any page showing many people at once.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class LeaveController extends Controller
{
    protected const PER_PAGE = 10;

    /**
     * GET /leave — the approval queue.
     */
    public function index(Request $request): Response
    {
        $tab = $request->validate([
            'tab' => ['nullable', Rule::in(array_merge(['all'], LeavePresenter::statusOptions()))],
        ])['tab'] ?? LeavePresenter::PENDING;

        $all = DemoLeave::all();

        $requests = $tab === 'all' ? $all : $all->where('status', $tab)->values();

        $filters = $this->filters($request);
        $counts = DemoLeave::stats($all);

        return response()->view('leave.index', [
            'activeNav' => 'leave',
            'requests' => $this->paginate($this->matching($requests, $filters), $request),
            'stats' => $counts,
            'tab' => $tab,
            'tabCounts' => [
                'pending' => $counts['pending'],
                'approved' => $counts['approved'],
                'rejected' => $counts['rejected'],
                'cancelled' => $counts['cancelled'],
                'all' => $counts['total'],
            ],
            'absences' => DemoLeave::upcomingAbsences(),
            'departments' => DemoEmployees::all()->pluck('department')->unique()->sort()->values()->all(),
        ] + $filters);
    }

    /**
     * GET /leave/mine — the signed-in person's own leave.
     */
    public function mine(Request $request): Response
    {
        $viewer = DemoLeave::VIEWER;
        $mine = DemoLeave::forEmployee($viewer);

        $tab = $request->validate([
            'tab' => ['nullable', Rule::in(array_merge(['all'], LeavePresenter::statusOptions()))],
        ])['tab'] ?? 'all';

        $shown = $tab === 'all' ? $mine : $mine->where('status', $tab)->values();

        $counts = DemoLeave::stats($mine);

        return response()->view('leave.mine', [
            'activeNav' => 'leave',
            'requests' => $this->paginate($shown->sortByDesc('applied_at')->values(), $request),
            'balance' => LeavePolicy::balance($mine),
            'stats' => $counts,
            'tab' => $tab,
            'tabCounts' => [
                'all' => $counts['total'],
                'pending' => $counts['pending'],
                'approved' => $counts['approved'],
                'rejected' => $counts['rejected'],
                'cancelled' => $counts['cancelled'],
            ],
            // TODO (backend phase): `leave.approve`. This page is reached by a
            // button on the queue, so it needs a way back — but only for the
            // people who could have come from there.
            'canApprove' => true,
        ]);
    }

    /**
     * GET /leave/request — the request form.
     */
    public function create(): Response
    {
        $viewer = DemoLeave::VIEWER;

        return response()->view('leave.request', [
            'activeNav' => 'leave',
            'balance' => LeavePolicy::balance(DemoLeave::forEmployee($viewer)),
            'types' => LeavePolicy::types(),
        ]);
    }

    /**
     * GET /leave/{request} — one request, and the decision on it.
     */
    public function show(string $leaveRequest): Response
    {
        $record = DemoLeave::find($leaveRequest);

        abort_if($record === null, 404);

        $viewer = DemoLeave::VIEWER;

        return response()->view('leave.show', [
            'activeNav' => 'leave',
            'request' => $record,
            'own' => $record['employee'] === $viewer,
            // What the approver needs and the handover never showed: who else
            // is already off on these dates.
            'clashes' => DemoLeave::clashesWith($record),
            // The requester's balance, so a decision is made against the days
            // they actually have rather than in the abstract.
            'balance' => LeavePolicy::balance(DemoLeave::forEmployee($record['employee'])),
            // Nobody decides their own request — not even the owner. Stubbed
            // here; a real check lands with the RBAC engine.
            'canDecide' => LeavePresenter::isDecidable($record) && $record['employee'] !== $viewer,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(LeavePolicy::typeKeys())],
            'department' => ['nullable', 'string', 'max:60'],
        ]);

        $search = trim($validated['q'] ?? '');

        return [
            'search' => $search,
            'type' => $validated['type'] ?? null,
            'department' => $validated['department'] ?? null,
            'filtered' => $search !== ''
                || ($validated['type'] ?? null) !== null
                || ($validated['department'] ?? null) !== null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $requests
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function matching(Collection $requests, array $filters): Collection
    {
        return $requests
            ->when($filters['search'] !== '', fn (Collection $rows) => $rows->filter(
                fn (array $r) => str_contains(
                    mb_strtolower($r['employee_record']['name'].' '.$r['employee'].' '.$r['id']),
                    mb_strtolower($filters['search'])
                )
            ))
            ->when($filters['type'], fn (Collection $rows) => $rows->where('type', $filters['type']))
            ->when($filters['department'], fn (Collection $rows) => $rows->filter(
                fn (array $r) => $r['employee_record']['department'] === $filters['department']
            ))
            // Soonest first: a request that starts tomorrow is the one that has
            // to be decided today.
            ->sortBy('from')
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
