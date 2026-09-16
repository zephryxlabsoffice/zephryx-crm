<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request to correct one's own details, waiting on HR.
 *
 * Written and read through App\Support\Profile\ProfileChanges. Nothing else
 * should apply one: applying is a write to the live record, a file move and an
 * audit entry, and the version where two call sites each do two of the three is
 * the version where a photo is accepted and the record is not.
 *
 * `changes` is a map of field to proposed value, and the fields in it are
 * checked against ProfilePolicy::requestable() both when it is written and when
 * it is applied. A stored field name that later names a column to write is only
 * safe while the list it is checked against is the same list at both ends.
 */
class ProfileChangeRequest extends Model
{
    protected $fillable = [
        'employee_id', 'requested_by', 'changes', 'photo_path',
        'applied_at', 'rejected_at', 'cancelled_at', 'decided_by', 'decision_note',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'applied_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * Still waiting on somebody.
     *
     * No expiry, unlike an email change. That one expires because a live token
     * is a credential and an old one is a liability; this carries nothing an
     * attacker could use, and a request quietly expiring would mean somebody
     * who brought their documents in finding the form empty and being asked to
     * fill it in again.
     *
     * @param  Builder<ProfileChangeRequest>  $query
     * @return Builder<ProfileChangeRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query
            ->whereNull('applied_at')
            ->whereNull('rejected_at')
            ->whereNull('cancelled_at');
    }

    public function isPending(): bool
    {
        return $this->applied_at === null
            && $this->rejected_at === null
            && $this->cancelled_at === null;
    }

    /**
     * What happened to it, for a page that lists spent ones.
     */
    public function outcome(): string
    {
        return match (true) {
            $this->applied_at !== null => 'applied',
            $this->rejected_at !== null => 'declined',
            $this->cancelled_at !== null => 'withdrawn',
            default => 'pending',
        };
    }
}
