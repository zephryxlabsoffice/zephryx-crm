<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
    /*
     * The kind of engagement, decided 2026-09-11.
     *
     * Named constants rather than loose strings because three separate rules
     * turn on this value — the staff ID's type digit, whether attendance and
     * leave apply at all, and which fields are required when somebody is added
     * — and a typo in any one of them fails silently towards the wrong answer.
     */
    public const FULL_TIME = 'full_time';

    public const INTERN = 'intern';

    /** No attendance, no leave, no payroll: paid against work, not time. */
    public const FREELANCE = 'freelance';

    /** @var list<string> */
    public const TYPES = [self::FULL_TIME, self::INTERN, self::FREELANCE];

    /**
     * Who the time-and-attendance rules apply to.
     *
     * Freelancers are out by decision, not by oversight — they have no clock,
     * no leave balance and no comp-off.
     *
     * @var list<string>
     */
    public const ATTENDS = [self::FULL_TIME, self::INTERN];

    protected $fillable = [
        'user_id', 'department_id', 'designation_id', 'reports_to',
        'employment_type', 'joined_on', 'date_of_birth', 'announce_milestones',
    ];

    protected $attributes = [
        'employment_type' => self::FULL_TIME,
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
     * The people the clock and the leave balance apply to.
     *
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function scopeAttends(Builder $query): Builder
    {
        return $query->whereIn('employment_type', self::ATTENDS);
    }

    /**
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('employment_type', $type);
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
     * The fields this person owns about themselves.
     *
     * A person who has never opened My Profile has no row here, and that is a
     * real state rather than a missing one — see EmployeeProfile::blank().
     *
     * @return HasOne<EmployeeProfile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    /**
     * @return HasMany<EmployeeDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class)->orderByDesc('id');
    }

    /**
     * The reporting line. HR's, shown on the profile and editable nowhere on it.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reports_to');
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
