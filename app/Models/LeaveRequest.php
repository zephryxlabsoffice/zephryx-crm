<?php

namespace App\Models;

use App\Support\LeavePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One request for time off.
 */
class LeaveRequest extends Model
{
    protected $fillable = [
        'reference', 'employee_id', 'type', 'from_date', 'to_date', 'days',
        'reason', 'contact_number', 'status', 'decided_by', 'decided_at',
        'decision_note', 'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'days' => 'float',
            'decided_at' => 'datetime',
            'applied_at' => 'datetime',
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
     * @return BelongsTo<Employee, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'decided_by');
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * The row shape the presenter, the policy and the views all read.
     *
     * They predate this table and are tested against plain arrays — see
     * App\Support\LeaveDirectory for why the row shape is a contract rather
     * than an implementation detail.
     *
     * @return array<string, mixed>
     */
    public function toRecordArray(): array
    {
        return [
            'id' => $this->reference,
            'employee' => $this->employee?->user?->user_id,
            'type' => $this->type,
            // `from` and `to` are what the presenter reads; the date-suffixed
            // pair is kept beside them because the views use both names.
            'from' => $this->from_date->toDateString(),
            'to' => $this->to_date->toDateString(),
            'from_date' => $this->from_date->toDateString(),
            'to_date' => $this->to_date->toDateString(),
            'days' => $this->days,
            'reason' => $this->reason,
            'contact' => $this->contact_number,
            'status' => $this->status,
            'decided_by' => $this->decider?->user?->user_id,
            'decided_at' => $this->decided_at,
            'note' => $this->decision_note,
            'applied_at' => $this->applied_at,
        ];
    }

    /**
     * @param  Builder<LeaveRequest>  $query
     * @return Builder<LeaveRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', LeavePresenter::PENDING);
    }

    /**
     * @param  Builder<LeaveRequest>  $query
     * @return Builder<LeaveRequest>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', LeavePresenter::APPROVED);
    }

    /**
     * Requests covering any day in a range.
     *
     * Inclusive at both ends, matching LeavePresenter::overlaps — a request
     * ending the day another begins does not overlap, one ending the same day
     * another ends does.
     *
     * @param  Builder<LeaveRequest>  $query
     * @return Builder<LeaveRequest>
     */
    public function scopeOverlapping(Builder $query, string $from, string $to): Builder
    {
        return $query->whereDate('from_date', '<=', $to)->whereDate('to_date', '>=', $from);
    }
}
