<?php

namespace App\Http\Controllers;

use App\Support\Demo\DemoEmployees;
use App\Support\Demo\DemoProjects;
use App\Support\Demo\DemoTasks;
use App\Support\Demo\DemoTeams;
use App\Support\TaskPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Tasks — the managing face (`/tasks`), the personal face (`/tasks/mine`), the
 * team lead's queue (`/tasks/team`) and one task (`/tasks/{task}`).
 * Foundation spec §12.1.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FRONT END ONLY. There is no `tasks` table; rows come from
 * App\Support\Demo\DemoTasks, which returns nothing outside local + debug.
 *
 * Still to add with the backend: `tasks.view` for the managing face; §2.6's
 * rank rules for `/tasks/team`, where a Team Lead may act within their own
 * teams only; and the ownership check on `/tasks/mine`.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class TaskController extends Controller
{
    protected const PER_PAGE = 8;

    /**
     * GET /tasks
     */
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        return response()->view('tasks.index', [
            'activeNav' => 'tasks',
            'tasks' => $this->paginate($this->matching(DemoTasks::all(), $filters), $request),
            'stats' => DemoTasks::stats(),
            'upcoming' => array_map(fn (array $t) => $this->decorate($t), DemoTasks::upcoming()),
            'activity' => DemoTasks::activity(),
            'projects' => $this->projectOptions(),
            'teams' => $this->teamOptions(),
        ] + $filters);
    }

    /**
     * GET /tasks/mine
     */
    public function mine(Request $request): Response
    {
        $filters = $this->filters($request);
        $mine = DemoTasks::mine();

        return response()->view('tasks.mine', [
            'activeNav' => 'tasks',
            'tasks' => $this->paginate($this->matching($mine, $filters), $request),
            'stats' => DemoTasks::stats($mine),
            'projects' => $this->projectOptions(),
            'teams' => $this->teamOptions(),
        ] + $filters);
    }

    /**
     * GET /tasks/team — tasks held by a team rather than a person.
     *
     * This is the Team Lead's queue: the point of it is deciding who picks each
     * one up, so the "nobody assigned yet" state is the normal case here rather
     * than an exception.
     */
    public function team(Request $request): Response
    {
        $filters = $this->filters($request);
        $teamTasks = DemoTasks::teamTasks();

        return response()->view('tasks.team', [
            'activeNav' => 'tasks',
            'tasks' => $this->paginate($this->matching($teamTasks, $filters), $request),
            'stats' => DemoTasks::stats($teamTasks),
            'projects' => $this->projectOptions(),
            'teams' => $this->teamOptions(),
        ] + $filters);
    }

    /**
     * GET /tasks/{task}
     */
    public function show(Request $request, string $task): Response
    {
        $record = DemoTasks::find($task);

        abort_if($record === null, 404);

        return response()->view('tasks.show', [
            'activeNav' => 'tasks',
            'task' => $this->decorate($record),
            'timeline' => DemoTasks::timeline($record),
            'attachments' => DemoTasks::attachments($record),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(TaskPresenter::statusOptions())],
            'priority' => ['nullable', Rule::in(TaskPresenter::priorityOptions())],
            'project' => ['nullable', 'string', 'max:32'],
            'team' => ['nullable', 'string', 'max:32'],
        ]);

        $search = trim($validated['q'] ?? '');

        return [
            'search' => $search,
            'status' => $validated['status'] ?? null,
            'priority' => $validated['priority'] ?? null,
            'project' => $validated['project'] ?? null,
            'team' => $validated['team'] ?? null,
            'filtered' => $search !== ''
                || ($validated['status'] ?? null) !== null
                || ($validated['priority'] ?? null) !== null
                || ($validated['project'] ?? null) !== null
                || ($validated['team'] ?? null) !== null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $tasks
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function matching(Collection $tasks, array $filters): Collection
    {
        return $tasks
            ->when($filters['search'] !== '', fn (Collection $rows) => $rows->filter(
                fn (array $t) => str_contains(mb_strtolower($t['name'].' '.$t['id']), mb_strtolower($filters['search']))
            ))
            ->when($filters['status'], fn (Collection $rows) => $rows->where('status', $filters['status']))
            ->when($filters['priority'], fn (Collection $rows) => $rows->where('priority', $filters['priority']))
            ->when($filters['project'], fn (Collection $rows) => $rows->where('project', $filters['project']))
            ->when($filters['team'], fn (Collection $rows) => $rows->where('team', $filters['team']))
            ->map(fn (array $t) => $this->decorate($t))
            ->values();
    }

    /**
     * Attach the project, team, assignee and due-date reading every task view
     * needs. Done once here rather than in four templates.
     *
     * @param  array<string, mixed>  $task
     * @return array<string, mixed>
     */
    protected function decorate(array $task): array
    {
        return $task + [
            'project_record' => DemoProjects::find($task['project']),
            'team_record' => $task['team'] ? DemoTeams::find($task['team']) : null,
            'assignee_record' => $task['assignee']
                ? DemoEmployees::all()->firstWhere('user_id', $task['assignee'])
                : null,
            'due_meta' => TaskPresenter::due($task['due'], $task['status']),
        ];
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    protected function projectOptions(): array
    {
        return DemoProjects::all()
            ->map(fn (array $p) => ['id' => $p['id'], 'name' => $p['name']])
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    protected function teamOptions(): array
    {
        return DemoTeams::all()
            ->map(fn (array $t) => ['id' => $t['id'], 'name' => $t['name']])
            ->sortBy('name')
            ->values()
            ->all();
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
