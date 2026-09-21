<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One day's comp-off, earned by working a rostered Sunday or holiday in
 * full.
 *
 * `status` has no `lapsed` value — see App\Support\CompOffPolicy, which
 * derives it from `expires_on` the same way AttendancePolicy derives an
 * auto-rejection from the ten-hour window: because the clock passed it, not
 * because anything stamped it.
 */
class CompOff extends Model
{
    public const AVAILABLE = 'available';

    public const PENDING = 'pending';

    public const TAKEN = 'taken';

    public const REJECTED = 'rejected';

    protected $fillable = [
        'employee_id', 'earned_on', 'expires_on', 'status',
        'take_date', 'requested_at', 'decided_by', 'decided_at', 'decision_note',
    ];

    protected function casts(): array
    {
        return [
            'earned_on' => 'date',
            'expires_on' => 'date',
            'take_date' => 'date',
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
     * @return BelongsTo<Employee, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'decided_by');
    }
}
