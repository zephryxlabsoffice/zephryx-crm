<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where somebody's pay goes.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE THREE IDENTIFIERS ARE ENCRYPTED, AND THIS CLASS NEVER MASKS THEM
 *
 * Encryption is about the database; masking is about the page. They are
 * different problems and the fix for one is not the fix for the other, so this
 * model returns the real values and App\Support\Sensitive decides what a given
 * viewer may see of them. A model that returned "•••• 4567" would be a model no
 * bank transfer file could be built from.
 *
 * Nothing hands an instance of this straight to a view.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class EmployeeBanking extends Model
{
    protected $table = 'employee_banking';

    protected $fillable = [
        'employee_id', 'bank_name', 'ifsc', 'account_number', 'pan', 'aadhaar',
    ];

    protected function casts(): array
    {
        return [
            // At rest, in the column. A database dump, a backup on a laptop and
            // a reporting replica are all places these end up otherwise, and
            // none of them has a masking layer.
            'account_number' => 'encrypted',
            'pan' => 'encrypted',
            'aadhaar' => 'encrypted',
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
     * The shape the salary pages read, before masking.
     *
     * @return array<string, string|null>
     */
    public function toRecordArray(): array
    {
        return [
            'bank' => $this->bank_name,
            'ifsc' => $this->ifsc,
            'account' => $this->account_number,
            'pan' => $this->pan,
            'aadhaar' => $this->aadhaar,
        ];
    }
}
