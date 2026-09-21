<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Models\Team;
use App\Support\Audit\AuditLog;
use App\Support\ProjectDirectory;
use App\Support\Rbac\Rbac;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Projects — the managing face (`/projects`), the personal face
 * (`/projects/mine`), one project's overview (`/projects/{project}`) and the
 * end-of-day updates. Foundation spec §12.1.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHO MAY WRITE AN END-OF-DAY UPDATE
 *
 * Anybody on the project, and nobody else. Not a permission — it is not an
 * authority, it is the ordinary act of reporting your own day — so it is an
 * ownership check: the viewer's employment record has to manage the project or
 * be in a team assigned to it. Somebody who is on no projects has nothing to
 * submit, and the page says so.
 *
 * Making an update client-visible IS a permission, and a separate one from
 * editing a project. It is a disclosure: it puts a sentence somebody wrote at
 * six in the evening in front of the company it is about.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class ProjectController extends Controller
{
    protected const PER_PAGE = 8;

    protected const DONUT_RADIUS = 57;

    public function __construct(protected Rbac $rbac, protected AuditLog $audit)
    {
    }

    /**
     * GET /projects
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(Project::STATUSES)],
            'priority' => ['nullable', Rule::in(Project::PRIORITIES)],
        ]);

        $search = trim($filters['q'] ?? '');
        $status = $filters['status'] ?? null;
        $priority = $filters['priority'] ?? null;

        $query = ProjectDirectory::query(
            $search !== '' ? $search : null,
            $status,
            $priority,
        );

        return response()->view('projects.index', [
            'activeNav' => 'projects',
            'projects' => $this->paginate($query),
            'search' => $search,
            'status' => $status,
            'priority' => $priority,
            'filtered' => $search !== '' || $status !== null || $priority !== null,
            'stats' => ProjectDirectory::stats(),
            'breakdown' => ProjectDirectory::statusBreakdown(),
            'circumference' => 2 * M_PI * self::DONUT_RADIUS,
            'donutRadius' => self::DONUT_RADIUS,
            'upcoming' => ProjectDirectory::upcoming(),
            'mayCreate' => $this->rbac->can($request->user(), 'projects.create'),
        ]);
    }

    /**
     * GET /projects/mine — a separate page, not a filter: §12.1, and this one
     * needs no `projects.view`.
     */
    public function mine(Request $request): Response
    {
        $query = $this->mineQuery($request);

        return response()->view('projects.mine', [
            'activeNav' => 'projects',
            'projects' => $this->paginate(clone $query),
            'stats' => ProjectDirectory::stats(clone $query),
        ]);
    }

    /**
     * GET /projects/updates — the end-of-day submission list.
     */
    public function updates(Request $request): Response
    {
        return response()->view('projects.updates', [
            'activeNav' => 'projects',
            'projects' => $this->paginate($this->mineQuery($request)),
        ]);
    }

    /**
     * GET /projects/{project}
     */
    public function show(Request $request, string $project): Response
    {
        $record = $this->find($project);

        return response()->view('projects.show', [
            'activeNav' => 'projects',
            'project' => ProjectDirectory::row($record),
            'record' => $record,
            'teams' => ProjectDirectory::teamsOf($record),
            /*
             * The staff view of the log, internal notes included — that is what
             * the staff side is for. The client portal reads
             * ProjectUpdate::clientVisible and has no route to this.
             */
            'updates' => $record->updates()->with('author.user')->limit(20)->get(),
            'mayEdit' => $this->rbac->can($request->user(), 'projects.edit'),
            'mayPublish' => $this->rbac->can($request->user(), 'projects.publish'),
            'mayPost' => $this->isOnProject($request, $record),
        ]);
    }

    public function create(Request $request): Response
    {
        return response()->view('projects.form', [
            'activeNav' => 'projects',
            'project' => null,
            'reference' => $this->nextReference(),
            'assigned' => [],
        ] + $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $teams = $data['teams'] ?? [];
        unset($data['teams']);

        $project = DB::transaction(function () use ($data, $teams) {
            $project = Project::create($data + ['reference' => $this->nextReference()]);
            $project->teams()->sync($teams);

            return $project;
        });

        $this->audit->record(
            action: AuditLog::PROJECT_CREATED,
            actor: $request->user(),
            entityType: 'project',
            entityId: $project->reference,
            after: $this->describe($project->fresh(['client', 'manager.user'])),
            request: $request,
        );

        return redirect()
            ->route('projects.show', ['project' => $project->reference])
            ->with('status', $project->name.' was created.')
            ->with('status_tone', 'success');
    }

    public function edit(Request $request, string $project): Response
    {
        $record = $this->find($project);

        return response()->view('projects.form', [
            'activeNav' => 'projects',
            'project' => $record,
            'reference' => $record->reference,
            'assigned' => $record->teams()->pluck('teams.id')->all(),
        ] + $this->formOptions());
    }

    public function update(Request $request, string $project): RedirectResponse
    {
        $record = $this->find($project);
        $data = $this->validated($request, $record);

        $teams = $data['teams'] ?? [];
        unset($data['teams']);

        $before = $this->describe($record);
        $statusBefore = $record->status;

        DB::transaction(function () use ($record, $data, $teams) {
            $record->update($data);
            // sync, not syncWithoutDetaching: the form shows every team with
            // the assigned ones ticked, so an unticked box means "take this
            // team off", and detaching is the whole of what it means.
            $record->teams()->sync($teams);
        });

        $record->refresh()->load(['client', 'manager.user']);

        $this->audit->record(
            action: AuditLog::PROJECT_UPDATED,
            actor: $request->user(),
            entityType: 'project',
            entityId: $record->reference,
            before: $before,
            after: $this->describe($record),
            request: $request,
        );

        if ($statusBefore !== $record->status) {
            $this->audit->record(
                action: AuditLog::PROJECT_STATUS_CHANGED,
                actor: $request->user(),
                entityType: 'project',
                entityId: $record->reference,
                before: $statusBefore,
                after: $record->name.' is now '.str_replace('_', ' ', $record->status),
                request: $request,
            );
        }

        return redirect()
            ->route('projects.show', ['project' => $record->reference])
            ->with('status', 'Project updated.')
            ->with('status_tone', 'success');
    }

    /* ══════════════════════════════════════════════════════════════════════
       END-OF-DAY UPDATES
       ══════════════════════════════════════════════════════════════════════ */

    public function createUpdate(Request $request, string $project): Response
    {
        $record = $this->find($project);

        $this->requireOnProject($request, $record);

        return response()->view('projects.update-form', [
            'activeNav' => 'projects',
            'project' => ProjectDirectory::row($record),
            'record' => $record,
            'mayPublish' => $this->rbac->can($request->user(), 'projects.publish'),
        ]);
    }

    /**
     * Write today's update.
     *
     * The visibility field is on the form from the first line of markup rather
     * than bolted on later, and it arrives as `internal` unless somebody who
     * may publish chose otherwise — the database default says the same thing,
     * because a row inserted any other way must be internal too.
     */
    public function storeUpdate(Request $request, string $project): RedirectResponse
    {
        $record = $this->find($project);

        $author = $this->requireOnProject($request, $record);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
            'visibility' => ['nullable', Rule::in([ProjectUpdate::INTERNAL, ProjectUpdate::CLIENT])],
        ]);

        /*
         * Asking to publish is only honoured from somebody who may. Dropped
         * rather than refused: the update itself is fine and should be saved,
         * and losing somebody's written account of their day because they
         * ticked a box they were not allowed to tick would be the worse
         * outcome. The audit entry records what was actually stored.
         */
        $wantsClient = ($data['visibility'] ?? ProjectUpdate::INTERNAL) === ProjectUpdate::CLIENT
            && $this->rbac->can($request->user(), 'projects.publish');

        $update = ProjectUpdate::create([
            'reference' => $this->nextUpdateReference(),
            'project_id' => $record->id,
            'author_id' => $author->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'visibility' => $wantsClient ? ProjectUpdate::CLIENT : ProjectUpdate::INTERNAL,
            'published_at' => $wantsClient ? now() : null,
        ]);

        $this->audit->record(
            action: $wantsClient ? AuditLog::PROJECT_UPDATE_PUBLISHED : AuditLog::PROJECT_UPDATE_POSTED,
            actor: $request->user(),
            entityType: 'project',
            entityId: $record->reference,
            after: $update->title.($wantsClient ? ' — shared with the client' : ' — internal'),
            request: $request,
        );

        return redirect()
            ->route('projects.show', ['project' => $record->reference])
            ->with('status', 'Update posted.')
            ->with('status_tone', 'success');
    }

    /**
     * Share an existing update with the client, or take it off their view.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * HIDING IT AGAIN IS NOT A RECALL
     *
     * `published_at` is set once and never cleared, so the record keeps saying
     * this was in front of the client from that moment. If they have read it,
     * they have read it, and no screen here may suggest otherwise.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function publishUpdate(Request $request, string $project, string $update): RedirectResponse
    {
        $record = $this->find($project);

        $entry = ProjectUpdate::where('project_id', $record->id)
            ->where('reference', $update)
            ->firstOrFail();

        $data = $request->validate([
            'visibility' => ['required', Rule::in([ProjectUpdate::INTERNAL, ProjectUpdate::CLIENT])],
        ]);

        $before = $entry->visibility;

        if ($before === $data['visibility']) {
            return redirect()
                ->route('projects.show', ['project' => $record->reference])
                ->with('status', 'Nothing changed.')
                ->with('status_tone', 'info');
        }

        $entry->update([
            'visibility' => $data['visibility'],
            // Set the first time only. See the note above.
            'published_at' => $data['visibility'] === ProjectUpdate::CLIENT
                ? ($entry->published_at ?? now())
                : $entry->published_at,
        ]);

        $this->audit->record(
            action: $data['visibility'] === ProjectUpdate::CLIENT
                ? AuditLog::PROJECT_UPDATE_PUBLISHED
                : AuditLog::PROJECT_UPDATE_HIDDEN,
            actor: $request->user(),
            entityType: 'project',
            entityId: $record->reference,
            before: $before,
            after: $entry->title.' is now '.($entry->visibility === ProjectUpdate::CLIENT
                ? 'visible to the client'
                : 'internal only'),
            request: $request,
        );

        return redirect()
            ->route('projects.show', ['project' => $record->reference])
            ->with('status', $entry->visibility === ProjectUpdate::CLIENT
                ? 'Shared with the client.'
                : 'Hidden from the client. They may already have read it.')
            ->with('status_tone', 'info');
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE PIECES
       ══════════════════════════════════════════════════════════════════════ */

    protected function find(string $reference): Project
    {
        return Project::query()
            ->with(['client', 'manager.user', 'manager.designation'])
            ->where('reference', $reference)
            ->firstOrFail();
    }

    protected function employeeFor(Request $request): ?Employee
    {
        return Employee::where('user_id', $request->user()?->id)->first();
    }

    /**
     * The projects the viewer is on — managing, or in an assigned team.
     *
     * @return Builder<Project>
     */
    protected function mineQuery(Request $request): Builder
    {
        return ProjectDirectory::query()->forEmployee($this->employeeFor($request));
    }

    protected function isOnProject(Request $request, Project $project): bool
    {
        $employee = $this->employeeFor($request);

        return $employee !== null
            && Project::query()->whereKey($project->id)->forEmployee($employee)->exists();
    }

    /**
     * Refuse anybody writing an update on a project they are not on.
     */
    protected function requireOnProject(Request $request, Project $project): Employee
    {
        $employee = $this->employeeFor($request);

        abort_unless($this->isOnProject($request, $project), 403);

        return $employee;
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Project $existing = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'client_id' => ['required', Rule::exists('clients', 'id')],
            'manager_id' => ['nullable', Rule::exists('employees', 'id')],
            'status' => ['required', Rule::in(Project::STATUSES)],
            'priority' => ['required', Rule::in(Project::PRIORITIES)],
            'started_on' => ['nullable', 'date'],
            // Required, because every page in this module is built around what
            // is late — see the head of the migration.
            'deadline' => ['required', 'date'],
            'teams' => ['nullable', 'array'],
            'teams.*' => [Rule::exists('teams', 'id')],
        ]);

        if (isset($data['started_on']) && $data['started_on'] > $data['deadline']) {
            // Caught here rather than by `after:started_on` on the deadline,
            // so the message lands on the field somebody is likelier to have
            // got wrong — the one they typed last.
            throw ValidationException::withMessages([
                'deadline' => 'The deadline cannot be before the project started.',
            ]);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            // Clients that are actually being worked with. An inactive
            // engagement is not one to start new work against, and offering it
            // invites the mistake.
            'clients' => Client::query()->whereNot('status', 'inactive')->orderBy('name')->get(),
            'managers' => Employee::query()->with('user')->active()->get()
                ->sortBy(fn (Employee $e) => $e->user?->name)->values(),
            'teams' => Team::query()->active()->orderBy('name')->get(),
        ];
    }

    /**
     * The next project reference.
     *
     * Year-scoped, and derived from the highest existing one in that year — not
     * from a count, which reissues a reference the moment anything is removed.
     */
    protected function nextReference(): string
    {
        $year = now()->year;
        $prefix = 'PRJ-'.$year.'-';

        $highest = Project::query()
            ->where('reference', 'like', $prefix.'%')
            ->selectRaw('max(cast(substr(reference, ?) as integer)) as n', [strlen($prefix) + 1])
            ->value('n');

        return $prefix.str_pad((string) (((int) $highest) + 1), 3, '0', STR_PAD_LEFT);
    }

    protected function nextUpdateReference(): string
    {
        $year = now()->year;
        $prefix = 'UPD-'.$year.'-';

        $highest = ProjectUpdate::query()
            ->where('reference', 'like', $prefix.'%')
            ->selectRaw('max(cast(substr(reference, ?) as integer)) as n', [strlen($prefix) + 1])
            ->value('n');

        return $prefix.str_pad((string) (((int) $highest) + 1), 3, '0', STR_PAD_LEFT);
    }

    protected function describe(Project $project): string
    {
        return implode(' · ', array_filter([
            $project->name,
            $project->client?->name,
            str_replace('_', ' ', $project->status),
            $project->priority.' priority',
            'due '.$project->deadline->format('d M Y'),
            $project->manager?->user?->name ? 'managed by '.$project->manager->user->name : null,
        ]));
    }

    /**
     * @param  Builder<Project>  $query
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function paginate(Builder $query): LengthAwarePaginator
    {
        return $query->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Project $p) => ProjectDirectory::row($p));
    }
}
