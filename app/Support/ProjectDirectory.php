<?php

namespace App\Support;

use App\Models\Project;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Projects, read from the database.
 *
 * The row shape the views already read: `id` (the reference), name, client,
 * manager, progress, deadline, status, priority — plus `manager_record` and
 * `deadline_meta`, which the controller used to attach in a decorate() step and
 * which belong with the rest of the shape.
 *
 * `due_in` is kept because the KPI counts used it. It is derived from the
 * deadline rather than stored, which is what it always was.
 */
class ProjectDirectory
{
    /**
     * The list query, filtered.
     *
     * @return Builder<Project>
     */
    public static function query(?string $search = null, ?string $status = null, ?string $priority = null): Builder
    {
        return Project::query()
            ->with(['client', 'manager.user', 'manager.designation'])
            ->when($search, fn (Builder $q, string $term) => $q->where(function (Builder $q) use ($term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

                $q->where('projects.name', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhereHas('client', fn (Builder $c) => $c->where('name', 'like', $like));
            }))
            ->when($status, fn (Builder $q, string $value) => $q->where('status', $value))
            ->when($priority, fn (Builder $q, string $value) => $q->where('priority', $value))
            // Soonest deadline first: the list is read to find out what is
            // next, and alphabetical order answers a question nobody asks of a
            // project list.
            ->orderBy('deadline');
    }

    /**
     * One project in the shape the views read.
     *
     * @return array<string, mixed>
     */
    public static function row(Project $project): array
    {
        $deadline = $project->deadline->toDateString();

        return [
            'id' => $project->reference,
            'name' => $project->name,
            'client' => $project->client?->name,
            'client_reference' => $project->client?->reference,
            'manager' => $project->manager?->user?->user_id,
            'manager_record' => $project->manager ? EmployeeDirectory::row($project->manager) : null,
            'progress' => $project->progress,
            'status' => $project->status,
            'priority' => $project->priority,
            'deadline' => $deadline,
            'start_date' => $project->started_on?->toDateString(),
            // Days from today, negative when it has passed. Derived, never
            // stored — a stored one is wrong by tomorrow morning.
            'due_in' => (int) Carbon::today()->diffInDays($project->deadline->startOfDay(), false),
            'deadline_meta' => ProjectPresenter::deadline($deadline, $project->status),
        ];
    }

    /**
     * The headline counts.
     *
     * @param  Builder<Project>|null  $scope  count within one list rather than all
     * @return array<string, int>
     */
    public static function stats(?Builder $scope = null): array
    {
        $base = fn () => clone ($scope ?? Project::query());

        return [
            'total' => $base()->count(),
            'active' => $base()->whereIn('status', ['in_progress', 'planning', 'review'])->count(),
            'completed' => $base()->where('status', 'completed')->count(),
            'on_hold' => $base()->where('status', 'on_hold')->count(),
            // Past its deadline and not finished — see Project::scopeOverdue.
            'overdue' => $base()->overdue()->count(),
        ];
    }

    /**
     * The next deadlines still ahead of us, soonest first.
     *
     * @return list<array<string, mixed>>
     */
    public static function upcoming(int $limit = 4): array
    {
        return Project::query()
            ->with(['client', 'manager.user'])
            ->open()
            ->orderBy('deadline')
            ->limit($limit)
            ->get()
            ->map(fn (Project $p) => self::row($p))
            ->all();
    }

    /**
     * Projects grouped by status, for the donut — labels, not raw keys, so the
     * legend reads as words.
     *
     * @return list<array{name: string, count: int, share: float}>
     */
    public static function statusBreakdown(): array
    {
        $labelled = Project::query()
            ->get(['status'])
            ->map(fn (Project $p) => ['status' => ProjectPresenter::status($p->status)['label']]);

        return Chart::breakdown($labelled, 'status');
    }

    /**
     * The teams on a project, in the shape the teams card draws.
     *
     * @return list<array<string, mixed>>
     */
    public static function teamsOf(Project $project): array
    {
        return $project->teams()
            ->with(['lead.user', 'lead.designation'])
            ->withCount('members')
            ->get()
            ->map(fn (Team $team) => TeamDirectory::row($team))
            ->all();
    }
}
