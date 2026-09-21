<?php

namespace App\Support\Dashboard;

use App\Models\Employee;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use App\Support\AnnouncementDirectory;
use App\Support\AttendanceDirectory;
use App\Support\AttendancePolicy;
use App\Support\AttendancePresenter;
use App\Support\ClientDirectory;
use App\Support\DashboardPresenter as P;
use App\Support\EmployeeDirectory;
use App\Support\InvoiceDirectory;
use App\Support\InvoicePresenter;
use App\Support\LeaveDirectory;
use App\Support\LeavePolicy;
use App\Support\LeavePresenter;
use App\Support\MeetingDirectory;
use App\Support\Milestones;
use App\Support\ProjectDirectory;
use App\Support\SalaryDirectory;
use App\Support\SalaryPresenter;
use App\Support\TaskDirectory;
use App\Support\TeamDirectory;
use App\Support\TicketDirectory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What each dashboard widget shows.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * NOTHING IS ASSEMBLED FOR A WIDGET THAT IS NOT BEING SHOWN
 *
 * The controller filters first and hydrates second: it asks DashboardComposer
 * which widgets survived the permission check, then calls in here only for
 * those. A widget somebody cannot see never has its figures read.
 *
 * This is the difference between filtering and hiding. A page that assembles
 * everything and then omits some markup has already put payroll totals into the
 * process that renders somebody's dashboard; one broken @if, one debug dump,
 * one cache of the view model, and they are on the page. Reading nothing has no
 * such failure mode.
 *
 * It matters more against a database than it did against a fixture: every one
 * of these methods is now a query, and the ones this page does not run are
 * queries nobody pays for either.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * THE VIEWER IS THE EMPLOYMENT RECORD, AND IT CAN BE NULL
 *
 * It used to be a staff-id string standing in for the session. It is an
 * `?Employee` now, resolved once by the controller — and nullable on purpose: a
 * Mentor and the owner hold no Employee base (§2.1), so "my tasks" and "my
 * leave" have no answer for them rather than an empty one. Every personal
 * method below draws that case.
 */
