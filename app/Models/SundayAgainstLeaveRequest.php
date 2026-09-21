<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request to work a Sunday already covered by approved leave.
 *
 * Asked BEFORE working the day (decided 2026-09-11) — there is no route that
 * creates one of these after the fact. Approving it reduces the linked leave
 * request's `days` by one and rosters the employee for the date — see
 * AttendanceController::decideSundayAgainstLeave.
 */
class SundayAgainstLeaveRequest extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected $fillable = [
        'employee_id', 'leave_request_id', 'date', 'status',
        'requested_at', 'decided_by', 'decided_at', 'decision_note',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
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
     * @return BelongsTo<LeaveRequest, $this>
     */
    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'decided_by');
    }
}
