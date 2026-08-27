<?php

namespace App\Http\Controllers;

use App\Support\Chart;
use App\Support\Demo\DemoEmployees;
use App\Support\Demo\DemoProjects;
use App\Support\ProjectPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Projects — the managing face (`/projects`), the personal face
 * (`/projects/mine`), one project's overview (`/projects/{project}`) and the
 * end-of-day update list (`/projects/updates`). Foundation spec §12.1.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FRONT END ONLY. There is no `projects` table; rows come from
 * App\Support\Demo\DemoProjects, which returns nothing outside local + debug.
 *
 * Still to add with the backend: `projects.view` for the managing face, and
 * §6's ownership rule — a client signing in to `/client` must see only their
 * own projects, which is the single most common leak in software of this
 * shape. `/projects/mine` and `/projects/updates` filter on the viewer.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class ProjectController extends Controller
{
    protected const PER_PAGE = 8;

    protected const DONUT_RADIUS = 57;

    /**
     * GET /projects
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(ProjectPresenter::statusOptions())],
            'priority' => ['nullable', Rule::in(ProjectPresenter::priorityOptions())],
        ]);

        $search = trim($filters['q'] ?? '');
        $status = $filters['status'] ?? null;
        $priority = $filters['priority'] ?? null;

        $matches = DemoProjects::all()
            ->when($search !== '', fn (Collection $rows) => $rows->filter(
                fn (array $p) => str_contains(
                    mb_strtolower($p['name'].' '.$p['id'].' '.$p['client']),
                    mb_strtolower($search)
                )
            ))
            ->when($status, fn (Collection $rows) => $rows->where('status', $status))
            ->when($priority, fn (Collection $rows) => $rows->where('priority', $priority))
            ->values();

        $all = DemoProjects::all();

        return response()->view('projects.index', [
            'activeNav' => 'projects',
            'projects' => $this->paginate($matches->map(fn (array $p) => $this->decorate($p)), $request),
            'search' => $search,
            'status' => $status,
            'priority' => $priority,
            'filtered' => $search !== '' || $status !== null || $priority !== null,
            'stats' => DemoProjects::stats(),
            'breakdown' => $this->statusBreakdown($all),
            'circumference' => 2 * M_PI * self::DONUT_RADIUS,
            'donutRadius' => self::DONUT_RADIUS,
            'upcoming' => array_map(fn (array $p) => $this->decorate($p), DemoProjects::upcoming()),
        ]);
    }

    /**
     * GET /projects/mine — a separate page, not a filter: §12.1, and this one
     * needs no `projects.view`.
     */
    public function mine(Request $request): Response
    {
        $mine = DemoProjects::mine();

        return response()->view('projects.mine', [
            'activeNav' => 'projects',
            'projects' => $this->paginate($mine->map(fn (array $p) => $this->decorate($p)), $request),
            'stats' => DemoProjects::stats($mine),
        ]);
    }

    /**
     * GET /projects/updates — the end-of-day submission list.
     */
    public function updates(Request $request): Response
    {
        $mine = DemoProjects::mine();

        return response()->view('projects.updates', [
            'activeNav' => 'projects',
            'projects' => $this->paginate($mine->map(fn (array $p) => $this->decorate($p)), $request),
        ]);
    }

    /**
     * GET /projects/{project}
     */
    public function show(Request $request, string $project): Response
    {
        $record = DemoProjects::find($project);

        abort_if($record === null, 404);

        return response()->view('projects.show', [
            'activeNav' => 'projects',
            'project' => $this->decorate($record),
            'teams' => DemoProjects::teamsOf($record),
        ]);
    }

    /**
     * Attach the things every project view needs.
     *
     * @param  array<string, mixed>  $project
     * @return array<string, mixed>
     */
    protected function decorate(array $project): array
    {
        return $project + [
            'manager_record' => $project['manager']
                ? DemoEmployees::all()->firstWhere('user_id', $project['manager'])
                : null,
            'deadline_meta' => ProjectPresenter::deadline($project['deadline'], $project['status']),
        ];
    }

    /**
     * Projects grouped by status, for the donut. Labels rather than raw keys,
     * so the legend reads as words.
     *
     * @param  Collection<int, array<string, mixed>>  $projects
     * @return list<array{name: string, count: int, share: float}>
     */
    protected function statusBreakdown(Collection $projects): array
    {
        $labelled = $projects->map(fn (array $p) => [
            'status' => ProjectPresenter::status($p['status'])['label'],
        ]);

        return Chart::breakdown($labelled, 'status');
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
