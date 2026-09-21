<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A team (foundation spec §12.1).
 */
class Team extends Model
{
    /**
     * The two states, and the order the filter offers them.
     *
     * Neither means "deleted": tasks and projects point at teams, so one that
     * stops being used is marked inactive and keeps everything it holds.
     * There used to be a third, `archived` — dropped 2026-09-21 (review round
     * decision) because nothing in this application ever read it differently
     * from `inactive`; see the migration that folded it in.
     *
     * @var list<string>
     */
    public const STATUSES = ['active', 'inactive'];

    protected $fillable = ['reference', 'name', 'purpose', 'lead_id', 'status', 'formed_on'];

    protected function casts(): array
    {
        return [
            'formed_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'lead_id');
    }

    /**
     * Who is in the team.
     *
     * Ordered by the pivot's own key so the list is stable between requests —
     * without it the order is whatever the database happens to return, and a
     * page that reshuffles on reload looks broken to the person reading it.
     *
     * @return BelongsToMany<Employee, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'team_members')
            ->withPivot(['id', 'joined_at'])
            ->withTimestamps()
            ->orderBy('team_members.id');
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * @param  Builder<Team>  $query
     * @return Builder<Team>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Whether this employee runs this team.
     *
     * The whole of the §2.6 rule that a Team Lead may act within their own team
     * and no further. Asked of the record rather than computed in a controller,
     * so every caller asks the same question the same way.
     */
    public function isLedBy(?Employee $employee): bool
    {
        return $employee !== null && $this->lead_id === $employee->id;
    }
}
