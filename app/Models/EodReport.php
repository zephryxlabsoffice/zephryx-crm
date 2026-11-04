<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One person's end of day report for one date. See App\Http\Controllers\EodController.
 */
class EodReport extends Model
{
    /** Reading everybody's, not just the ones sent to you. */
    public const VIEW_ALL_PERMISSION = 'tasks.eod.all';

    protected $fillable = ['employee_id', 'report_date', 'submitted_at'];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'submitted_at' => 'datetime',
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
     * @return HasMany<EodItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(EodItem::class)->orderBy('id');
    }

    /**
     * @return BelongsToMany<Employee, $this>
     */
    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'eod_recipients')->withTimestamps();
    }

    /**
     * Filed after the day it reports on.
     */
    public function wasFiledLate(): bool
    {
        return $this->submitted_at->toDateString() > $this->report_date->toDateString();
    }
}
