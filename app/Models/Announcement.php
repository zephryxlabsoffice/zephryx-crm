<?php

namespace App\Models;

use App\Support\AnnouncementPresenter;
use App\Support\EmployeeDirectory;
use App\Support\Rbac\Rbac;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One authored post on the board.
 *
 * Milestones share the board and are not rows here - see the migration.
 */
class Announcement extends Model
{
    protected $fillable = [
        'reference', 'title', 'body', 'details', 'category', 'author_id',
        'audience', 'audience_department_id', 'for_clients', 'notify_email', 'delivery_pending',
        'starts_on', 'ends_on', 'observed_from', 'observed_to',
        'published_at', 'pinned',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'observed_from' => 'date',
            'observed_to' => 'date',
            'published_at' => 'datetime',
            'pinned' => 'boolean',
            'for_clients' => 'boolean',
            'notify_email' => 'boolean',
            'delivery_pending' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'author_id');
    }

    /**
     * @return BelongsTo<MasterDataItem, $this>
     */
    public function audienceDepartment(): BelongsTo
    {
        return $this->belongsTo(MasterDataItem::class, 'audience_department_id');
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function isDraft(): bool
    {
        return $this->published_at === null;
    }

    /**
     * When it goes on the board.
     *
     * Pressing Post on a future-dated notice stamps `published_at` today, and the
     * notice still waits for `starts_on`, so the start date wins while it is ahead.
     * A draft still has a date it would go up on, which is what the managing table
     * sorts and schedules by.
     */
    protected function goesUpAt(): Carbon
    {
        if ($this->published_at === null) {
            return $this->starts_on->copy()->setTime(9, 0);
        }

        return $this->starts_on->isFuture() ? $this->starts_on->copy()->startOfDay() : $this->published_at;
    }

    /**
     * The row shape the presenter, the board and Holidays all read.
     *
     * @return array<string, mixed>
     */
    public function toRecordArray(): array
    {
        $announcement = [
            'id' => $this->reference,
            'kind' => 'authored',
            'title' => $this->title,
            'body' => $this->body,
            'category' => $this->category,
            'author' => $this->author?->user?->user_id,
            'author_record' => $this->author
                ? EmployeeDirectory::row($this->author)
                : null,
            'audience' => $this->audience,
            'audience_value' => $this->audienceDepartment?->name,
            'for_clients' => $this->for_clients,
            'draft' => $this->isDraft(),
            'pinned' => $this->pinned,
            'published_at' => $this->goesUpAt()->toDateTimeString(),
            'expires_at' => $this->ends_on?->toDateString(),
            // The days the office is shut, which is NOT the window the notice is
            // up for. Null on everything that is not a closure.
            'observed_from' => $this->observed_from?->toDateString(),
            'observed_to' => $this->observed_to?->toDateString(),
        ];

        $announcement['status'] = AnnouncementPresenter::statusOf($announcement);

        return $announcement;
    }

    /**
     * Published posts only.
     *
     * What Holidays reads, and the reason a draft closes nothing.
     *
     * @param  Builder<Announcement>  $query
     * @return Builder<Announcement>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at');
    }

    /**
     * Published, started, and not yet expired - what is on the board today.
     *
     * @param  Builder<Announcement>  $query
     * @return Builder<Announcement>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->published()
            ->whereDate('starts_on', '<=', now()->toDateString())
            ->where(function (Builder $q) {
                $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', now()->toDateString());
            });
    }

    /**
     * What one person may read.
     *
     * Whoever may post manages the whole board, drafts included. Everyone else
     * gets what has gone up (expired notices stay readable, because the attendance
     * calendar links to them) and is addressed to them: everyone, their own
     * department or, for a manager, managers only.
     *
     * @param  Builder<Announcement>  $query
     * @return Builder<Announcement>
     */
    public function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        if ($viewer !== null && app(Rbac::class)->can($viewer, config('announcements.post_permission'))) {
            return $query;
        }

        $departmentId = $viewer === null ? null : Employee::where('user_id', $viewer->id)->value('department_id');
        $isManager = $viewer?->roles->contains('role_key', 'manager') ?? false;

        return $query->published()
            ->whereDate('starts_on', '<=', now()->toDateString())
            ->where(function (Builder $q) use ($departmentId, $isManager) {
                $q->where('audience', 'everyone')
                    ->when($departmentId !== null, fn (Builder $q) => $q->orWhere(fn (Builder $q) => $q
                        ->where('audience', 'department')
                        ->where('audience_department_id', $departmentId)))
                    ->when($isManager, fn (Builder $q) => $q->orWhere('audience', 'managers'));
            });
    }

    /**
     * Posted, nobody told yet, and its start date has come.
     *
     * @param  Builder<Announcement>  $query
     * @return Builder<Announcement>
     */
    public function scopeDueForDelivery(Builder $query): Builder
    {
        return $query->published()
            ->where('delivery_pending', true)
            ->whereDate('starts_on', '<=', now()->toDateString());
    }

    /**
     * Live, and marked for the client board too.
     *
     * `audience`/`audience_department_id` narrow which STAFF see a post and
     * mean nothing to a client account, so this reads `for_clients` alone -
     * a post can be both "everyone" internally and on the client board, or
     * "managers only" internally and still on it.
     *
     * @param  Builder<Announcement>  $query
     * @return Builder<Announcement>
     */
    public function scopeForClientBoard(Builder $query): Builder
    {
        return $query->live()->where('for_clients', true);
    }
}
