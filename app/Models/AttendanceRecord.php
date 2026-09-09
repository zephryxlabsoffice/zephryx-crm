<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One person's one day.
 *
 * The reference — `ATT-2026-09-09-EMP002` — is composed rather than stored,
 * because it IS the two columns that identify the row. A stored copy could
 * disagree with them, and there is nothing it could say that they do not.
 */
class AttendanceRecord extends Model
{
    protected $fillable = [
        'employee_id', 'date', 'check_in', 'check_out',
        'rejected_at', 'rejected_by', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'rejected_at' => 'datetime',
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
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'rejected_by');
    }

    /**
     * The id the pages and URLs carry.
     */
    public function reference(): string
    {
        return self::referenceFor($this->date->toDateString(), (string) $this->employee?->user?->user_id);
    }

    public static function referenceFor(string $date, string $staffId): string
    {
        return 'ATT-'.$date.'-'.$staffId;
    }

    /**
     * Pull a reference apart, or null if it is not one.
     *
     * @return array{0: string, 1: string}|null  [date, staff id]
     */
    public static function parseReference(string $reference): ?array
    {
        if (preg_match('/^ATT-(\d{4}-\d{2}-\d{2})-([A-Za-z0-9-]{1,16})$/', $reference, $m) !== 1) {
            return null;
        }

        return [$m[1], $m[2]];
    }

    /**
     * The row shape App\Support\AttendancePolicy reads.
     *
     * Kept in the model because the policy is the thing every page's judgement
     * goes through, and it takes plain arrays — its tests are written against
     * that shape and predate this table.
     *
     * @return array<string, mixed>
     */
    public function toRecordArray(): array
    {
        return [
            'id' => $this->reference(),
            'employee' => $this->employee?->user?->user_id,
            'date' => $this->date->toDateString(),
            'check_in' => $this->clock($this->check_in),
            'check_out' => $this->clock($this->check_out),
            'rejected_at' => $this->rejected_at,
            'rejected_by' => $this->rejecter?->user?->user_id,
            'rejection_reason' => $this->rejection_reason,
        ];
    }

    /**
     * `09:21`, whatever shape the driver hands back.
     *
     * SQLite returns the string it was given and MySQL returns 'HH:MM:SS', so
     * without this the same record renders as "09:21" on one machine and
     * "09:21:00" on another — and the policy parses both but the page shows the
     * difference.
     */
    protected function clock(?string $time): ?string
    {
        return $time === null ? null : Carbon::parse($time)->format('H:i');
    }

    /**
     * Days still open — checked into and not out of.
     *
     * @param  Builder<AttendanceRecord>  $query
     * @return Builder<AttendanceRecord>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('check_out');
    }

    /**
     * Records a PERSON rejected — not the ones the clock closed on, which are
     * derived and have no column to filter by.
     *
     * @param  Builder<AttendanceRecord>  $query
     * @return Builder<AttendanceRecord>
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->whereNotNull('rejected_at');
    }
}