class DashboardData
{
    /**
     * One KPI tile, or null when the tile has nothing to report.
     *
     * @return array{label: string, value: string, sub: string, tone: string, route: ?string}|null
     */
    public static function kpi(string $key, ?Employee $viewer): ?array
    {
        return match ($key) {
            'my-open-work' => self::openWorkTile($viewer),
            'clients' => self::clientsTile(),
            'projects' => self::projectsTile(),
            'headcount' => self::headcountTile(),
            'leave-pending' => self::leaveTile(),
            'attendance' => self::attendanceTile(),
            'tickets' => self::ticketsTile(),
            'receivables' => self::receivablesTile(),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected static function openWorkTile(?Employee $viewer): array
    {
        $open = self::myTaskQuery($viewer)->count();
        $late = self::myTaskQuery($viewer)->overdue()->count();

        return self::tile(
            label: 'On your plate',
            value: $open,
            // Overdue is named rather than folded into the headline: five tasks
            // with none late is a normal week, five with three late is not, and
            // one number cannot say both.
            sub: $late > 0 ? $late.' overdue' : 'Nothing overdue',
            tone: $late > 0 ? 'tone-warn' : 'tone-soft',
            route: 'tasks.mine',
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected static function clientsTile(): array
    {
        $clients = ClientDirectory::stats();

        return self::tile(
            label: 'Clients',
            value: $clients['total'],
            sub: $clients['active'].' active',
            tone: 'tone-accent',
            route: 'clients.index',
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected static function projectsTile(): array
    {
        $projects = ProjectDirectory::stats();

        return self::tile(
            label: 'Active projects',
            value: $projects['active'],
            sub: $projects['overdue'] > 0
                ? $projects['overdue'].' past the deadline'
                : 'All inside their deadlines',
            tone: $projects['overdue'] > 0 ? 'tone-warn' : 'tone-soft',
            route: 'projects.index',
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected static function headcountTile(): array
    {
        $people = EmployeeDirectory::stats();

        return self::tile(
            label: 'Employees',
            // "Active", not "total": this is a headcount, and counting somebody
            // who has left in it is how an estimate ends up wrong by a person.
            value: $people['active'],
            sub: $people['new_this_month'] > 0
                ? $people['new_this_month'].' joined this month'
                : 'Nobody new this month',
            tone: 'tone-alt',
            route: 'employees.index',
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected static function leaveTile(): array
    {
        $pending = LeaveRequest::query()->pending()->count();

        return self::tile(
            label: 'Leave to decide',
            value: $pending,
            sub: $pending === 0 ? 'Nothing waiting' : 'Waiting on a decision',
            tone: $pending === 0 ? 'tone-soft' : 'tone-warn',
            route: 'leave.index',
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected static function attendanceTile(): array
    {
        $roll = AttendanceDirectory::stats(AttendanceDirectory::forDate(Carbon::today()));

        return self::tile(
            label: 'In today',
            value: $roll[AttendancePresenter::PRESENT] + $roll[AttendancePresenter::HALF_DAY],
            // Out of headcount, stated, because "9" alone means nothing without
            // knowing whether the company is ten people or ninety.
            sub: 'Of '.$roll['headcount'].' on the roll',
            tone: 'tone-soft',
            route: 'attendance.index',
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected static function ticketsTile(): array
    {
        $tickets = TicketDirectory::stats();

        return self::tile(
            label: 'Open tickets',
            value: $tickets['total'] - $tickets['resolved'],
            sub: $tickets['escalated'] > 0
                ? $tickets['escalated'].' escalated'
                : 'None escalated',
            tone: $tickets['escalated'] > 0 ? 'tone-warn' : 'tone-soft',
            route: 'tickets.index',
        );
    }

    /**
     * Money, and the one tile that cannot always be a single number.
     *
     * A mixed-currency set has no total without an exchange rate this system
     * does not have — see App\Support\MoneyBag. `headline()` gives the dominant
     * currency and names the others rather than inventing a conversion.
     *
     * @return array<string, mixed>
     */
    protected static function receivablesTile(): array
    {
        $stats = InvoiceDirectory::stats(self::invoices());
        $money = $stats['outstanding']->headline();

        return self::tile(
            label: 'Outstanding',
            value: $money['lead'],
            // The other currencies displace the overdue count when there are
            // any: a figure the tile is withholding is worse than a count the
            // card below repeats.
            sub: $money['note'] !== ''
                ? $money['note']
                : ($stats['overdue'] > 0 ? P::count($stats['overdue'], 'invoice').' overdue' : 'Nothing overdue'),
            tone: $stats['overdue'] > 0 ? 'tone-warn' : 'tone-soft',
            route: 'invoices.index',
        );
    }

    /**
     * @return array{label: string, value: string, sub: string, tone: string, route: ?string}
     */
    protected static function tile(string $label, int|string $value, string $sub, string $tone, ?string $route): array
    {
        return [
            'label' => $label,
            'value' => (string) $value,
            'sub' => $sub,
            'tone' => $tone,
            'route' => $route,
        ];
    }

    /**
     * One card widget's payload.
     *
     * @return array<string, mixed>
     */
    public static function widget(string $key, ?Employee $viewer): array
    {
        return match ($key) {

            'punch' => self::punch($viewer),
            'my-meetings' => self::myMeetings($viewer),
            'my-leave' => self::myLeave($viewer),
            'my-salary' => self::mySalary($viewer),
            'announcements' => self::announcements(),
            'milestones' => self::milestones(),

            'my-tasks' => self::myTasks($viewer),
            'leave-approvals' => self::leaveApprovals($viewer),
            'attendance-open' => self::attendanceOpen(),
            'ticket-queue' => self::ticketQueue(),
            'meeting-requests' => self::meetingRequests(),
            'projects' => self::projects(),
            'my-team' => self::myTeam($viewer),
            'receivables' => self::receivables(),
            'payroll' => self::payroll(),

            default => [],
        };
    }

    /* ---------------------------------------------------------------------
     | The rail — the person
     --------------------------------------------------------------------- */

    /**
     * @return array<string, mixed>
     */
    protected static function punch(?Employee $viewer): array
    {
        $today = AttendanceDirectory::today($viewer);

        $onLeave = $viewer !== null && in_array(
            Carbon::today()->toDateString(),
            AttendanceDirectory::leaveDates($viewer->id),
            true,
        );

        return [
            'today' => $today,
            'state' => AttendancePolicy::evaluate(Carbon::today(), $today, $onLeave),
            'checkedIn' => $today !== null && $today['check_in'] !== null,
            'checkedOut' => $today !== null && $today['check_out'] !== null,
            'window' => (int) AttendancePolicy::autoRejectAfterHours(),
            // A day the office is shut is not a day somebody failed to check
            // in on, and the card must not imply otherwise.
            'workingDay' => AttendancePolicy::isWorkingDay(Carbon::today()),
            'holiday' => AttendancePolicy::holidayOn(Carbon::today()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function myMeetings(?Employee $viewer): array
    {
        /*
         * The viewer's own meetings, not the company's. A card headed "Your
         * meetings" listing one somebody is not invited to is worse than an
         * empty card.
         *
         * Attendance is a row in `meeting_attendees` keyed on the USER, not the
         * employment record — a meeting is something an account is invited to —
         * so this is the one personal widget that resolves back to the user.
         */
        $user = $viewer?->user;

        if ($user === null) {
            return ['next' => null, 'rest' => collect(), 'total' => 0];
        }

        $mine = MeetingDirectory::rows(
            MeetingDirectory::query()
                ->upcoming()
                ->whereHas('attendees', fn ($q) => $q->where('user_id', $user->id)),
            $user,
        );

        return [
            'next' => $mine->first(),
            'rest' => $mine->slice(1, 2)->values(),
            'total' => $mine->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function myLeave(?Employee $viewer): array
    {
        $mine = LeaveDirectory::forEmployee($viewer);

        return [
            'balance' => LeavePolicy::balance($mine),
            'pending' => $mine->where('status', LeavePresenter::PENDING)->count(),
            // The next approved day off, which is the thing somebody opens this
            // card to check.
            'next' => $mine
                ->where('status', LeavePresenter::APPROVED)
                ->filter(fn (array $r) => $r['from'] >= Carbon::today()->toDateString())
                ->sortBy('from')
                ->first(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function mySalary(?Employee $viewer): array
    {
        $latest = SalaryDirectory::latestFor($viewer);

        /*
         * The last payslip ON FILE, which is not the same as the last month
         * that has passed. Mid-run there is a record for the current month with
         * no payslip added yet, and reporting that as this month's pay would
         * put a blank where somebody expects a figure.
         */
        $onFile = SalaryDirectory::forEmployee($viewer)
            ->first(fn (array $r) => SalaryPresenter::statusOf($r) !== SalaryPresenter::NO_PAYSLIP);

        return [
            'current' => $latest,
            'latest' => $onFile,
            'status' => $latest !== null ? SalaryPresenter::statusOf($latest) : null,
            'banked' => SalaryDirectory::banked($viewer),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function announcements(): array
    {
        $board = AnnouncementDirectory::board();

        return [
            'items' => $board->take(3)->values(),
            'total' => $board->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function milestones(): array
    {
        /*
         * Computed from employee records on every request, never stored — a
         * stored milestone is wrong the following year and survives somebody
         * opting out of their own being announced. Milestones::upcoming already
         * honours both the opt-out and the inactive check.
         */
        return ['items' => array_slice(Milestones::upcoming(), 0, 4)];
    }

    /* ---------------------------------------------------------------------
     | The main column — the work
     --------------------------------------------------------------------- */

    /**
     * @return array<string, mixed>
     */
    protected static function myTasks(?Employee $viewer): array
    {
        return [
            // Soonest first, and overdue sorts to the top — that is the order
            // somebody works in. TaskDirectory::query already orders by due
            // date, so this is a limit rather than a re-sort.
            'items' => self::myTaskQuery($viewer)->limit(5)->get()
                ->map(fn (Task $t) => TaskDirectory::row($t)),
            'total' => self::myTaskQuery($viewer)->count(),
            'overdue' => self::myTaskQuery($viewer)->overdue()->count(),
        ];
    }

    /**
     * The viewer's own open tasks.
     *
     * A fresh builder each time rather than one cloned around, because two of
     * the three uses add a scope to it and a shared builder is how a count ends
     * up filtered by whatever the previous caller wanted.
     *
     * `assignee_id` of 0 for somebody with no employment record: an impossible
     * id rather than a skipped `where`, so the query returns nothing instead of
     * everything. The same shape TaskController::mine uses.
     *
     * @return Builder<Task>
     */
    protected static function myTaskQuery(?Employee $viewer)
    {
        return TaskDirectory::query()
            ->where('assignee_id', $viewer?->id ?? 0)
            ->whereNot('status', 'completed');
    }

    /**
     * @return array<string, mixed>
     */
    protected static function leaveApprovals(?Employee $viewer): array
    {
        /*
         * NOBODY DECIDES THEIR OWN REQUEST (§2.6) — owner included. Excluded
         * here as well as in the write, because a queue that lists your own
         * request next to an Approve button is an invitation to find out the
         * hard way that the server says no.
         */
        $queue = LeaveDirectory::query()
            ->pending()
            ->when($viewer !== null, fn ($q) => $q->whereNot('employee_id', $viewer->id))
            ->orderBy('from_date');

        return [
            'items' => $queue->limit(4)->get()->map(fn (LeaveRequest $r) => LeaveDirectory::row($r)),
            'total' => (clone $queue)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function attendanceOpen(): array
    {
        $open = AttendanceDirectory::missingCheckOuts();

        return [
            'items' => $open->take(4)->values(),
            'total' => $open->count(),
            'window' => (int) AttendancePolicy::autoRejectAfterHours(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function ticketQueue(): array
    {
        $escalated = TicketDirectory::query()->escalated()->get()
            ->map(fn (Ticket $t) => TicketDirectory::row($t));

        $unassigned = TicketDirectory::query()->unassigned()->get()
            ->map(fn (Ticket $t) => TicketDirectory::row($t));

        return [
            // Escalated first: an escalation is somebody saying the normal
            // route did not work, and it ages worse than an unpicked ticket.
            'items' => $escalated->concat($unassigned)->take(4)->values(),
            'escalated' => $escalated->count(),
            'unassigned' => $unassigned->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function meetingRequests(): array
    {
        $requested = MeetingDirectory::rows(MeetingDirectory::query()->requested(), null);

        return [
            'items' => $requested->take(3)->values(),
            'total' => $requested->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function projects(): array
    {
        return [
            'stats' => ProjectDirectory::stats(),
            'items' => ProjectDirectory::upcoming(4),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function myTeam(?Employee $viewer): array
    {
        if ($viewer === null) {
            return ['teams' => collect(), 'total' => 0, 'people' => collect()];
        }

        $teams = $viewer->teams()->with(['lead.user', 'lead.designation', 'members.user', 'members.designation'])->get();

        return [
            'teams' => $teams->take(3)->map(fn (Team $team) => TeamDirectory::row($team))->values(),
            'total' => $teams->count(),
            /*
             * The people, deduplicated across teams — somebody on three teams
             * is one colleague, not three — and never the viewer. A card headed
             * "who you work with" that lists you is a card nobody trusts the
             * rest of.
             */
            'people' => $teams
                ->flatMap(fn (Team $team) => $team->members)
                ->unique('id')
                ->reject(fn (Employee $member) => $member->id === $viewer->id)
                ->map(fn (Employee $member) => EmployeeDirectory::row($member))
                ->take(6)
                ->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function receivables(): array
    {
        $invoices = self::invoices();

        return [
            'stats' => InvoiceDirectory::stats($invoices),
            // Overdue only. An invoice inside its terms is not news; one past
            // them is the entire reason to look at this card.
            'items' => $invoices
                ->filter(fn (array $i) => InvoicePresenter::statusOf($i) === InvoicePresenter::OVERDUE)
                // Longest overdue first — the one that has been ignored the
                // longest is the one worth a call.
                ->sortBy('due_date')
                ->take(4)
                ->values(),
        ];
    }

    /**
     * Every invoice, as rows.
     *
     * Read whole rather than counted in SQL because the figures this feeds are
     * money in several currencies, and MoneyBag has to see each amount to
     * decide whether a total exists at all — a `SUM()` over mixed currencies is
     * exactly the invented conversion §9 refuses.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected static function invoices()
    {
        return Invoice::query()
            ->with(['client', 'project', 'payments'])
            ->get()
            ->map(fn (Invoice $i) => $i->toRecordArray());
    }

    /**
     * @return array<string, mixed>
     */
    protected static function payroll(): array
    {
        $period = SalaryDirectory::currentPeriod();
        $records = SalaryDirectory::forPeriod($period);

        return [
            'period' => $period,
            'stats' => SalaryDirectory::stats($records),
            // People who cannot be paid even if somebody presses the button —
            // no bank details on file. Worth surfacing before payroll day, not
            // during it.
            'unbanked' => SalaryDirectory::withoutBanking()->count(),
        ];
    }
}
