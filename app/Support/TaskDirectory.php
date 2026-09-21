<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tasks, read from the database.
 *
 * The row shape the four task pages already read: id (the reference), name,
 * project, team, assignees, status, priority, due — plus the `*_record`
 * values the templates draw and the `due_meta` reading. The controller used
 * to attach those in a decorate() step; they belong with the rest of the
 * shape.
 *
 * `assignees`/`assignee_records` are lists, not a single value — several
 * assignees per task (decided) replaced the single `assignee_id` foreign key
 * with the `task_assignees` pivot; see the head of App\Models\Task.
 */
class TaskDirectory
{
    /**
     * The list query, filtered.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Task>
     */
    public static function query(array $filters = []): Builder
    {
        $search = ($filters['search'] ?? '') !== '' ? $filters['search'] : null;

        return Task::query()
            ->with(['project.client', 'team.lead.user', 'assignees.user', 'assignees.designation'])
            ->when($search, fn (Builder $q, string $term) => $q->where(function (Builder $q) use ($term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

                $q->where('tasks.name', 'like', $like)
                    ->orWhere('reference', 'like', $like);
            }))
            ->when($filters['status'] ?? null, fn (Builder $q, string $v) => $q->where('status', $v))
            ->when($filters['priority'] ?? null, fn (Builder $q, string $v) => $q->where('priority', $v))
            /*
             * Project and team filters take a REFERENCE, because that is what
             * the dropdowns offer and what a shared URL should read as. A
             * primary key in a query string is neither.
             */
            ->when($filters['project'] ?? null, fn (Builder $q, string $ref) => $q->whereHas(
                'project', fn (Builder $p) => $p->where('reference', $ref)
            ))
            ->when($filters['team'] ?? null, fn (Builder $q, string $ref) => $q->whereHas(
                'team', fn (Builder $t) => $t->where('reference', $ref)
            ))
            // Soonest first: the list is read to find out what is next.
            ->orderBy('due_on');
    }

    /**
     * One task in the shape the views read.
     *
     * @return array<string, mixed>
     */
    public static function row(Task $task): array
    {
        $due = $task->due_on->toDateString();

        return [
            'id' => $task->reference,
            'name' => $task->name,
            'description' => $task->description,
            'project' => $task->project?->reference,
            'team' => $task->team?->reference,
            'assignees' => $task->assignees->map(fn (Employee $e) => $e->user?->user_id)->filter()->values()->all(),
            'status' => $task->status,
            'priority' => $task->priority,
            'due' => $due,
            'due_in' => (int) Carbon::today()->diffInDays($task->due_on->startOfDay(), false),
            'created_at' => $task->created_at?->toDateString(),

            'project_record' => $task->project ? ProjectDirectory::row($task->project) : null,
            'team_record' => $task->team ? TeamDirectory::row($task->team) : null,
            'assignee_records' => $task->assignees->map(fn (Employee $e) => EmployeeDirectory::row($e))->all(),
            'due_meta' => TaskPresenter::due($due, $task->status),
        ];
    }

    /**
     * The headline counts.
     *
     * @param  Builder<Task>|null  $scope
     * @return array<string, int>
     */
    public static function stats(?Builder $scope = null): array
    {
        $base = fn () => clone ($scope ?? Task::query());

        return [
            'total' => $base()->count(),
            'pending' => $base()->where('status', 'pending')->count(),
            'in_progress' => $base()->where('status', 'in_progress')->count(),
            'completed' => $base()->where('status', 'completed')->count(),
            'overdue' => $base()->overdue()->count(),
            'due_today' => $base()->open()->whereDate('due_on', now()->toDateString())->count(),
        ];
    }

    /**
     * The next few deadlines still ahead of us.
     *
     * @return list<array<string, mixed>>
     */
    public static function upcoming(int $limit = 5): array
    {
        return Task::query()
            ->with(['project.client', 'team', 'assignees.user'])
            ->open()
            ->whereDate('due_on', '>=', now()->toDateString())
            ->orderBy('due_on')
            ->limit($limit)
            ->get()
            ->map(fn (Task $t) => self::row($t))
            ->all();
    }

    /**
     * What has happened to one task, oldest first — from the audit log (§6).
     *
     * The demo timeline invented four events from the task's own fields, which
     * looked like history and was a reconstruction: it could say a task had
     * been assigned but never who reassigned it, or when.
     *
     * @return list<array<string, string>>
     */
    public static function timeline(string $reference): array
    {
        return DB::table('audit_log')
            ->where('entity_type', 'task')
            ->where('entity_id', $reference)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (object $row) => [
                'when' => Carbon::parse($row->created_at)->format('d M Y, g:i A'),
                'what' => self::summaryOf($row) ?? str_replace(['task.', '_'], ['', ' '], $row->action),
                'who' => $row->actor_label,
            ])
            ->all();
    }

    /**
     * Recent activity across every task, for the rail.
     *
     * @return list<array<string, string>>
     */
    public static function activity(int $limit = 4): array
    {
        return DB::table('audit_log')
            ->where('entity_type', 'task')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (object $row) => [
                'who' => $row->actor_label,
                'what' => self::summaryOf($row) ?? str_replace(['task.', '_'], ['', ' '], $row->action),
                'when' => Carbon::parse($row->created_at)->diffForHumans(),
                'tone' => match ($row->action) {
                    'task.completed' => 'tone-accent',
                    'task.assigned' => 'tone-alt',
                    default => '',
                },
            ])
            ->all();
    }

    /**
     * The next task reference — from the highest existing one, never a
     * count. Owned here rather than by TaskController alone because
     * TicketController::convertToTask needs to mint one too, and two copies
     * of the same numbering logic is the version that drifts.
     */
    public static function nextReference(): string
    {
        $highest = Task::query()
            ->where('reference', 'like', 'TSK-%')
            ->selectRaw('max(cast(substr(reference, 5) as integer)) as n')
            ->value('n');

        return 'TSK-'.str_pad((string) (((int) $highest) + 1), 3, '0', STR_PAD_LEFT);
    }

    protected static function summaryOf(object $row): ?string
    {
        $decoded = json_decode((string) $row->after_json, true);

        return is_array($decoded) ? ($decoded['summary'] ?? null) : null;
    }
}
