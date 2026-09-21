<?php

namespace App\Models;

use App\Support\AnnouncementPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One authored post on the board.
 *
 * Milestones share the board and are not rows here — see the migration.
 */
class Announcement extends Model
{
    protected $fillable = [
        'reference', 'title', 'body', 'category', 'author_id',
        'audience', 'audience_department_id', 'for_clients',
        'starts_on', 'ends_on', 'observed_from', 'observed_to',
        'published_at', 'pinned',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'observed_from' => 'date',
            'observed_to' => 'date',
            'published_at' => 'datetime',
            'pinned' => 'boolean',
            'for_clients' => 'boolean',
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
                ? \App\Support\EmployeeDirectory::row($this->author)
                : null,
            'audience' => $this->audience,
            'audience_value' => $this->audienceDepartment?->name,
            'for_clients' => $this->for_clients,
            'draft' => $this->isDraft(),
            'pinned' => $this->pinned,
            'published_at' => $this->published_at?->toDateTimeString()
                // A draft still has a date it would go up on, which is what the
                // managing table sorts and schedules by.
                ?? $this->starts_on->copy()->setTime(9, 0)->toDateTimeString(),
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
     * Published, started, and not yet expired — what is on the board today.
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
     * Live, and marked for the client board too.
     *
     * `audience`/`audience_department_id` narrow which STAFF see a post and
     * mean nothing to a client account, so this reads `for_clients` alone —
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
