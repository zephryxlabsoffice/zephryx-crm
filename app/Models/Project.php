<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A project (foundation spec §12.1).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * `progress` IS TYPED IN, AND THAT IS DELIBERATE FOR NOW
 *
 * The obvious improvement is to derive it from completed tasks. It is not done
 * here because Tasks has no table yet, and a percentage derived from an empty
 * table would report every project as 0% — which is worse than a number
 * somebody keeps honestly, because it looks computed.
 *
 * When Tasks lands this becomes a derived figure and the column goes.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class Project extends Model
{
    /** @var list<string> */
    public const STATUSES = ['planning', 'in_progress', 'review', 'on_hold', 'completed', 'cancelled'];

    /** @var list<string> */
    public const PRIORITIES = ['high', 'medium', 'low'];

    protected $fillable = [
        'reference', 'name', 'client_id', 'manager_id',
        'progress', 'status', 'priority', 'started_on', 'deadline',
    ];

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'deadline' => 'date',
            'progress' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    /**
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'project_teams')->withTimestamps();
    }

    /**
     * @return HasMany<ProjectUpdate, $this>
     */
    public function updates(): HasMany
    {
        return $this->hasMany(ProjectUpdate::class)->latest();
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * Still being worked on — the four states that are not an ending.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['completed', 'cancelled']);
    }

    /**
     * Past its deadline and not finished.
     *
     * A completed project that landed late is not an outstanding problem — it
     * is history — which is why this is not simply "deadline in the past".
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereDate('deadline', '<', now()->toDateString());
    }

    /**
     * The projects one person is on: managing it, or in a team assigned to it.
     *
     * Both, because "my projects" means the ones somebody is working on, and a
     * developer on the team is as much on the project as the manager of it.
     * The demo source answered with the manager only, which quietly excluded
     * everybody who does the work.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeForEmployee(Builder $query, ?Employee $employee): Builder
    {
        if ($employee === null) {
            // Somebody with no employment record — a Mentor, the owner — is on
            // no projects. An empty list, not an error.
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($employee) {
            $q->where('manager_id', $employee->id)
                ->orWhereHas('teams.members', fn (Builder $m) => $m->where('employees.id', $employee->id));
        });
    }
}
