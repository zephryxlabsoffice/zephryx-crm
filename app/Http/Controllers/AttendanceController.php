<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\CompOff;
use App\Models\Employee;
use App\Models\SundayRoster;
use App\Models\Team;
use App\Support\AttendanceDirectory;
use App\Support\AttendancePolicy;
use App\Support\AttendancePresenter as P;
use App\Support\Audit\AuditLog;
use App\Support\CompOffPolicy;
use App\Support\Holidays;
use App\Support\Rbac\Rbac;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Attendance - checking in, checking out, and keeping the record honest.
 *
 * Three pages: the day's roll (`/attendance`), a person's own attendance with
 * its calendar (`/attendance/mine`), and one day's record
 * (`/attendance/{record}`).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THERE IS NO APPROVAL. THERE IS NEVER GOING TO BE ONE.
 *
 * Decided 2026-09-03, and it is the decision the whole module is shaped around.
 *
 * A check-in is a FACT, not a request. Somebody walked in at 09:21; that
 * happened whether or not a manager gets round to agreeing with it. So a record
 * counts from the moment it is made, and there is no pending state, no queue,
 * no approver and no `attendance.approve` route.
 *
 * What HR and the owner get instead is the ability to REJECT a record after the
 * fact, with a reason. That is a correction, not a gate, and the difference is
 * everything:
 *
 *   - Approval blocks by default. Every day of every person needs somebody's
 *     click before it counts, which at twelve people is roughly 250 clicks a
 *     month, and the predictable outcome is a backlog and then a rubber stamp.
 *     A rubber stamp is worse than no approval, because it looks like control.
 *   - Rejection is the exception. Records are wrong occasionally - a duplicate
 *     from a shared login, a door reader that double-fires - and those are the
 *     handful of days a month a human should actually look at.
 *
 * The handover had a Mark Attendance screen with a Pending / Approved /
 * Rejected queue and twelve requests waiting in it. That screen is gone. If it
 * comes back, this comment is the argument it has to beat.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHAT THE BACKEND OWES
 *
 * 1. THE CLOCK IS THE SERVER'S. Check-in and check-out times are taken from
 *    the server, never from anything the browser sends. A posted timestamp is
 *    a form field, and a form field is something anyone can type into.
 *
 * 2. CHECKING IN IS IDEMPOTENT PER DAY. One person, one date, one record -
 *    enforced by a unique key on (employee, date), not by the button being
 *    hidden. Two rapid submits must produce one record, and checking out twice
 *    must not move the first check-out time.
 *
 * 3. NO WRITE EVER CHANGES A TIME. Not check-in, not check-out, not the
 *    rejection. A wrong record is rejected with a reason and stays legible;
 *    editing the times would make the audit trail a record of the last person
 *    to touch it rather than of what happened.
 *
 * 4. `attendance.reject` IS ITS OWN PERMISSION, separate from
 *    `attendance.view`. HR and the owner (§2.6), and every rejection carries
 *    who, when and why (§6).
 *
 * 5. NOBODY REJECTS THEIR OWN RECORD. Same rule, and same reason, as nobody
 *    approving their own leave.
 *
 * 6. THE TEN-HOUR RULE NEEDS NO JOB. A day left open past the window reads as
 *    rejected because the clock passed it, not because something stamped it -
 *    see AttendancePolicy::autoRejected. Do not add a nightly task to write
 *    that flag: it would be a second source of truth, and the first night it
 *    failed to run, a hundred days would quietly count as present.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class AttendanceController extends Controller
{
    protected const PER_PAGE = 10;

    /**
     * How far back a Sunday or holiday can be rostered. One week, because a
     * comp-off lapses on the Sunday after the day that earned it
     * (CompOffPolicy::expiresOn) - anything older would be granted already
     * expired.
     */
    protected const ROSTER_BACK_DAYS = 7;

    public function __construct(protected Rbac $rbac, protected AuditLog $audit)
    {
    }

    /**
     * GET /attendance - one day's roll across the company.
     */
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $date = $filters['date'];

        $roll = AttendanceDirectory::forDate($date);
        $stats = AttendanceDirectory::stats($roll);

        return response()->view('attendance.index', [
            'activeNav' => 'attendance',
            'rows' => $this->paginate($this->matching($roll, $filters), $request),
            'stats' => $stats,
            'breakdown' => P::breakdown($stats, $stats['headcount']),
            // Days somebody checked into and never out of. The one thing on
            // this page that is actually actionable.
            'openRecords' => AttendanceDirectory::missingCheckOuts(),
            'departments' => \App\Support\EmployeeDirectory::departmentsInUse(),
            'isToday' => Carbon::parse($date)->isToday(),
            'workingDay' => AttendancePolicy::isWorkingDay($date),
            'holiday' => AttendancePolicy::holidayOn($date),
            // How many people turned up on a day the board says the office was
            // shut, and whether that is enough to doubt the holiday rather than
            // admire the dedication. See AttendancePresenter::holidayLooksWrong.
            'holidayWorked' => $roll->whereNotNull('id')->count(),
            'holidayAnnouncement' => Holidays::announcementOn($date),
            'mayRoster' => $this->rbac->can($request->user(), 'attendance.roster')
                || $this->rbac->can($request->user(), 'attendance.reject'),
            'mayEnter' => $this->rbac->can($request->user(), 'attendance.reject'),
        ] + $filters);
    }

    /**
     * GET /attendance/mine - the signed-in person's own attendance.
     */
    public function mine(Request $request): Response
    {
        /*
         * The viewer's own employment record, from the session. There is no
         * parameter here for anybody to change to somebody else's - the same
         * reason the payslip route takes a period and no employee.
         */
        $viewer = $this->employeeFor($request);

        $month = P::month($request->validate([
            'month' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ])['month'] ?? null);

        $records = AttendanceDirectory::forEmployee($viewer);
        $leaveDates = $viewer ? AttendanceDirectory::leaveDates($viewer->id) : [];
        $rosteredDates = $viewer ? AttendanceDirectory::rosteredDates($viewer->id) : [];

        $today = AttendanceDirectory::today($viewer);
        $summary = AttendancePolicy::monthSummary($month, $records, $leaveDates, $rosteredDates);

        return response()->view('attendance.mine', [
            'activeNav' => 'attendance',
            'month' => $month,
            // The arrows are links, not script. Forward is capped at the
            // current month: there is nothing to show in November.
            'previousMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->startOfMonth()->isAfter(Carbon::today()->startOfMonth())
                ? null
                : $month->copy()->addMonth()->format('Y-m'),
            'calendar' => P::calendar($month, $records, $leaveDates, $rosteredDates),
            'summary' => $summary,
            'breakdown' => P::breakdown($summary, (int) $summary['days_counted']),
            // The month before, so the KPI tiles can say "3 fewer than last
            // month" instead of the handover's hardcoded "12% vs last month".
            'previousSummary' => AttendancePolicy::monthSummary($month->copy()->subMonth(), $records, $leaveDates, $rosteredDates),
            'history' => $this->paginate($records->values(), $request),
            'today' => $today,
            'todayState' => AttendancePolicy::evaluate(
                Carbon::today(),
                $today,
                in_array(Carbon::today()->toDateString(), $leaveDates, true),
                in_array(Carbon::today()->toDateString(), $rosteredDates, true),
            ),
            // The way back to the roll, for the people who could have come from
            // it. Everybody else has no roll to return to.
            'canTrack' => $this->rbac->can($request->user(), 'attendance.view.all'),
            // Somebody with no employment record - a Mentor, the owner - has no
            // attendance of their own at all (§2.1), and the page says so
            // rather than drawing an empty calendar as though they were absent
            // every day.
            'hasRecord' => $viewer !== null,
            // False only when there IS a record and it says freelance (decided
            // 2026-09-11: paid against work, not time, so no clock for them).
            // A null viewer is left true here - same as before this flag
            // existed - because that is §2.1's separate, pre-existing case.
            'attends' => $viewer === null || $viewer->attendsWork(),
        ]);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE CLOCK
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * Check in.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THE TIME IS THE SERVER'S AND THE PERSON IS THE SESSION'S
     *
     * Neither is a parameter, so there is nothing here to tamper with: no
     * employee id to swap and no timestamp to type. That is the whole design of
     * the route, and it is why this method validates nothing.
     *
     * Idempotent per day, enforced by the unique key rather than by the button
     * being hidden - a second submit finds the existing record and does not
     * move its time.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function checkIn(Request $request): RedirectResponse
    {
        $employee = $this->requireEmployee($request);
        $today = Carbon::today();

        $existing = AttendanceRecord::where('employee_id', $employee->id)
            ->whereDate('date', $today->toDateString())
            ->first();

        if ($existing !== null) {
            return redirect()
                ->route('attendance.mine')
                ->with('status', 'You are already checked in for today, at '
                    .Carbon::parse($existing->check_in)->format('g:i A').'.')
                ->with('status_tone', 'info');
        }

        $record = AttendanceRecord::create([
            'employee_id' => $employee->id,
            'date' => $today,
            'check_in' => Carbon::now()->format('H:i'),
        ]);

        $this->audit->record(
            action: AuditLog::ATTENDANCE_CHECKED_IN,
            actor: $request->user(),
            entityType: 'attendance',
            entityId: $record->fresh('employee.user')->reference(),
            after: 'Checked in at '.Carbon::now()->format('g:i A'),
            request: $request,
        );

        return redirect()
            ->route('attendance.mine')
            ->with('status', 'Checked in at '.Carbon::now()->format('g:i A').'.')
            ->with('status_tone', 'success');
    }

    /**
     * Check out.
     *
     * Also idempotent: a second check-out does not move the first one's time.
     * There is no way to check out of a day you never checked into, which is
     * why the missing record is a message rather than a new row.
     */
    public function checkOut(Request $request): RedirectResponse
    {
        $employee = $this->requireEmployee($request);

        $record = AttendanceRecord::where('employee_id', $employee->id)
            ->whereDate('date', Carbon::today()->toDateString())
            ->first();

        if ($record === null) {
            return redirect()
                ->route('attendance.mine')
                ->with('status', 'There is no check-in for today to close.')
                ->with('status_tone', 'warning');
        }

        if ($record->check_out !== null) {
            return redirect()
                ->route('attendance.mine')
                ->with('status', 'You checked out at '.Carbon::parse($record->check_out)->format('g:i A').'.')
                ->with('status_tone', 'info');
        }

        $record->update(['check_out' => Carbon::now()->format('H:i')]);

        $this->audit->record(
            action: AuditLog::ATTENDANCE_CHECKED_OUT,
            actor: $request->user(),
            entityType: 'attendance',
            entityId: $record->fresh('employee.user')->reference(),
            after: 'Checked out at '.Carbon::now()->format('g:i A'),
            request: $request,
        );

        $earned = $this->earnCompOffIfDue($request, $employee, $record->fresh());

        return redirect()
            ->route('attendance.mine')
            ->with('status', 'Checked out at '.Carbon::now()->format('g:i A').'.'
                .($earned ? ' A rostered day, worked in full - one comp-off added.' : ''))
            ->with('status_tone', 'success');
    }

    /**
     * Write the comp-off a full rostered day just earned, if it has not
     * already been.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * WHY THIS RUNS AT CHECK-OUT AND NOWHERE ELSE
     *
     * There is no midnight job anywhere in this module (§ the head of this
     * class), and a comp-off is the one fact here that has to survive past
     * the day it was earned - unlike a day's STATE, which AttendancePolicy can
     * always recompute later, a comp-off is a ledger row that gets spent or
     * left to lapse. Check-out is the one moment a person's own action
     * settles whether the day was a full one, so it is the one place this
     * gets written, exactly as check-in and check-out are the only writes
     * anywhere else in this controller.
     *
     * "Half a Sunday earns nothing" - only PRESENT earns one, never HALF_DAY.
     * ─────────────────────────────────────────────────────────────────────────
     */
    protected function earnCompOffIfDue(Request $request, Employee $employee, AttendanceRecord $record): bool
    {
        $date = $record->date->toDateString();

        if (! in_array($date, AttendanceDirectory::rosteredDates($employee->id), true)) {
            return false;
        }

        $evaluated = AttendancePolicy::evaluate($record->date, $record->toRecordArray(), false, true);

        if ($evaluated['state'] !== P::PRESENT) {
            return false;
        }

        $compOff = CompOff::firstOrCreate(
            ['employee_id' => $employee->id, 'earned_on' => $date],
            ['expires_on' => CompOffPolicy::expiresOn($date), 'status' => CompOff::AVAILABLE],
        );

        if (! $compOff->wasRecentlyCreated) {
            return false;
        }

        $this->audit->record(
            action: AuditLog::COMPOFF_EARNED,
            actor: $request->user(),
            entityType: 'compoff',
            entityId: (string) $compOff->id,
            after: 'Earned for '.Carbon::parse($date)->format('d M Y').', expires '.$compOff->expires_on->format('d M Y'),
            request: $request,
        );

        return true;
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE ROSTER
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * GET /attendance/roster
     *
     * Sunday work is rostered, not requested - there is no approval step, so
     * this page is the whole of the act: pick a person, pick an upcoming
     * Sunday or holiday, and the row exists.
     */
    public function roster(Request $request): Response
    {
        $rosterer = $this->employeeFor($request);

        return response()->view('attendance.roster', [
            'activeNav' => 'attendance',
            'employeeChoices' => $this->rosterableEmployees($rosterer, $request),
            'upcoming' => $this->upcomingRosterableDates(),
            'roster' => SundayRoster::query()
                ->with(['employee.user', 'rosterer.user'])
                ->whereDate('date', '>=', Carbon::today()->subDays(self::ROSTER_BACK_DAYS))
                ->orderBy('date')
                ->get(),
        ]);
    }

    /**
     * POST /attendance/roster
     */
    public function storeRoster(Request $request): RedirectResponse
    {
        /*
         * Past dates too, since 2026-10-27: somebody who worked a Sunday or a
         * holiday without being rostered first can still be given the
         * comp-off. Bounded to the comp-off window - a day older than that
         * would earn a comp-off that has already lapsed.
         */
        $earliest = Carbon::today()->subDays(self::ROSTER_BACK_DAYS);
        $tracking = AttendancePolicy::startsOn();

        if ($tracking !== null && $tracking->greaterThan($earliest)) {
            $earliest = $tracking;
        }

        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$earliest->toDateString()],
        ], [
            'date.after_or_equal' => 'That is too far back to roster.',
        ]);

        if (AttendancePolicy::isWorkingDay($data['date'])) {
            throw ValidationException::withMessages([
                'date' => 'That is already a working day - there is nothing to roster it for.',
            ]);
        }

        $target = Employee::with('user')->findOrFail($data['employee_id']);

        abort_unless($this->canRoster($request, $target), 403);

        if (! $target->attendsWork()) {
            throw ValidationException::withMessages([
                'employee_id' => 'Freelancers have no attendance to roster.',
            ]);
        }

        $rosterer = $this->employeeFor($request);

        $past = Carbon::parse($data['date'])->lessThanOrEqualTo(Carbon::today());

        // Rostering yourself for a day already worked is granting yourself a
        // comp-off. The same rule as approving your own leave (§2.6).
        if ($past && $rosterer !== null && $rosterer->id === $target->id) {
            throw ValidationException::withMessages([
                'employee_id' => 'You cannot roster yourself for a day that has already happened.',
            ]);
        }

        $roster = SundayRoster::firstOrCreate(
            ['employee_id' => $target->id, 'date' => $data['date']],
            ['rostered_by' => $rosterer?->id],
        );

        if ($roster->wasRecentlyCreated) {
            $this->audit->record(
                action: AuditLog::ATTENDANCE_ROSTERED,
                actor: $request->user(),
                entityType: 'attendance',
                entityId: $target->user?->user_id.'-'.$data['date'],
                after: $target->user?->name.' rostered for '.Carbon::parse($data['date'])->format('d M Y'),
                request: $request,
            );
        }

        /*
         * A day already worked earns its comp-off now. Check-out is normally
         * the moment this happens, and on a past day that moment has gone -
         * but the rule is identical: a full day only, never a half.
         */
        $earned = false;

        if ($past) {
            $record = AttendanceRecord::where('employee_id', $target->id)
                ->whereDate('date', $data['date'])
                ->whereNotNull('check_out')
                ->first();

            if ($record !== null) {
                $earned = $this->earnCompOffIfDue($request, $target, $record);
            }
        }

        $when = Carbon::parse($data['date'])->format('d M Y');

        return redirect()
            ->route('attendance.roster')
            ->with('status', ($roster->wasRecentlyCreated
                ? $target->user?->name.' is rostered for '.$when.'.'
                : $target->user?->name.' was already rostered for that date.')
                .($earned ? ' They worked it in full - one comp-off added.' : '')
                .($past && ! $earned && $roster->wasRecentlyCreated
                    ? ' No comp-off: there is no full day recorded for them on that date.'
                    : ''))
            ->with('status_tone', 'success');
    }

    /**
     * Whether this person may roster THAT employee.
     *
     * The same shape as TeamController::canManageMembers: `teams.edit` is the
     * wide answer - a Manager's authority over the whole Teams module - and
     * otherwise `attendance.roster` plus actually leading a team the target
     * belongs to (§2.6). The key opens the route; team leadership decides
     * whose roster.
     */
    protected function canRoster(Request $request, Employee $target): bool
    {
        // HR rosters anyone (decided 2026-10-27).
        if ($this->rbac->can($request->user(), 'teams.edit')
            || $this->rbac->can($request->user(), 'attendance.reject')) {
            return true;
        }

        $rosterer = $this->employeeFor($request);

        return $this->rbac->can($request->user(), 'attendance.roster')
            && $rosterer !== null
            && Team::where('lead_id', $rosterer->id)
                ->whereHas('members', fn ($q) => $q->where('employees.id', $target->id))
                ->exists();
    }

    /**
     * The people this rosterer may put on a Sunday: everyone, for a Manager;
     * their own teams' members, for a Team Lead.
     *
     * @return Collection<int, Employee>
     */
    protected function rosterableEmployees(?Employee $rosterer, Request $request): Collection
    {
        if ($this->rbac->can($request->user(), 'teams.edit')
            || $this->rbac->can($request->user(), 'attendance.reject')) {
            return Employee::query()->with('user')->attends()->get()
                ->sortBy(fn (Employee $e) => $e->user?->name)->values();
        }

        if ($rosterer === null) {
            return collect();
        }

        return Employee::query()
            ->with('user')
            ->attends()
            ->whereHas('teams', fn ($q) => $q->where('teams.lead_id', $rosterer->id))
            ->get()
            ->sortBy(fn (Employee $e) => $e->user?->name)
            ->values();
    }

    /**
     * The next several Sundays, and any holiday among them - the only dates
     * worth offering the roster form, since a working day needs no roster at
     * all (the check-in route already covers it).
     *
     * @return list<array{date: string, label: string}>
     */
    protected function upcomingRosterableDates(int $weeks = 8): array
    {
        $dates = [];
        // The last week too - see ROSTER_BACK_DAYS.
        $cursor = Carbon::today()->subDays(self::ROSTER_BACK_DAYS);
        $end = Carbon::today()->addWeeks($weeks);
        $tracking = AttendancePolicy::startsOn();

        if ($tracking !== null && $tracking->greaterThan($cursor)) {
            $cursor = $tracking->copy();
        }

        while ($cursor->lessThanOrEqualTo($end)) {
            if (! AttendancePolicy::isWorkingDay($cursor)) {
                $holiday = AttendancePolicy::holidayOn($cursor);

                $dates[] = [
                    'date' => $cursor->toDateString(),
                    'label' => $cursor->format('D, d M Y').($holiday !== null ? ' - '.$holiday : '')
                        .($cursor->lessThan(Carbon::today()) ? ' (past)' : ''),
                ];
            }

            $cursor->addDay();
        }

        return $dates;
    }

    /**
     * Reject a record, with a reason.
     *
     * A correction applied after the fact, never a gate - see the head of this
     * class for why there is no approval anywhere in this module. The reason is
     * required because "rejected" with no explanation is the version somebody
     * has to come and ask about, and it is their attendance record.
     *
     * The times are not touched. A wrong record stays legible.
     */
    public function reject(Request $request, string $record): RedirectResponse
    {
        $found = $this->findRecord($record);
        $model = $found['model'];

        $actor = $this->employeeFor($request);

        if ($actor !== null && $model->employee_id === $actor->id) {
            // Nobody rejects their own record - the same rule, for the same
            // reason, as nobody approving their own leave (§2.6).
            throw ValidationException::withMessages([
                'reason' => 'You cannot reject your own attendance record.',
            ]);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        if ($model->rejected_at !== null) {
            return redirect()
                ->route('attendance.show', ['record' => $record])
                ->with('status', 'This record was already rejected.')
                ->with('status_tone', 'info');
        }

        $model->update([
            'rejected_at' => Carbon::now(),
            'rejected_by' => $actor?->id,
            'rejection_reason' => $data['reason'],
        ]);

        $this->audit->record(
            action: AuditLog::ATTENDANCE_REJECTED,
            actor: $request->user(),
            entityType: 'attendance',
            entityId: $record,
            before: 'recorded',
            after: 'Rejected: '.$data['reason'],
            request: $request,
        );

        return redirect()
            ->route('attendance.show', ['record' => $record])
            ->with('status', 'Record rejected.')
            ->with('status_tone', 'info');
    }

    /**
     * Undo a rejection somebody made in error.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * ONLY A PERSON'S REJECTION CAN BE LIFTED
     *
     * A day the ten-hour window closed on is not restorable: there is no flag
     * to lift, because the state is derived from a check-out that is still
     * missing. "Restoring" it would mean inventing the time somebody went home.
     * The honest answer is that the day is gone.
     *
     * The correction mechanism needs to be correctable, or it needs a
     * correction mechanism of its own.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function restore(Request $request, string $record): RedirectResponse
    {
        $found = $this->findRecord($record);
        $model = $found['model'];

        if ($model->rejected_at === null) {
            return redirect()
                ->route('attendance.show', ['record' => $record])
                ->with('status', 'This record is not rejected.')
                ->with('status_tone', 'info');
        }

        $reason = $model->rejection_reason;

        $model->update([
            'rejected_at' => null,
            'rejected_by' => null,
            'rejection_reason' => null,
        ]);

        $this->audit->record(
            action: AuditLog::ATTENDANCE_RESTORED,
            actor: $request->user(),
            entityType: 'attendance',
            entityId: $record,
            // The reason the rejection gave is carried into the entry that
            // undoes it: the log has to keep saying what was once claimed about
            // this day, even after the claim was withdrawn.
            before: 'Rejected: '.$reason,
            after: 'Rejection withdrawn',
            request: $request,
        );

        return redirect()
            ->route('attendance.show', ['record' => $record])
            ->with('status', 'Rejection withdrawn.')
            ->with('status_tone', 'success');
    }

    /**
     * POST /attendance/{record}/mark-present - a day somebody forgot to check
     * out of, counted as present (v1.2.1).
     *
     * Only a day the auto-reject window closed on, never your own, and with a
     * reason. The check-out stays missing - nobody types a time in - so the day
     * reads "Present (warning)" and carries no hours.
     */
    public function markPresent(Request $request, string $record): RedirectResponse
    {
        $found = $this->findRecord($record);
        $model = $found['model'];

        $actor = $this->employeeFor($request);

        if ($actor !== null && $model->employee_id === $actor->id) {
            throw ValidationException::withMessages([
                'present_reason' => 'You cannot mark your own attendance record.',
            ]);
        }

        if (! $found['auto_rejected'] || $model->rejected_at !== null) {
            return redirect()
                ->route('attendance.show', ['record' => $record])
                ->with('status', 'Only a day that was never checked out of can be marked present.')
                ->with('status_tone', 'info');
        }

        $data = $request->validate([
            'present_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'present_reason.required' => 'Say why this day counts.',
            'present_reason.min' => 'Say a little more - at least 10 characters.',
        ]);

        $model->update([
            'marked_present_at' => Carbon::now(),
            'marked_present_by' => $request->user()->id,
            'marked_present_reason' => $data['present_reason'],
        ]);

        $this->audit->record(
            action: AuditLog::ATTENDANCE_MARKED_PRESENT,
            actor: $request->user(),
            entityType: 'attendance',
            entityId: $record,
            before: 'Rejected: never checked out',
            after: 'Present (warning): '.$data['present_reason'],
            request: $request,
        );

        return redirect()
            ->route('attendance.show', ['record' => $record])
            ->with('status', 'Marked present, with a warning.')
            ->with('status_tone', 'success');
    }

    /* ══════════════════════════════════════════════════════════════════════
       A MISSED PAST DAY, ENTERED BY HR
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * GET /attendance/add - the form for filling in a day somebody missed.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * WHY THIS EXISTS, AND WHAT IT STILL REFUSES (decided 2026-10-27)
     *
     * Somebody who worked a day and could not check in - the system was down,
     * they were on site, it was before go-live - would otherwise carry an
     * absence nobody can correct. HR (`attendance.reject`) can now write that
     * day in, with times and a reason.
     *
     * It only CREATES a day that has no record. It never edits one: a recorded
     * time is still never changed, and a day left open is still not closed by
     * typing a check-out in. Nobody enters their own day, today or the future -
     * today belongs to the clock.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function createEntry(Request $request): Response
    {
        $actor = $this->employeeFor($request);

        return response()->view('attendance.add', [
            'activeNav' => 'attendance',
            'employeeChoices' => Employee::query()
                ->with('user')
                ->active()
                ->get()
                ->filter(fn (Employee $e) => $e->attendsWork() && $e->id !== $actor?->id)
                ->sortBy(fn (Employee $e) => $e->user?->name)
                ->values(),
            'chosenEmployee' => (string) $request->query('employee', ''),
            'chosenDate' => (string) $request->query('date', ''),
            'latest' => Carbon::yesterday()->toDateString(),
            'policy' => [
                'start' => AttendancePolicy::workStart(),
                'end' => AttendancePolicy::workEnd(),
            ],
        ]);
    }

    /**
     * POST /attendance/add
     */
    public function storeEntry(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'date' => ['required', 'date_format:Y-m-d', 'before:today'],
            'check_in' => ['required', 'date_format:H:i'],
            'check_out' => ['required', 'date_format:H:i', 'after:check_in'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'date.before' => 'Only a past day can be entered. Today is recorded by checking in.',
            'check_out.after' => 'The check-out has to be after the check-in.',
        ]);

        $employee = Employee::with('user')->findOrFail($data['employee_id']);
        $actor = $this->employeeFor($request);

        if ($actor !== null && $employee->id === $actor->id) {
            // The same rule as rejecting: nobody corrects their own record.
            throw ValidationException::withMessages([
                'employee_id' => 'You cannot enter your own attendance.',
            ]);
        }

        if (! $employee->attendsWork()) {
            throw ValidationException::withMessages([
                'employee_id' => 'This person does not record attendance.',
            ]);
        }

        $existing = AttendanceRecord::where('employee_id', $employee->id)
            ->whereDate('date', $data['date'])
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'date' => 'A record already exists for that day. Open it from the roll instead - '
                    .'a recorded time is never overwritten.',
            ]);
        }

        $record = AttendanceRecord::create([
            'employee_id' => $employee->id,
            'date' => $data['date'],
            'check_in' => $data['check_in'],
            'check_out' => $data['check_out'],
        ]);

        $reference = $record->fresh('employee.user')->reference();

        $this->audit->record(
            action: AuditLog::ATTENDANCE_ENTERED,
            actor: $request->user(),
            entityType: 'attendance',
            entityId: $reference,
            after: 'Entered by HR, '.$data['check_in'].'–'.$data['check_out'].': '.$data['reason'],
            request: $request,
        );

        $earned = $this->earnCompOffIfDue($request, $employee, $record->fresh());

        return redirect()
            ->route('attendance.show', ['record' => $reference])
            ->with('status', 'Day entered for '.$employee->user?->name.'.'
                .($earned ? ' A rostered day, worked in full - one comp-off added.' : ''))
            ->with('status_tone', 'success');
    }

    /**
     * The signed-in person's employment record.
     */
    protected function employeeFor(Request $request): ?Employee
    {
        return Employee::where('user_id', $request->user()?->id)->first();
    }

    /**
     * The same, refusing anybody who has none - or who is a freelancer.
     *
     * A Mentor and the owner hold no Employee base at all (§2.1), so they have
     * no attendance to record - and a clock button that half-worked for them
     * would be worse than one that says no. A freelancer HAS an employment
     * record but is paid against work, not time (decided 2026-09-11): no
     * attendance, no leave, no clock for them either.
     */
    protected function requireEmployee(Request $request): Employee
    {
        $employee = $this->employeeFor($request);

        abort_if($employee === null, 403);
        abort_unless($employee->attendsWork(), 403);

        return $employee;
    }

    /**
     * @return array<string, mixed>
     */
    protected function findRecord(string $reference): array
    {
        $found = AttendanceDirectory::find($reference);

        abort_if($found === null, 404);

        return $found;
    }

    /**
     * GET /attendance/{record} - one day, one person, and the rejection on it.
     */
    public function show(Request $request, string $record): Response
    {
        $found = $this->findRecord($record);

        $viewer = $this->employeeFor($request);
        $own = $viewer !== null && $found['model']->employee_id === $viewer->id;

        return response()->view('attendance.show', [
            'activeNav' => 'attendance',
            'record' => $found,
            'own' => $own,
            /*
             * Rejecting needs the permission AND not being your own record
             * (§2.6) - a control somebody can apply to themselves is not a
             * control. A day the ten-hour window already closed on is not
             * offered either: it is rejected, and rejecting it again does
             * nothing.
             */
            'canReject' => $this->rbac->can($request->user(), 'attendance.reject')
                && ! $own
                && ! $found['rejected'],
            // A rejection made in error has to be reversible, or the correction
            // mechanism needs a correction mechanism.
            //
            // An AUTO-rejection is not. There is no flag to lift - the state is
            // derived from a check-out that is still missing, so "restoring" it
            // would either do nothing or mean inventing the time. The honest
            // answer is that the day is gone.
            'canRestore' => $this->rbac->can($request->user(), 'attendance.reject')
                && ! $own
                && $found['rejected_at'] !== null
                && ! $found['auto_rejected'],
            // Except by marking it present (v1.2.1): the day counts, with a
            // warning, and the missing check-out stays missing.
            'canMarkPresent' => $this->rbac->can($request->user(), 'attendance.reject')
                && ! $own
                && $found['auto_rejected']
                && $found['rejected_at'] === null,
            // A day HR typed in looks like any other row; the log is what says
            // so, and the page has to say it too.
            'entered' => $this->audit->entriesFor('attendance', $record)
                ->firstWhere('action', AuditLog::ATTENDANCE_ENTERED),
            'policy' => [
                'start' => AttendancePolicy::workStart(),
                'end' => AttendancePolicy::workEnd(),
                'halfDay' => AttendancePolicy::halfDayHours(),
                'window' => AttendancePolicy::autoRejectAfterHours(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:60'],
            'state' => ['nullable', Rule::in(P::filterableStates())],
            // A date, and not a future one: there is no attendance to show for
            // a day that has not happened.
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        $search = trim($validated['q'] ?? '');

        return [
            'search' => $search,
            'department' => $validated['department'] ?? null,
            'state' => $validated['state'] ?? null,
            'date' => $validated['date'] ?? Carbon::today()->toDateString(),
            'filtered' => $search !== ''
                || ($validated['department'] ?? null) !== null
                || ($validated['state'] ?? null) !== null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function matching(Collection $rows, array $filters): Collection
    {
        return $rows
            ->when($filters['search'] !== '', fn (Collection $r) => $r->filter(
                fn (array $row) => str_contains(
                    mb_strtolower($row['employee_record']['name'].' '.$row['employee']),
                    mb_strtolower($filters['search'])
                )
            ))
            ->when($filters['department'], fn (Collection $r) => $r->filter(
                fn (array $row) => $row['employee_record']['department'] === $filters['department']
            ))
            ->when($filters['state'], fn (Collection $r) => $r->where('state', $filters['state']))
            // Alphabetical. A roll is read by looking for a name, and any other
            // order means scanning the whole list to find one person.
            ->sortBy(fn (array $row) => $row['employee_record']['name'])
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
