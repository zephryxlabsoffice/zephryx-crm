<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Somebody asked for one person's End of Day for one date urgently. See App\Support\EodUrgent.
 */
class EodUrgentRequest extends Model
{
    protected $fillable = ['employee_id', 'report_date', 'requested_by', 'fulfilled_at'];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'fulfilled_at' => 'datetime',
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

    public function requesterName(): string
    {
        return $this->requester?->name ?? 'Your manager';
    }
}
