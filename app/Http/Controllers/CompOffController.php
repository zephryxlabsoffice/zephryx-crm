<?php

namespace App\Http\Controllers;

use App\Models\CompOff;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\SundayAgainstLeaveRequest;
use App\Models\SundayRoster;
use App\Models\Team;
use App\Support\Audit\AuditLog;
use App\Support\CompOffDirectory;
use App\Support\CompOffPolicy;
use App\Support\Rbac\Rbac;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Comp-offs — earned automatically at check-out (see AttendanceController),
 * spent through a request a Manager or Team Lead approves.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TWO KINDS OF REQUEST, ONE PERMISSION DECIDES BOTH
 *
 * Taking an earned comp-off, and working a Sunday already covered by
 * approved leave, are different facts about the same subject — Sunday work
 * — so both are decided behind `attendance.roster`, the same permission that
 * gates rostering itself, and both narrow a Team Lead to their own team the
 * same way (§2.6). See AttendanceController::canRoster, which this class
 * reuses rather than re-deriving the same rule twice.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class CompOffController extends Controller
{
    public function __construct(protected Rbac $rbac, protected AuditLog $audit) {}

    /**
     * GET /comp-offs — the signed-in person's own ledger.
     */
    public function mine(Request $request): Response
    {
        $viewer = $this->employeeFor($request);

        return response()->view('compoffs.mine', [
            'activeNav' => 'attendance',
            'compOffs' => CompOffDirectory::forEmployee($viewer),
            'available' => CompOffDirectory::availableCount($viewer),
            'canDecide' => $this->rbac->can($request->user(), 'attendance.roster'),
        ]);
    }

    /**
     * GET /comp-offs/requests — the queue a Manager or Team Lead works.
     */
    public function index(Request $request): Response
    {
        return response()->view('compoffs.index', [
            'activeNav' => 'attendance',
            'requests' => CompOffDirectory::pendingRequests(),
            'sundayAgainstLeave' => SundayAgainstLeaveRequest::query()
                ->with(['employee.user', 'leaveRequest'])
                ->where('status', SundayAgainstLeaveRequest::PENDING)
                ->orderBy('date')
                ->get(),
        ]);
    }

    /**
     * POST /comp-offs/{compOff}/take — ask to spend one.
     */
    public function requestTake(Request $request, int $compOff): RedirectResponse
    {
        $employee = $this->requireEmployee($request);
        $record = $this->ownedBy($compOff, $employee);

        if (! CompOffPolicy::isTakeable($record)) {
            throw ValidationException::withMessages([
                'take_date' => 'This comp-off is no longer available to take.',
            ]);
        }

        $data = $request->validate([
            'take_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $record->update([
            'status' => CompOff::PENDING,
            'take_date' => $data['take_date'],
            'requested_at' => now(),
        ]);

        $this->audit->record(
            action: AuditLog::COMPOFF_TAKE_REQUESTED,
            actor: $request->user(),
            entityType: 'compoff',
            entityId: (string) $record->id,
            after: 'Requested for '.Carbon::parse($data['take_date'])->format('d M Y'),
            request: $request,
        );

        return redirect()
            ->route('compoffs.mine')
            ->with('status', 'Request sent. It needs a decision before that day counts as taken.')
            ->with('status_tone', 'success');
    }

    /**
     * POST /comp-offs/{compOff}/approve
     */
    public function approve(Request $request, int $compOff): RedirectResponse
    {
        return $this->decide($request, $compOff, CompOff::TAKEN);
    }

    /**
     * POST /comp-offs/{compOff}/reject
     */
    public function reject(Request $request, int $compOff): RedirectResponse
    {
        return $this->decide($request, $compOff, CompOff::REJECTED);
    }

    protected function decide(Request $request, int $compOffId, string $decision): RedirectResponse
    {
        $record = CompOff::findOrFail($compOffId);

        abort_unless($this->canDecide($request, $record->employee), 403);

        if ($record->status !== CompOff::PENDING) {
            return redirect()
                ->route('compoffs.index')
                ->with('status', 'This request has already been decided.')
                ->with('status_tone', 'info');
        }

        $data = $request->validate([
            'note' => $decision === CompOff::REJECTED
                ? ['required', 'string', 'min:5', 'max:1000']
                : ['nullable', 'string', 'max:1000'],
        ]);

        $decider = $this->employeeFor($request);

        // A rejection is not lost — the comp-off goes back to available so it
        // can still be taken before it lapses, the same reason a rejected
        // leave request is not the only chance somebody had to ask.
        $record->update([
            'status' => $decision === CompOff::TAKEN ? CompOff::TAKEN : CompOff::AVAILABLE,
            'take_date' => $decision === CompOff::TAKEN ? $record->take_date : null,
            'decided_by' => $decider?->id,
            'decided_at' => now(),
            'decision_note' => $data['note'] ?? null,
        ]);

        $this->audit->record(
            action: $decision === CompOff::TAKEN ? AuditLog::COMPOFF_TAKEN : AuditLog::COMPOFF_TAKE_REJECTED,
            actor: $request->user(),
            entityType: 'compoff',
            entityId: (string) $record->id,
            after: $decision === CompOff::TAKEN ? 'Approved' : 'Rejected: '.$data['note'],
            request: $request,
        );

        return redirect()
            ->route('compoffs.index')
            ->with('status', $decision === CompOff::TAKEN ? 'Comp-off approved.' : 'Comp-off request rejected.')
            ->with('status_tone', $decision === CompOff::TAKEN ? 'success' : 'info');
    }

    /* ══════════════════════════════════════════════════════════════════════
       SUNDAY AGAINST LEAVE
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * POST /leave/{leaveRequest}/sunday-against-leave
     *
     * Asked BEFORE working the Sunday (decided 2026-09-11) — there is no
     * route that creates one of these once the day has already passed, and
     * `date` must fall inside the leave it is asking to partly reverse.
     */
    public function requestSundayAgainstLeave(Request $request, string $leaveRequest): RedirectResponse
    {
        $employee = $this->requireEmployee($request);

        $leave = LeaveRequest::where('reference', $leaveRequest)->firstOrFail();

        abort_unless($leave->employee_id === $employee->id, 403);

        $data = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $date = Carbon::parse($data['date']);

        if ($date->lessThan($leave->from_date) || $date->greaterThan($leave->to_date)) {
            throw ValidationException::withMessages([
                'date' => 'That date is not inside this leave request.',
            ]);
        }

        // "Must be in the same month" — asked in the same calendar month as
        // the Sunday itself, not chased up weeks later.
        if (! $date->isSameMonth(Carbon::today())) {
            throw ValidationException::withMessages([
                'date' => 'This has to be asked for in the same month as the Sunday.',
            ]);
        }

        $existing = SundayAgainstLeaveRequest::where('leave_request_id', $leave->id)
            ->where('date', $date->toDateString())
            ->whereIn('status', [SundayAgainstLeaveRequest::PENDING, SundayAgainstLeaveRequest::APPROVED])
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'date' => 'There is already a request for this date.',
            ]);
        }

        $sundayRequest = SundayAgainstLeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_request_id' => $leave->id,
            'date' => $date,
            'status' => SundayAgainstLeaveRequest::PENDING,
            'requested_at' => now(),
        ]);

        $this->audit->record(
            action: AuditLog::SUNDAY_AGAINST_LEAVE_REQUESTED,
            actor: $request->user(),
            entityType: 'sunday_against_leave',
            entityId: (string) $sundayRequest->id,
            after: 'Requested to work '.$date->format('d M Y').' against leave '.$leave->reference,
            request: $request,
        );

        return redirect()
            ->route('leave.show', ['leaveRequest' => $leave->reference])
            ->with('status', 'Request sent. Work the day only once it is approved.')
            ->with('status_tone', 'success');
    }

    /**
     * POST /sunday-against-leave/{sundayRequest}/approve
     *
     * Reduces the linked leave request's days by one and rosters the
     * employee for the date — the whole of what "the leave day returns to
     * the balance" and "may now work it" mean in this schema.
     */
    public function approveSundayAgainstLeave(Request $request, int $sundayRequest): RedirectResponse
    {
        $record = SundayAgainstLeaveRequest::with('employee')->findOrFail($sundayRequest);

        abort_unless($this->canDecide($request, $record->employee), 403);

        if ($record->status !== SundayAgainstLeaveRequest::PENDING) {
            return redirect()
                ->route('compoffs.index')
                ->with('status', 'This request has already been decided.')
                ->with('status_tone', 'info');
        }

        $decider = $this->employeeFor($request);
        $leave = LeaveRequest::findOrFail($record->leave_request_id);

        $leave->update(['days' => max(0, $leave->days - 1)]);

        SundayRoster::firstOrCreate(
            ['employee_id' => $record->employee_id, 'date' => $record->date],
            ['rostered_by' => $decider?->id],
        );

        $record->update([
            'status' => SundayAgainstLeaveRequest::APPROVED,
            'decided_by' => $decider?->id,
            'decided_at' => now(),
        ]);

        $this->audit->record(
            action: AuditLog::SUNDAY_AGAINST_LEAVE_APPROVED,
            actor: $request->user(),
            entityType: 'sunday_against_leave',
            entityId: (string) $record->id,
            after: 'Approved — 1 day returned to '.$leave->reference.', '.$record->date->format('d M Y').' rostered',
            request: $request,
        );

        return redirect()
            ->route('compoffs.index')
            ->with('status', 'Approved. The leave day is back on their balance.')
            ->with('status_tone', 'success');
    }

    /**
     * POST /sunday-against-leave/{sundayRequest}/reject
     */
    public function rejectSundayAgainstLeave(Request $request, int $sundayRequest): RedirectResponse
    {
        $record = SundayAgainstLeaveRequest::with('employee')->findOrFail($sundayRequest);

        abort_unless($this->canDecide($request, $record->employee), 403);

        if ($record->status !== SundayAgainstLeaveRequest::PENDING) {
            return redirect()
                ->route('compoffs.index')
                ->with('status', 'This request has already been decided.')
                ->with('status_tone', 'info');
        }

        $data = $request->validate([
            'note' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $decider = $this->employeeFor($request);

        $record->update([
            'status' => SundayAgainstLeaveRequest::REJECTED,
            'decided_by' => $decider?->id,
            'decided_at' => now(),
            'decision_note' => $data['note'],
        ]);

        $this->audit->record(
            action: AuditLog::SUNDAY_AGAINST_LEAVE_REJECTED,
            actor: $request->user(),
            entityType: 'sunday_against_leave',
            entityId: (string) $record->id,
            after: 'Rejected: '.$data['note'],
            request: $request,
        );

        return redirect()
            ->route('compoffs.index')
            ->with('status', 'Request rejected.')
            ->with('status_tone', 'info');
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE PIECES
       ══════════════════════════════════════════════════════════════════════ */

    protected function employeeFor(Request $request): ?Employee
    {
        return Employee::where('user_id', $request->user()?->id)->first();
    }

    protected function requireEmployee(Request $request): Employee
    {
        $employee = $this->employeeFor($request);

        abort_if($employee === null, 403);
        abort_unless($employee->attendsWork(), 403);

        return $employee;
    }

    /**
     * A comp-off scoped to the signed-in employee's own — ownership in the
     * query, the same rule every other module in this application follows.
     */
    protected function ownedBy(int $compOffId, Employee $employee): CompOff
    {
        return CompOff::where('id', $compOffId)
            ->where('employee_id', $employee->id)
            ->firstOrFail();
    }

    /**
     * The same rule AttendanceController::canRoster applies to rostering
     * itself — `teams.edit` is company-wide, `attendance.roster` plus
     * actually leading a team the subject belongs to is a Team Lead's own.
     */
    protected function canDecide(Request $request, ?Employee $subject): bool
    {
        if ($subject === null) {
            return false;
        }

        if ($this->rbac->can($request->user(), 'teams.edit')) {
            return true;
        }

        $decider = $this->employeeFor($request);

        return $this->rbac->can($request->user(), 'attendance.roster')
            && $decider !== null
            && Team::where('lead_id', $decider->id)
                ->whereHas('members', fn ($q) => $q->where('employees.id', $subject->id))
                ->exists();
    }
}
