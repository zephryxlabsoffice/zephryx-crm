<?php

namespace App\Http\Controllers;

use App\Support\Chart;
use App\Support\Demo\DemoEmployees;
use App\Support\Demo\DemoTeams;
use App\Support\EmployeePresenter;
use App\Support\TeamPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Teams — the managing face (`/teams`), the personal face (`/teams/mine`) and
 * one team's overview (`/teams/{team}`). Foundation spec §12.1.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FRONT END ONLY. There is no `teams` table; rows come from
 * App\Support\Demo\DemoTeams, which returns nothing outside local + debug.
 *
 * Still to add with the backend: `teams.view` for the managing face, and the
 * §2.6 rules — a Team Lead may act within their own team only, and nobody may
 * act on someone who outranks them in the `work` domain. `/teams/mine` needs
 * no permission beyond being signed in, but must filter on the actual viewer.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class TeamController extends Controller
{
    protected const PER_PAGE = 8;

    protected const MEMBERS_PER_PAGE = 8;

    protected const DONUT_RADIUS = 57;

    /**
     * GET /teams — every team in the company.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(TeamPresenter::statusOptions())],
            'lead' => ['nullable', 'string', 'max:20'],
        ]);

        $search = trim($filters['q'] ?? '');
        $status = $filters['status'] ?? null;
        $lead = $filters['lead'] ?? null;

        $matches = DemoTeams::all()
            ->when($search !== '', fn (Collection $rows) => $rows->filter(
                fn (array $team) => str_contains(
                    mb_strtolower($team['name'].' '.$team['purpose'].' '.$team['id']),
                    mb_strtolower($search)
                )
            ))
            ->when($status, fn (Collection $rows) => $rows->where('status', $status))
            ->when($lead, fn (Collection $rows) => $rows->where('lead', $lead))
            ->values();

        return response()->view('teams.index', [
            'activeNav' => 'teams',
            'teams' => $this->paginate($matches->map(fn (array $t) => $this->decorate($t)), $request, self::PER_PAGE),
            'search' => $search,
            'status' => $status,
            'lead' => $lead,
            'filtered' => $search !== '' || $status !== null || $lead !== null,
            'leads' => $this->leadOptions(),
            'stats' => DemoTeams::stats(),
            'activity' => DemoTeams::activity(),
        ]);
    }

    /**
     * GET /teams/mine — the teams the signed-in person belongs to.
     *
     * A separate route rather than a filter on the managing face: §12.1 keeps
     * the personal and managing views distinct, and this one is reachable by
     * anyone regardless of `teams.view`.
     */
    public function mine(Request $request): Response
    {
        $mine = DemoTeams::mine();

        return response()->view('teams.mine', [
            'activeNav' => 'teams',
            'teams' => $this->paginate($mine->map(fn (array $t) => $this->decorate($t)), $request, self::PER_PAGE),
            'stats' => DemoTeams::stats($mine),
        ]);
    }

    /**
     * GET /teams/{team} — one team and its members.
     */
    public function show(Request $request, string $team): Response
    {
        $record = DemoTeams::find($team);

        abort_if($record === null, 404);

        $validated = $request->validate([
            'tab' => ['nullable', Rule::in(['all', 'by_department', 'on_leave', 'inactive'])],
            'q' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:60'],
        ]);

        $tab = $validated['tab'] ?? 'all';
        $search = trim($validated['q'] ?? '');
        $department = $validated['department'] ?? null;

        $allMembers = DemoTeams::membersOf($record);

        $members = collect($allMembers)
            ->when($tab === 'on_leave', fn (Collection $rows) => $rows->where('status', 'on_leave'))
            ->when($tab === 'inactive', fn (Collection $rows) => $rows->where('status', 'inactive'))
            ->when($search !== '', fn (Collection $rows) => $rows->filter(
                fn (array $m) => str_contains(mb_strtolower($m['name'].' '.$m['designation'].' '.$m['email']), mb_strtolower($search))
            ))
            ->when($department, fn (Collection $rows) => $rows->where('department', $department))
            // "By department" is a grouping, not a filter: it orders the rows so
            // the view can insert a heading whenever the department changes.
            ->when($tab === 'by_department', fn (Collection $rows) => $rows->sortBy('department'))
            ->values();

        $breakdown = Chart::breakdown($allMembers, 'department');

        return response()->view('teams.show', [
            'activeNav' => 'teams',
            'team' => $this->decorate($record),
            'members' => $this->paginate($members, $request, self::MEMBERS_PER_PAGE),
            'grouped' => $tab === 'by_department',
            'tab' => $tab,
            'tabCounts' => [
                'all' => count($allMembers),
                'on_leave' => collect($allMembers)->where('status', 'on_leave')->count(),
                'inactive' => collect($allMembers)->where('status', 'inactive')->count(),
            ],
            'search' => $search,
            'department' => $department,
            'filtered' => $search !== '' || $department !== null,
            'departments' => collect($allMembers)->pluck('department')->unique()->sort()->values()->all(),
            'breakdown' => $breakdown,
            'circumference' => 2 * M_PI * self::DONUT_RADIUS,
            'donutRadius' => self::DONUT_RADIUS,
            'averageTenure' => TeamPresenter::averageTenure($allMembers),
            'age' => TeamPresenter::age($record['created']),
        ]);
    }

    /**
     * Attach the things every team view needs: the lead's record and a member
     * count. Done once here rather than in three templates.
     *
     * @param  array<string, mixed>  $team
     * @return array<string, mixed>
     */
    protected function decorate(array $team): array
    {
        $lead = $team['lead']
            ? DemoEmployees::all()->firstWhere('user_id', $team['lead'])
            : null;

        return $team + [
            'lead_record' => $lead,
            'member_count' => count($team['members']),
        ];
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    protected function leadOptions(): array
    {
        $employees = DemoEmployees::all()->keyBy('user_id');

        return DemoTeams::all()
            ->pluck('lead')
            ->filter()
            ->unique()
            ->map(fn (string $id) => ['id' => $id, 'name' => $employees[$id]['name'] ?? $id])
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function paginate(Collection $rows, Request $request, int $perPage): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            items: $rows->forPage($page, $perPage)->values(),
            total: $rows->count(),
            perPage: $perPage,
            currentPage: $page,
            options: ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
