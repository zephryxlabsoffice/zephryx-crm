<?php

namespace App\Support\Dashboard;

use App\Support\AttendancePolicy;
use App\Support\AttendancePresenter;
use App\Support\DashboardPresenter as P;
use App\Support\Demo\DemoAnnouncements;
use App\Support\Demo\DemoAttendance;
use App\Support\Demo\DemoClients;
use App\Support\Demo\DemoEmployees;
use App\Support\Demo\DemoInvoices;
use App\Support\Demo\DemoLeave;
use App\Support\Demo\DemoMeetings;
use App\Support\Demo\DemoProjects;
use App\Support\Demo\DemoSalaries;
use App\Support\Demo\DemoTasks;
use App\Support\Demo\DemoTeams;
use App\Support\Demo\DemoTickets;
use App\Support\InvoicePresenter;
use App\Support\LeavePolicy;
use App\Support\LeavePresenter;
use App\Support\Milestones;
use App\Support\SalaryPresenter;
use Illuminate\Support\Carbon;

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
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Everything here reads the Demo sources, which return empty outside local +
 * debug. A deployed dashboard therefore shows its empty states rather than
 * invented activity — see the head of any Demo class.
 *
 * The `$viewer` argument stands in for the session until authentication lands.
 * It is threaded through explicitly rather than read from a constant inside
 * each method so that swapping it for `auth()->id()` is one change in the
 * controller, not thirty in here.
 */
class DashboardData
{
    /**
     * One KPI tile, or null when the tile has nothing to report.
     *
     * @return array{label: string, value: string, sub: string, tone: string, route: ?string}|null
     */
    public static function kpi(string $key, string $viewer): ?array
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
    protected static function openWorkTile(string $viewer): array
    {
        $open = DemoTasks::mine($viewer)->where('status', '!=', 'completed');
        $late = $open->where('due_in', '<', 0)->count();

        return self::tile(
            label: 'On your plate',
            value: $open->count(),
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
        $clients = DemoClients::stats();

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
        $projects = DemoProjects::stats();

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
        $people = DemoEmployees::stats();

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
        $pending = DemoLeave::pending()->count();

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
        $roll = DemoAttendance::stats(DemoAttendance::forDate(Carbon::today()));

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
        $tickets = DemoTickets::stats();

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
        $stats = DemoInvoices::stats();
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
    public static function widget(string $key, string $viewer): array
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
    protected static function punch(string $viewer): array
    {
        $today = DemoAttendance::today($viewer);
        $onLeave = in_array(Carbon::today()->toDateString(), DemoAttendance::leaveDates($viewer), true);

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
    protected static function myMeetings(string $viewer): array
    {
        /*
         * The viewer's own meetings, not the company's. A card headed "Your
         * meetings" listing one somebody is not invited to is worse than an
         * empty card — see DemoMeetings::nextFor, which made the same call.
         */
        $mine = DemoMeetings::upcoming()
            ->filter(fn (array $m) => DemoMeetings::isAttendee($m, $viewer))
            ->values();

        return [
            'next' => $mine->first(),
            'rest' => $mine->slice(1, 2)->values(),
            'total' => $mine->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function myLeave(string $viewer): array
    {
        $mine = DemoLeave::forEmployee($viewer);

        return [
            'balance' => LeavePolicy::balance($mine),
            'pending' => $mine->where('status', LeavePresenter::PENDING)->count(),
            // The next approved day off, which is the thing somebody opens this
            // card to check. `from` is a resolved date by the time it gets
            // here, not the offset it is written as in the demo source.
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
    protected static function mySalary(string $viewer): array
    {
        $latest = DemoSalaries::latestFor($viewer);

        /*
         * The last payslip ON FILE, which is not the same as the last month
         * that has passed. Mid-run there is a record for the current month with
         * no payslip added yet, and reporting that as this month's pay would
         * put a blank where somebody expects a figure.
         */
        $onFile = DemoSalaries::forEmployee($viewer)
            ->first(fn (array $r) => SalaryPresenter::statusOf($r) !== SalaryPresenter::NO_PAYSLIP);

        return [
            'current' => $latest,
            'latest' => $onFile,
            'status' => $latest !== null ? SalaryPresenter::statusOf($latest) : null,
            'banked' => DemoSalaries::banked($viewer),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function announcements(): array
    {
        $board = DemoAnnouncements::board();

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
    protected static function myTasks(string $viewer): array
    {
        $mine = DemoTasks::mine($viewer)->where('status', '!=', 'completed');

        return [
            // Soonest first, and overdue sorts to the top because `due_in` is
            // negative on a late task. That is the order somebody works in.
            'items' => $mine->sortBy('due_in')->take(5)->values(),
            'total' => $mine->count(),
            'overdue' => $mine->where('due_in', '<', 0)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function leaveApprovals(string $viewer): array
    {
        /*
         * NOBODY DECIDES THEIR OWN REQUEST (§2.6) — owner included. Excluded
         * here as well as in the write, because a queue that lists your own
         * request next to an Approve button is an invitation to find out the
         * hard way that the server says no.
         */
        $queue = DemoLeave::pending()
            ->reject(fn (array $r) => $r['employee'] === $viewer)
            ->sortBy('from')
            ->values();

        // Each row already carries its `employee_record` from DemoLeave.
        return [
            'items' => $queue->take(4)->values(),
            'total' => $queue->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function attendanceOpen(): array
    {
        $open = DemoAttendance::missingCheckOuts();

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
        $unassigned = DemoTickets::unassigned();
        $escalated = DemoTickets::escalated();

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
        $requested = DemoMeetings::requested();

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
            'stats' => DemoProjects::stats(),
            'items' => DemoProjects::upcoming(4),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function myTeam(string $viewer): array
    {
        $teams = DemoTeams::mine($viewer);
        $employees = DemoEmployees::all()->keyBy('user_id');

        return [
            'teams' => $teams->take(3)->map(fn (array $team) => $team + [
                'lead_record' => $team['lead'] ? $employees->get($team['lead']) : null,
                'member_count' => count($team['members']),
            ])->values(),
            'total' => $teams->count(),
            // The people, deduplicated across teams — somebody on three teams
            // is one colleague, not three.
            'people' => $teams
                ->flatMap(fn (array $team) => $team['members'])
                ->unique()
                ->reject(fn (string $id) => $id === $viewer)
                ->map(fn (string $id) => $employees->get($id))
                ->filter()
                ->take(6)
                ->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function receivables(): array
    {
        $invoices = DemoInvoices::all();

        return [
            'stats' => DemoInvoices::stats($invoices),
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
     * @return array<string, mixed>
     */
    protected static function payroll(): array
    {
        $period = DemoSalaries::currentPeriod();
        $records = DemoSalaries::forPeriod($period);

        return [
            'period' => $period,
            'stats' => DemoSalaries::stats($records),
            // People who cannot be paid even if somebody presses the button —
            // no bank details on file. Worth surfacing before payroll day, not
            // during it.
            'unbanked' => DemoSalaries::withoutBanking()->count(),
        ];
    }
}
