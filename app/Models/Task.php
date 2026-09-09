<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A task (foundation spec §12.1).
 */
class Task extends Model
{
    /** @var list<string> */
    public const STATUSES = ['pending', 'in_progress', 'review', 'completed', 'blocked'];

    /** @var list<string> */
    public const PRIORITIES = ['high', 'medium', 'low'];

    protected $fillable = [
        'reference', 'name', 'description', 'project_id', 'team_id', 'assignee_id',
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
     * @return BelongsTo<Employee, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assignee_id');
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
        return $query->whereNull('assignee_id');
    }

    /**
     * Whether this person may decide the outcome of this task.
     *
     * The assignee, because it is their work; whoever leads the team holding
     * it, because that is their queue (§2.6). Anybody wider than that holds
     * `tasks.edit` and the controller asks for that separately — this method
     * is only about the two people close to the work.
     */
    public function isOwnedBy(?Employee $employee): bool
    {
        if ($employee === null) {
            return false;
        }

        return $this->assignee_id === $employee->id
            || ($this->team !== null && $this->team->isLedBy($employee));
    }
}
