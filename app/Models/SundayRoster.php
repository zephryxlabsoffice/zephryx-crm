<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person, rostered to work a Sunday or holiday.
 *
 * A row existing IS the roster — there is no status column and no approval
 * (decided 2026-09-11). See the migration for why a Sunday-against-leave
 * approval also produces a row here.
 */
class SundayRoster extends Model
{
    protected $fillable = ['employee_id', 'date', 'rostered_by'];

    protected function casts(): array
    {
        return ['date' => 'date'];
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
    public function rosterer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'rostered_by');
    }
}
