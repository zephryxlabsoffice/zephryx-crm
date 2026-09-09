<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * The employment record behind a staff account.
 *
 * Identity — name, email, whether the account may sign in — lives on `users`.
 * This holds what is true of somebody's employment and nothing else, which is
 * why there is no `name` here to fall out of step with the one people log in
 * with.
 */
class Employee extends Model
{
    protected $fillable = [
        'user_id', 'department_id', 'designation_id',
        'joined_on', 'date_of_birth', 'announce_milestones',
    ];

    protected function casts(): array
    {
        return [
            'joined_on' => 'date',
            'date_of_birth' => 'date',
            'announce_milestones' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<MasterDataItem, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(MasterDataItem::class, 'department_id');
    }

    /**
     * @return BelongsTo<MasterDataItem, $this>
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(MasterDataItem::class, 'designation_id');
    }

    /**
     * The teams this person belongs to.
     *
     * Membership, not leadership. Somebody can lead a team they are not a
     * member of — unusual, and a real state the data allows — so `/teams/mine`
     * asks this and the ownership check on a write asks Team::isLedBy.
     *
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members')->withTimestamps();
    }

    /**
     * Employees whose account is active — the directory's default.
     *
     * Joined rather than filtered in PHP so the count on the page and the rows
     * under it come from one query and cannot disagree.
     *
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereHas('user', fn (Builder $user) => $user->where('status', 'active'));
    }

    /**
     * Started within the given month.
     *
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function scopeJoinedIn(Builder $query, Carbon $month): Builder
    {
        /*
         * Bounded by instants, not by date strings. A `date` cast writes
         * 'Y-m-d H:i:s', so a row saved from endOfMonth() holds
         * '2026-09-30 23:59:59' — which compares GREATER than the string
         * '2026-09-30' and drops anybody who joined on the last day of the
         * month out of the count, silently and only in some months.
         */
        return $query->whereBetween('joined_on', [
            $month->copy()->startOfMonth()->startOfDay(),
            $month->copy()->endOfMonth()->endOfDay(),
        ]);
    }
}
