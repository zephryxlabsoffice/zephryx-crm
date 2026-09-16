<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The standing agreement about somebody's pay.
 *
 * An INPUT to the payslip HR prepares in a spreadsheet, never an output of this
 * application — see the migration for why that distinction is the whole design.
 * Nothing here is added up into a monthly figure, and no page shows it beside a
 * payslip: the two would disagree in any month with a deduction in it.
 */
class EmployeeSalaryStructure extends Model
{
    /** Full-time: the six components a payslip is built from. */
    public const BREAKDOWN = 'breakdown';

    /** An intern: one monthly amount, and no fiction about HRA. */
    public const STIPEND = 'stipend';

    /** A freelancer: what was agreed, for a record. Paid outside the CRM. */
    public const RATE = 'rate';

    /** @var list<string> */
    public const KINDS = [self::BREAKDOWN, self::STIPEND, self::RATE];

    /** @var list<string> */
    public const BASES = ['project', 'hour'];

    protected $fillable = [
        'employee_id', 'kind', 'currency',
        'basic_minor', 'hra_minor', 'allowances_minor',
        'pf_minor', 'pt_minor', 'tds_minor',
        'stipend_minor',
        'rate_minor', 'rate_basis',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'basic_minor' => 'integer',
            'hra_minor' => 'integer',
            'allowances_minor' => 'integer',
            'pf_minor' => 'integer',
            'pt_minor' => 'integer',
            'tds_minor' => 'integer',
            'stipend_minor' => 'integer',
            'rate_minor' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
