<?php

namespace App\Http\Controllers;

use App\Support\AttendancePolicy;
use App\Support\AttendancePresenter as P;
use App\Support\Demo\DemoAttendance;
use App\Support\Demo\DemoEmployees;
use App\Support\Holidays;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Attendance — checking in, checking out, and keeping the record honest.
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
 *   - Rejection is the exception. Records are wrong occasionally — a duplicate
 *     from a shared login, a door reader that double-fires — and those are the
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
 * 2. CHECKING IN IS IDEMPOTENT PER DAY. One person, one date, one record —
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
 *    rejected because the clock passed it, not because something stamped it —
 *    see AttendancePolicy::autoRejected. Do not add a nightly task to write
 *    that flag: it would be a second source of truth, and the first night it
 *    failed to run, a hundred days would quietly count as present.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class AttendanceController extends Controller
{
    protected const PER_PAGE = 10;

    /**
     * GET /attendance — one day's roll across the company.
     */
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $date = $filters['date'];

        $roll = DemoAttendance::forDate($date);
        $stats = DemoAttendance::stats($roll);

        return response()->view('attendance.index', [
            'activeNav' => 'attendance',
            'rows' => $this->paginate($this->matching($roll, $filters), $request),
            'stats' => $stats,
            'breakdown' => P::breakdown($stats, $stats['headcount']),
            // Days somebody checked into and never out of. The one thing on
            // this page that is actually actionable.
            'openRecords' => DemoAttendance::missingCheckOuts(),
            'departments' => DemoEmployees::all()->pluck('department')->unique()->sort()->values()->all(),
            'isToday' => Carbon::parse($date)->isToday(),
            'workingDay' => AttendancePolicy::isWorkingDay($date),
            'holiday' => AttendancePolicy::holidayOn($date),
            // How many people turned up on a day the board says the office was
            // shut, and whether that is enough to doubt the holiday rather than
            // admire the dedication. See AttendancePresenter::holidayLooksWrong.
            'holidayWorked' => $roll->whereNotNull('id')->count(),
            'holidayAnnouncement' => Holidays::announcementOn($date),
        ] + $filters);
    }

    /**
     * GET /attendance/mine — the signed-in person's own attendance.
     */
    public function mine(Request $request): Response
    {
        $viewer = DemoAttendance::VIEWER;

        $month = P::month($request->validate([
            'month' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ])['month'] ?? null);

        $records = DemoAttendance::forEmployee($viewer);
        $leaveDates = DemoAttendance::leaveDates($viewer);

        $today = DemoAttendance::today($viewer);
        $summary = AttendancePolicy::monthSummary($month, $records, $leaveDates);

        return response()->view('attendance.mine', [
            'activeNav' => 'attendance',
            'month' => $month,
            // The arrows are links, not script. Forward is capped at the
            // current month: there is nothing to show in November.
            'previousMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->startOfMonth()->isAfter(Carbon::today()->startOfMonth())
                ? null
                : $month->copy()->addMonth()->format('Y-m'),
            'calendar' => P::calendar($month, $records, $leaveDates),
            'summary' => $summary,
            'breakdown' => P::breakdown($summary, (int) $summary['days_counted']),
            // The month before, so the KPI tiles can say "3 fewer than last
            // month" instead of the handover's hardcoded "12% vs last month".
            'previousSummary' => AttendancePolicy::monthSummary($month->copy()->subMonth(), $records, $leaveDates),
            'history' => $this->paginate($records->values(), $request),
            'today' => $today,
            'todayState' => AttendancePolicy::evaluate(
                Carbon::today(),
                $today,
                in_array(Carbon::today()->toDateString(), $leaveDates, true),
            ),
            // TODO (backend phase): `attendance.view.all`. This page is reached
            // from the roll, so it needs a way back — but only for the people
            // who could have come from there.
            'canTrack' => true,
        ]);
    }

    /**
     * GET /attendance/{record} — one day, one person, and the rejection on it.
     */
    public function show(string $record): Response
    {
        $found = DemoAttendance::find($record);

        abort_if($found === null, 404);

        $viewer = DemoAttendance::VIEWER;
        $own = $found['employee'] === $viewer;

        return response()->view('attendance.show', [
            'activeNav' => 'attendance',
            'record' => $found,
            'own' => $own,
            // Nobody rejects their own record — the same rule, for the same
            // reason, as nobody approving their own leave. Stubbed here; the
            // real check lands with the RBAC engine.
            // TODO (backend phase): gate on `attendance.reject` (§2.6).
            //
            // A day the ten-hour window already closed on is not offered
            // either: it is rejected, and rejecting it again does nothing.
            'canReject' => ! $own && ! $found['rejected'],
            // A rejection made in error has to be reversible, or the correction
            // mechanism needs a correction mechanism.
            //
            // An AUTO-rejection is not. There is no flag to lift — the state is
            // derived from a check-out that is still missing, so "restoring" it
            // would either do nothing or mean inventing the time. The honest
            // answer is that the day is gone.
            'canRestore' => ! $own && $found['rejected_at'] !== null && ! $found['auto_rejected'],
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
