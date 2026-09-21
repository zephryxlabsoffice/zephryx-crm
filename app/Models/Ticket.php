<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A ticket — somebody here asking for something, or a client asking us.
 */
class Ticket extends Model
{
    /** @var list<string> */
    public const STATUSES = ['unassigned', 'open', 'in_progress', 'escalated', 'resolved', 'closed'];

    /** @var list<string> */
    public const TYPES = ['internal', 'client'];

    /** @var list<string> */
    public const PRIORITIES = ['high', 'medium', 'low'];

    protected $fillable = [
        'reference', 'type', 'subject', 'description', 'raised_by', 'client_id',
        'project_id', 'assignee_id', 'status', 'priority', 'category', 'department',
        'escalated_by', 'escalated_at', 'resolved_at', 'converted_task_id',
    ];

    protected function casts(): array
    {
        return [
            'escalated_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function raiser(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'raised_by');
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assignee_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function escalator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'escalated_by');
    }

    /**
     * The task this ticket became, if it has been converted.
     *
     * @return BelongsTo<Task, $this>
     */
    public function convertedTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'converted_task_id');
    }

    /**
     * The whole thread, internal notes included.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THIS RELATION IS THE STAFF VIEW AND ONLY THE STAFF VIEW
     *
     * The client portal must never reach it. What the portal calls is
     * `publicComments()`, which cannot return an internal note — the same shape
     * as ProjectUpdate::clientVisible, and for the same reason: the rule in §6
     * is kept by not providing the call that could break it.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @return HasMany<TicketComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->oldest();
    }

    /**
     * The thread as a client may read it.
     *
     * @return HasMany<TicketComment, $this>
     */
    public function publicComments(): HasMany
    {
        return $this->comments()->where('visibility', TicketComment::PUBLIC);
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * Nobody has picked it up — the triage queue.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->where('status', 'unassigned');
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeEscalated(Builder $query): Builder
    {
        return $query->where('status', 'escalated');
    }

    /**
     * Still needing somebody — not resolved, not closed.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['resolved', 'closed']);
    }
}
