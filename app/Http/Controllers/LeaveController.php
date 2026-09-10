<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Support\Audit\AuditLog;
use App\Support\EmployeeDirectory;
use App\Support\LeaveDirectory;
use App\Support\LeavePolicy;
use App\Support\LeavePresenter;
use App\Support\Notifier;
use App\Support\Rbac\Rbac;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
 * THE THREE RULES THE WRITES ENFORCE
 *
 * 1. NOBODY DECIDES THEIR OWN REQUEST. Not even the owner. It is the one rule
 *    here that cannot be delegated away: an approver who can grant themselves
 *    leave makes the whole record meaningless.
 *
 * 2. A DECISION IS ONLY VALID ON A PENDING REQUEST, and the status is checked
 *    INSIDE the transaction — two approvers opening the same request must not
 *    both be able to decide it, and a check before the transaction is a race
 *    that shows up as one decision silently overwriting another.
 *
 * 3. A REJECTION CARRIES A REASON. The handover's reject button captured
 *    nothing, which leaves the person guessing why.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class LeaveController extends Controller
{
    protected const PER_PAGE = 10;

    public function __construct(
        protected Rbac $rbac,
        protected AuditLog $audit,
        protected Notifier $notify,
    ) {
    }

    /**
     * GET /leave — the approval queue.
     */
    public function index(Request $request): Response
    {
        $tab = $request->validate([
            'tab' => ['nullable', Rule::in(array_merge(['all'], LeavePresenter::statusOptions()))],
        ])['tab'] ?? LeavePresenter::PENDING;

        $filters = $this->filters($request);
        $counts = LeaveDirectory::stats();

        $query = LeaveDirectory::query($filters, $tab === 'all' ? null : $tab);

        return response()->view('leave.index', [
            'activeNav' => 'leave',
            'requests' => $this->paginate($query),
            'stats' => $counts,
            'tab' => $tab,
            'tabCounts' => [
                'pending' => $counts['pending'],
                'approved' => $counts['approved'],
                'rejected' => $counts['rejected'],
                'cancelled' => $counts['cancelled'],
                'all' => $counts['total'],
            ],
            'absences' => LeaveDirectory::upcomingAbsences(),
            'departments' => EmployeeDirectory::departmentsInUse(),
        ] + $filters);
    }

    /**
     * GET /leave/mine — the signed-in person's own leave.
     */
    public function mine(Request $request): Response
    {
        $viewer = $this->employeeFor($request);
        $mine = LeaveDirectory::forEmployee($viewer);

        $tab = $request->validate([
            'tab' => ['nullable', Rule::in(array_merge(['all'], LeavePresenter::statusOptions()))],
        ])['tab'] ?? 'all';

        $shown = $tab === 'all' ? $mine : $mine->where('status', $tab)->values();

        $counts = $this->countsOf($mine);

        return response()->view('leave.mine', [
            'activeNav' => 'leave',
            'requests' => $this->paginateRows($shown, $request),
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
            // The way back to the queue, for the people who could have come
            // from it.
            'canApprove' => $this->rbac->can($request->user(), 'leave.approve'),
        ]);
    }

    /**
     * GET /leave/request — the request form.
     */
    public function create(Request $request): Response
    {
        return response()->view('leave.request', [
            'activeNav' => 'leave',
            'balance' => LeavePolicy::balance(LeaveDirectory::forEmployee($this->employeeFor($request))),
            'types' => LeavePolicy::types(),
        ]);
    }

    /**
     * GET /leave/{request} — one request, and the decision on it.
     */
    public function show(Request $request, string $leaveRequest): Response
    {
        $record = $this->find($leaveRequest);

        $viewer = $this->employeeFor($request);
        $own = $viewer !== null && $record['model']->employee_id === $viewer->id;

        return response()->view('leave.show', [
            'activeNav' => 'leave',
            'request' => $record,
            'own' => $own,
            // What the approver needs and the handover never showed: who else
            // is already off on these dates.
            'clashes' => LeaveDirectory::clashesWith($record),
            // The requester's balance, so a decision is made against the days
            // they actually have rather than in the abstract.
            'balance' => LeavePolicy::balance(LeaveDirectory::forEmployee($record['model']->employee)),
            /*
             * Nobody decides their own request — not even the owner, and not
             * even with the permission. Both halves are checked again on the
             * write; this only decides whether the buttons are drawn.
             */
            'canDecide' => $this->rbac->can($request->user(), 'leave.approve')
                && LeavePresenter::isDecidable($record)
                && ! $own,
            // Withdrawing is the requester's own act, and only theirs.
            'canCancel' => $own && LeavePresenter::isCancellable($record),
        ]);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE WRITES
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * Ask for time off.
     *
     * The days are the requester's number. Nothing here recalculates it — see
     * the head of this class — but the dates have to make sense as a range, and
     * a request cannot cost more days than the range could contain.
     */
    public function store(Request $request): RedirectResponse
    {
        $employee = $this->requireEmployee($request);

        $data = $request->validate([
            'type' => ['required', Rule::in(LeavePolicy::typeKeys())],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'days' => ['required', 'numeric', 'min:0.5', 'max:365'],
            // Required. A request with no reason is one the approver has to go
            // and ask about, which is the delay the form exists to avoid.
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'contact' => ['nullable', 'string', 'max:32'],
        ]);

        $span = Carbon::parse($data['from'])->diffInDays(Carbon::parse($data['to'])) + 1;

        if ($data['days'] > $span) {
            /*
             * The one arithmetic check in a module that deliberately does not
             * do arithmetic. It is not deciding how many days the range is
             * worth — a week with a holiday in it may well cost four — it is
             * refusing a figure the range cannot contain at all, which is a
             * typo rather than a judgement.
             */
            throw ValidationException::withMessages([
                'days' => 'Those dates cover '.$span.' '.($span === 1 ? 'day' : 'days').', so the request cannot cost more than that.',
            ]);
        }

        $leave = LeaveRequest::create([
            'reference' => $this->nextReference(),
            'employee_id' => $employee->id,
            'type' => $data['type'],
            'from_date' => $data['from'],
            'to_date' => $data['to'],
            'days' => $data['days'],
            'reason' => $data['reason'],
            'contact_number' => $data['contact'] ?? null,
            'status' => LeavePresenter::PENDING,
            'applied_at' => now(),
        ]);

        $this->audit->record(
            action: AuditLog::LEAVE_REQUESTED,
            actor: $request->user(),
            entityType: 'leave',
            entityId: $leave->reference,
            /*
             * The REASON IS NOT IN THE AUDIT ENTRY. "Fever, seeing a doctor" is
             * health information; it belongs on the request, where the approver
             * reads it, and not in a log the Admin Panel lists by the page.
             */
            after: LeavePolicy::label($leave->type).' · '
                .$leave->from_date->format('d M Y').' to '.$leave->to_date->format('d M Y')
                .' · '.$leave->days.' days',
            request: $request,
        );

        return redirect()
            ->route('leave.show', ['leaveRequest' => $leave->reference])
            ->with('status', 'Request submitted. It is waiting for a decision.')
            ->with('status_tone', 'success');
    }

    public function approve(Request $request, string $leaveRequest): RedirectResponse
    {
        return $this->decide($request, $leaveRequest, LeavePresenter::APPROVED);
    }

    public function reject(Request $request, string $leaveRequest): RedirectResponse
    {
        return $this->decide($request, $leaveRequest, LeavePresenter::REJECTED);
    }

    /**
     * Withdraw a request you made.
     *
     * The requester's own act — pending, or approved and not yet started, per
     * LeavePresenter::isCancellable. Leave already under way is a conversation,
     * not a button.
     */
    public function cancel(Request $request, string $leaveRequest): RedirectResponse
    {
        $record = $this->find($leaveRequest);
        $model = $record['model'];

        $viewer = $this->employeeFor($request);

        // Only the person who asked. Not the approver — refusing somebody's
        // leave is a rejection, with a reason, and calling it a withdrawal
        // would put words in their mouth.
        abort_unless($viewer !== null && $model->employee_id === $viewer->id, 403);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if (! LeavePresenter::isCancellable($record)) {
            throw ValidationException::withMessages([
                'note' => 'This request can no longer be withdrawn.',
            ]);
        }

        $before = $model->status;

        $model->update([
            'status' => LeavePresenter::CANCELLED,
            'decision_note' => $data['note'] ?? null,
        ]);

        $this->audit->record(
            action: AuditLog::LEAVE_CANCELLED,
            actor: $request->user(),
            entityType: 'leave',
            entityId: $model->reference,
            before: $before,
            after: 'Withdrawn by the person who asked',
            request: $request,
        );

        return redirect()
            ->route('leave.show', ['leaveRequest' => $model->reference])
            ->with('status', 'Request withdrawn.')
            ->with('status_tone', 'info');
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE PIECES
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * Approve or reject, with the two rules that make either safe.
     */
    protected function decide(Request $request, string $reference, string $decision): RedirectResponse
    {
        $record = $this->find($reference);
        $model = $record['model'];

        $decider = $this->employeeFor($request);

        if ($decider !== null && $model->employee_id === $decider->id) {
            /*
             * The rule that cannot be delegated away. Checked here as well as
             * hidden in the view, because a button that is not drawn is not a
             * control — and this is the one somebody would think to try.
             */
            throw ValidationException::withMessages([
                'note' => 'You cannot decide your own leave request.',
            ]);
        }

        $data = $request->validate([
            'note' => $decision === LeavePresenter::REJECTED
                // Rejecting requires a reason. Approving does not: "yes" needs
                // no explanation, and demanding one would only produce "ok".
                ? ['required', 'string', 'min:5', 'max:1000']
                : ['nullable', 'string', 'max:1000'],
        ]);

        /*
         * The status is checked INSIDE the transaction and the row is locked
         * while it is. Two approvers opening the same request at once must not
         * both be able to decide it, and a check made before the transaction is
         * a race whose symptom is one decision silently overwriting another.
         */
        $decided = DB::transaction(function () use ($model, $decision, $data, $decider) {
            $fresh = LeaveRequest::whereKey($model->id)->lockForUpdate()->first();

            if ($fresh === null || $fresh->status !== LeavePresenter::PENDING) {
                return null;
            }

            $fresh->update([
                'status' => $decision,
                'decided_by' => $decider?->id,
                'decided_at' => now(),
                'decision_note' => $data['note'] ?? null,
            ]);

            return $fresh;
        });

        if ($decided === null) {
            return redirect()
                ->route('leave.show', ['leaveRequest' => $model->reference])
                ->with('status', 'This request has already been decided.')
                ->with('status_tone', 'info');
        }

        $this->audit->record(
            action: $decision === LeavePresenter::APPROVED ? AuditLog::LEAVE_APPROVED : AuditLog::LEAVE_REJECTED,
            actor: $request->user(),
            entityType: 'leave',
            entityId: $decided->reference,
            before: LeavePresenter::PENDING,
            after: $decision === LeavePresenter::APPROVED
                ? 'Approved'
                : 'Rejected: '.$data['note'],
            request: $request,
        );

        /*
         * Outside the transaction, and after the "already decided" return
         * above. A second approver whose write lost the race gets the message
         * and sends nothing — one decision, one notification, however many
         * people pressed the button.
         */
        $this->notify->leaveDecided($decided->load('employee.user'), $request->user());

        return redirect()
            ->route('leave.show', ['leaveRequest' => $decided->reference])
            ->with('status', $decision === LeavePresenter::APPROVED ? 'Leave approved.' : 'Leave rejected.')
            ->with('status_tone', $decision === LeavePresenter::APPROVED ? 'success' : 'info');
    }

    /**
     * @return array<string, mixed>
     */
    protected function find(string $reference): array
    {
        $found = LeaveDirectory::find($reference);

        abort_if($found === null, 404);

        return $found;
    }

    protected function employeeFor(Request $request): ?Employee
    {
        return Employee::where('user_id', $request->user()?->id)->first();
    }

    /**
     * A Mentor and the owner hold no Employee base, so they have no leave to
     * ask for (§2.1).
     */
    protected function requireEmployee(Request $request): Employee
    {
        $employee = $this->employeeFor($request);

        abort_if($employee === null, 403);

        return $employee;
    }

    /**
     * The next reference, year-scoped and derived from the highest existing one.
     */
    protected function nextReference(): string
    {
        $prefix = 'LV-'.now()->year.'-';

        $highest = LeaveRequest::query()
            ->where('reference', 'like', $prefix.'%')
            ->selectRaw('max(cast(substr(reference, ?) as integer)) as n', [strlen($prefix) + 1])
            ->value('n');

        return $prefix.str_pad((string) (((int) $highest) + 1), 3, '0', STR_PAD_LEFT);
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
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    protected function countsOf($rows): array
    {
        return [
            'total' => $rows->count(),
            'pending' => $rows->where('status', LeavePresenter::PENDING)->count(),
            'approved' => $rows->where('status', LeavePresenter::APPROVED)->count(),
            'rejected' => $rows->where('status', LeavePresenter::REJECTED)->count(),
            'cancelled' => $rows->where('status', LeavePresenter::CANCELLED)->count(),
        ];
    }

    /**
     * @param  Builder<LeaveRequest>  $query
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function paginate(Builder $query): LengthAwarePaginator
    {
        return $query->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (LeaveRequest $r) => LeaveDirectory::row($r));
    }

    /**
     * Pagination over one person's own requests, which are loaded whole because
     * the balance beside them is a sum over all of them anyway.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function paginateRows($rows, Request $request): LengthAwarePaginator
    {
        $page = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            items: $rows->forPage($page, self::PER_PAGE)->values(),
            total: $rows->count(),
            perPage: self::PER_PAGE,
            currentPage: $page,
            options: ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
