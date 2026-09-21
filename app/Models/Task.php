<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A task (foundation spec §12.1).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * SEVERAL ASSIGNEES, NOT ONE
 *
 * `assignee_id` was a single nullable foreign key until
 * 2026_09_21_000028_tasks_multiple_assignees_comments_attachments.php. "A task
 * can go to a team and flow to its members" (review round decision) means more
 * than one person can be on it — two people pairing on the same piece of work
 * is the ordinary case `assignees()` exists for. The pivot is `task_assignees`,
 * shaped exactly like `team_members` and for the same reason a JSON list of
 * ids would fail: it cannot be joined against, cannot carry its own foreign
 * key, and would keep pointing at somebody after their record closed.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class Task extends Model
{
    /** @var list<string> */
    public const STATUSES = ['pending', 'in_progress', 'review', 'completed', 'blocked'];

    /** @var list<string> */
    public const PRIORITIES = ['high', 'medium', 'low'];

    protected $fillable = [
        'reference', 'name', 'description', 'project_id', 'team_id',
        'status', 'priority', 'due_on', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Who is on the task.
     *
     * Ordered by the pivot's own key, same as Team::members() — without it the
     * order is whatever the database happens to return, and a page that
     * reshuffles on reload looks broken to the person reading it.
     *
     * @return BelongsToMany<Employee, $this>
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'task_assignees')
            ->withPivot(['id'])
            ->withTimestamps()
            ->orderBy('task_assignees.id');
    }

    /**
     * @return HasMany<TaskComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->oldest();
    }

    /**
     * @return HasMany<TaskAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class)->latest();
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', '!=', 'completed');
    }

    /**
     * Past its date and not finished.
     *
     * A task delivered late is history, not an outstanding problem — the same
     * rule Projects uses, so the two modules cannot contradict each other.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereDate('due_on', '<', now()->toDateString());
    }

    /**
     * Held by a team with nobody on it — the Team Lead's queue.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->doesntHave('assignees');
    }

    /**
     * Whether this person may decide the outcome of this task.
     *
     * One of the assignees, because it is their work; whoever leads the team
     * holding it, because that is their queue (§2.6). Anybody wider than that
     * holds `tasks.edit` and the controller asks for that separately — this
     * method is only about the people close to the work.
     */
    public function isOwnedBy(?Employee $employee): bool
    {
        if ($employee === null) {
            return false;
        }

        return $this->assignees->contains('id', $employee->id)
            || ($this->team !== null && $this->team->isLedBy($employee));
    }
}
