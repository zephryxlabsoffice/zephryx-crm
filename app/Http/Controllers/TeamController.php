<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Team;
use App\Support\Audit\AuditLog;
use App\Support\Chart;
use App\Support\Rbac\Rbac;
use App\Support\TeamDirectory;
use App\Support\TeamPresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Teams — the managing face (`/teams`), the personal face (`/teams/mine`) and
 * one team's overview (`/teams/{team}`). Foundation spec §12.1.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TWO KINDS OF AUTHORITY, AND THEY ARE NOT THE SAME CHECK
 *
 * The permission (§5) says what somebody may do at all. The §2.6 rule says who
 * they may do it to: a Team Lead acts within their own team and no further.
 * Both are enforced — the permission on the route, the ownership in `authorise`
 * below — because a Team Lead holding `teams.members` must not be able to
 * reassign somebody else's team by typing its reference into the URL.
 *
 * `/teams/mine` carries no permission beyond being signed in, and filters on
 * the viewer's own employment record rather than on anything they can pass.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class TeamController extends Controller
{
    protected const PER_PAGE = 8;

    protected const MEMBERS_PER_PAGE = 8;

    protected const DONUT_RADIUS = 57;

    public function __construct(protected Rbac $rbac, protected AuditLog $audit)
    {
    }

    /**
     * GET /teams — every team in the company.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(Team::STATUSES)],
            'lead' => ['nullable', 'string', 'max:32'],
        ]);

        $search = trim($filters['q'] ?? '');
        $status = $filters['status'] ?? null;
        $lead = $filters['lead'] ?? null;

        $query = TeamDirectory::query($search !== '' ? $search : null, $status, $lead);

        return response()->view('teams.index', [
            'activeNav' => 'teams',
            'teams' => $this->paginate($query, self::PER_PAGE),
            'search' => $search,
            'status' => $status,
            'lead' => $lead,
            'filtered' => $search !== '' || $status !== null || $lead !== null,
            'leads' => TeamDirectory::leadOptions(),
            'stats' => TeamDirectory::stats(),
            'activity' => TeamDirectory::activity(),
            'mayCreate' => $this->rbac->can($request->user(), 'teams.create'),
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
        $employee = $this->employeeFor($request);

        /*
         * Scoped by the viewer's own employment id, taken from the session.
         * There is no parameter here for anybody to change to somebody else's
         * — the same reason the payslip route takes a period and no employee.
         */
        $query = TeamDirectory::query()->whereHas(
            'members',
            fn (Builder $q) => $q->where('employees.id', $employee?->id ?? 0)
        );

        return response()->view('teams.mine', [
            'activeNav' => 'teams',
            'teams' => $this->paginate(clone $query, self::PER_PAGE),
            'stats' => TeamDirectory::stats(clone $query),
        ]);
    }

    /**
     * GET /teams/{team} — one team and its members.
     */
    public function show(Request $request, string $team): Response
    {
        $record = $this->find($team);

        $validated = $request->validate([
            'tab' => ['nullable', Rule::in(['all', 'by_department', 'on_leave', 'inactive'])],
            'q' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:60'],
        ]);

        $tab = $validated['tab'] ?? 'all';
        $search = trim($validated['q'] ?? '');
        $department = $validated['department'] ?? null;

        $allMembers = TeamDirectory::membersOf($record);

        $members = collect($allMembers)
            /*
             * `on_leave` is not a stored state — it is a question about today
             * that an approved leave request answers, and the Leave module has
             * no table yet. The tab stays, and reports nobody, which is the
             * honest answer rather than a stale one. Same decision as the
             * employee directory's filter.
             */
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

        return response()->view('teams.show', [
            'activeNav' => 'teams',
            'team' => TeamDirectory::row($record),
            'record' => $record,
            'members' => $this->paginateRows($members, $request, self::MEMBERS_PER_PAGE),
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
            'departments' => collect($allMembers)->pluck('department')->filter()->unique()->sort()->values()->all(),
            'breakdown' => Chart::breakdown($allMembers, 'department'),
            'circumference' => 2 * M_PI * self::DONUT_RADIUS,
            'donutRadius' => self::DONUT_RADIUS,
            'averageTenure' => TeamPresenter::averageTenure($allMembers),
            'age' => TeamPresenter::age(TeamDirectory::row($record)['created']),
            'mayEdit' => $this->rbac->can($request->user(), 'teams.edit'),
            // Membership is the routine act, and a Team Lead may do it on their
            // own team — which is why this is not simply the permission.
            'mayManageMembers' => $this->canManageMembers($request, $record),
            'addable' => $this->canManageMembers($request, $record) ? $this->addableTo($record) : collect(),
        ]);
    }

    public function create(Request $request): Response
    {
        return response()->view('teams.form', [
            'activeNav' => 'teams',
            'team' => null,
            'reference' => $this->nextReference(),
        ] + $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $team = Team::create($data + ['reference' => $this->nextReference()]);

        /*
         * The lead joins the team they lead. Not enforced by the schema —
         * leading a team you are not in is a real, if unusual, arrangement —
         * but it is what somebody naming a lead means, and having to add them
         * as a member afterwards is a step everybody forgets.
         */
        if ($team->lead_id !== null) {
            $team->members()->syncWithoutDetaching([$team->lead_id => ['joined_at' => now()]]);
        }

        $this->audit->record(
            action: AuditLog::TEAM_CREATED,
            actor: $request->user(),
            entityType: 'team',
            entityId: $team->reference,
            after: $team->name.' was created',
            request: $request,
        );

        return redirect()
            ->route('teams.show', ['team' => $team->reference])
            ->with('status', $team->name.' was created.')
            ->with('status_tone', 'success');
    }

    public function edit(Request $request, string $team): Response
    {
        $record = $this->find($team);

        return response()->view('teams.form', [
            'activeNav' => 'teams',
            'team' => $record,
            'reference' => $record->reference,
        ] + $this->formOptions());
    }

    public function update(Request $request, string $team): RedirectResponse
    {
        $record = $this->find($team);
        $data = $this->validated($request, $record);

        $before = $this->describe($record);
        $statusBefore = $record->status;

        $record->update($data);
        $record->refresh()->load('lead.user');

        if ($record->lead_id !== null) {
            $record->members()->syncWithoutDetaching([$record->lead_id => ['joined_at' => now()]]);
        }

        $this->audit->record(
            action: AuditLog::TEAM_UPDATED,
            actor: $request->user(),
            entityType: 'team',
            entityId: $record->reference,
            before: $before,
            after: $this->describe($record),
            request: $request,
        );

        /*
         * A status change gets its own entry as well as the update one. "When
         * did this team stop being used, and who decided" is a question the
         * activity rail is asked, and an update entry buries it in a sentence
         * about four other fields.
         */
        if ($statusBefore !== $record->status) {
            $this->audit->record(
                action: AuditLog::TEAM_STATUS_CHANGED,
                actor: $request->user(),
                entityType: 'team',
                entityId: $record->reference,
                before: $statusBefore,
                after: $record->name.' is now '.$record->status,
                request: $request,
            );
        }

        return redirect()
            ->route('teams.show', ['team' => $record->reference])
            ->with('status', 'Team updated.')
            ->with('status_tone', 'success');
    }

    /**
     * Add somebody to a team.
     */
    public function addMember(Request $request, string $team): RedirectResponse
    {
        $record = $this->find($team);
        $this->authorise($request, $record);

        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')],
        ]);

        $employee = Employee::with('user')->findOrFail($data['employee_id']);

        if (! $employee->user?->isActive()) {
            /*
             * Checked here rather than by the dropdown only offering active
             * people: a closed record on a team's roster is somebody who cannot
             * sign in listed as though they can, and the browser is not where
             * that rule lives.
             */
            throw ValidationException::withMessages([
                'employee_id' => 'That record is closed. Reopen it before adding them to a team.',
            ]);
        }

        // syncWithoutDetaching, not attach: the unique key would refuse a
        // double submit with a database error, and a slow connection is not an
        // error worth showing somebody a 500 for.
        $record->members()->syncWithoutDetaching([$employee->id => ['joined_at' => now()]]);

        $this->audit->record(
            action: AuditLog::TEAM_MEMBERS_CHANGED,
            actor: $request->user(),
            entityType: 'team',
            entityId: $record->reference,
            after: $employee->user?->name.' joined '.$record->name,
            request: $request,
        );

        return redirect()
            ->route('teams.show', ['team' => $record->reference])
            ->with('status', $employee->user?->name.' was added to '.$record->name.'.')
            ->with('status_tone', 'success');
    }

    /**
     * Take somebody out of a team.
     *
     * Membership is the one thing in this module that IS removed rather than
     * deactivated: it is a statement about the present, and somebody who has
     * left the team is not in it. The audit entry is what keeps the history.
     */
    public function removeMember(Request $request, string $team): RedirectResponse
    {
        $record = $this->find($team);
        $this->authorise($request, $record);

        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')],
        ]);

        $employee = Employee::with('user')->findOrFail($data['employee_id']);

        if ($record->lead_id === $employee->id) {
            /*
             * The lead cannot be removed as a member while they are still the
             * lead — the team would show a lead who is not in it, which is a
             * state nobody arrives at on purpose. Naming a different lead first
             * is one extra click and one less confusing page.
             */
            throw ValidationException::withMessages([
                'employee_id' => 'They lead this team. Name a different lead first.',
            ]);
        }

        $record->members()->detach($employee->id);

        $this->audit->record(
            action: AuditLog::TEAM_MEMBERS_CHANGED,
            actor: $request->user(),
            entityType: 'team',
            entityId: $record->reference,
            after: $employee->user?->name.' left '.$record->name,
            request: $request,
        );

        return redirect()
            ->route('teams.show', ['team' => $record->reference])
            ->with('status', $employee->user?->name.' was removed from '.$record->name.'.')
            ->with('status_tone', 'info');
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE PIECES
       ══════════════════════════════════════════════════════════════════════ */

    protected function find(string $reference): Team
    {
        return Team::query()
            ->with(['lead.user', 'lead.designation'])
            ->withCount('members')
            ->where('reference', $reference)
            ->firstOrFail();
    }

    /**
     * The signed-in person's employment record, if they have one.
     *
     * A Mentor and the owner have none (§2.1), which is why this is nullable
     * rather than an assumption — "my teams" for somebody with no employment is
     * an empty list, not an error.
     */
    protected function employeeFor(Request $request): ?Employee
    {
        return Employee::where('user_id', $request->user()?->id)->first();
    }

    /**
     * Whether this person may change who is in THIS team.
     *
     * The permission alone is not the answer. §2.6 gives a Team Lead authority
     * within their own team, so a lead may manage theirs without holding the
     * company-wide key, and somebody holding the key may not use it on a team
     * they do not lead unless they also hold `teams.edit`.
     */
    protected function canManageMembers(Request $request, Team $team): bool
    {
        if ($this->rbac->can($request->user(), 'teams.edit')) {
            return true;
        }

        return $this->rbac->can($request->user(), 'teams.members')
            && $team->isLedBy($this->employeeFor($request));
    }

    protected function authorise(Request $request, Team $team): void
    {
        abort_unless($this->canManageMembers($request, $team), 403);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Team $existing = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('teams', 'name')->ignore($existing?->id),
            ],
            'purpose' => ['nullable', 'string', 'max:200'],
            'lead_id' => ['nullable', Rule::exists('employees', 'id')],
            'status' => ['required', Rule::in(Team::STATUSES)],
            'formed_on' => ['nullable', 'date', 'before_or_equal:today'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'employees' => Employee::query()
                ->with('user')
                ->active()
                ->get()
                ->sortBy(fn (Employee $e) => $e->user?->name)
                ->values(),
        ];
    }

    /**
     * The people who could be added — active employees not already in.
     *
     * @return Collection<int, Employee>
     */
    protected function addableTo(Team $team): Collection
    {
        return Employee::query()
            ->with('user')
            ->active()
            ->whereNotIn('id', $team->members()->select('employees.id'))
            ->get()
            ->sortBy(fn (Employee $e) => $e->user?->name)
            ->values();
    }

    /**
     * The next team reference.
     *
     * From the highest existing one, never from a count — the same reason as
     * staff ids and client references: a reference two teams have held makes
     * every audit entry about it ambiguous.
     */
    protected function nextReference(): string
    {
        $highest = Team::query()
            ->where('reference', 'like', 'TM-%')
            ->selectRaw('max(cast(substr(reference, 4) as integer)) as n')
            ->value('n');

        return 'TM-'.(max((int) $highest, 1000) + 1);
    }

    protected function describe(Team $team): string
    {
        return implode(' · ', array_filter([
            $team->name,
            $team->purpose,
            $team->status,
            $team->lead?->user?->name ? 'led by '.$team->lead->user->name : null,
        ]));
    }

    /**
     * @param  Builder<Team>  $query
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function paginate(Builder $query, int $perPage): LengthAwarePaginator
    {
        return $query->paginate($perPage)->withQueryString()->through(fn (Team $t) => TeamDirectory::row($t));
    }

    /**
     * Pagination over an in-memory list — the member table, which is filtered
     * and grouped in PHP because it is one team's worth of people.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function paginateRows(Collection $rows, Request $request, int $perPage): LengthAwarePaginator
    {
        $page = Paginator::resolveCurrentPage();

        return new Paginator(
            items: $rows->forPage($page, $perPage)->values(),
            total: $rows->count(),
            perPage: $perPage,
            currentPage: $page,
            options: ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
