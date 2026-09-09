<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One end-of-day update written against a project.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THERE IS NO SCOPE HERE THAT RETURNS BOTH KINDS TO A CLIENT
 *
 * `clientVisible` is the only scope, and the client portal has no way to read
 * an update except through it. That is the same shape as DemoClientPortal's:
 * the rule §6 names — "enforced at the query layer, not in the view" — is kept
 * by not providing the call that could break it.
 *
 * The staff side reads the relation directly, because internal notes are the
 * point of the staff side.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class ProjectUpdate extends Model
{
    public const INTERNAL = 'internal';

    public const CLIENT = 'client';

    protected $fillable = [
        'reference', 'project_id', 'author_id', 'title', 'body', 'visibility', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
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
     * @return BelongsTo<Employee, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'author_id');
    }

    /**
     * The updates a client may read.
     *
     * @param  Builder<ProjectUpdate>  $query
     * @return Builder<ProjectUpdate>
     */
    public function scopeClientVisible(Builder $query): Builder
    {
        return $query->where('visibility', self::CLIENT);
    }

    /**
     * Whether this has ever been in front of the client.
     *
     * Not the same question as "is it client-visible now". Turning visibility
     * back to internal is housekeeping, not a recall — if they have read it,
     * they have read it — and no screen may imply otherwise.
     */
    public function wasPublished(): bool
    {
        return $this->published_at !== null;
    }
}
