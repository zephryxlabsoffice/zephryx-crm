<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's one month of pay.
 *
 * Three facts and no arithmetic: the payslip somebody produced elsewhere, the
 * net figure that document states, and when the transfer was made.
 */
class SalaryRecord extends Model
{
    protected $fillable = [
        'employee_id', 'period', 'net_minor', 'currency',
        'payslip_path', 'payslip_name', 'payslip_bytes', 'payslip_added_at', 'payslip_added_by',
        'paid_on', 'method', 'paid_by',
    ];

    protected function casts(): array
    {
        return [
            'net_minor' => 'integer',
            'payslip_added_at' => 'datetime',
            'paid_on' => 'date',
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
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'payslip_added_by');
    }

    /**
     * The net as money, or null when no payslip has been added.
     *
     * Never a float, at any point. See App\Support\Money.
     */
    public function net(): ?Money
    {
        return $this->net_minor === null ? null : Money::of($this->net_minor, $this->currency);
    }

    /**
     * The row shape the salary pages and SalaryPresenter read.
     *
     * @return array<string, mixed>
     */
    public function toRecordArray(): array
    {
        return [
            'id' => $this->period.'-'.$this->employee?->user?->user_id,
            'period' => $this->period,
            'employee' => $this->employee?->user?->user_id,
            'currency' => $this->currency,
            'net' => $this->net(),
            /*
             * Keyed on the NAME, not the stored path. A record can say a
             * payslip was added and have no file behind it — a demo row, or a
             * month imported from wherever pay was recorded before this — and
             * that is a truthful state rather than a broken one. `file` is what
             * decides whether a download link is drawn.
             */
            'payslip' => $this->payslip_name === null ? null : [
                'name' => $this->payslip_name,
                'size' => $this->payslipSize(),
                'added_on' => $this->payslip_added_at?->toDateString(),
                'added_by' => $this->addedBy?->user?->name ?? 'Unknown',
                'file' => $this->payslip_path !== null,
            ],
            'paid_on' => $this->paid_on?->toDateString(),
            'method' => $this->method,
        ];
    }

    /**
     * `68 KB`. Rounded for a person, not for arithmetic — nothing sums these.
     */
    public function payslipSize(): ?string
    {
        if ($this->payslip_bytes === null) {
            return null;
        }

        return $this->payslip_bytes < 1024 * 1024
            ? max(1, (int) round($this->payslip_bytes / 1024)).' KB'
            : round($this->payslip_bytes / 1024 / 1024, 1).' MB';
    }

    /**
     * Records with a payslip and no payment yet — what a payroll run acts on.
     *
     * @param  Builder<SalaryRecord>  $query
     * @return Builder<SalaryRecord>
     */
    public function scopePayable(Builder $query): Builder
    {
        return $query->whereNotNull('net_minor')->whereNull('paid_on');
    }
}
