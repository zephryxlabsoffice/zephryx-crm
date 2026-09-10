<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One row of one master data list — a department, a designation, a leave type,
 * a document type.
 *
 * The four lists share a table because they share a shape and a screen; `list`
 * is the discriminator. See the migration for why that is one table and not
 * four.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * DEACTIVATION IS NOT DELETION, AND `in_use` IS WHY
 *
 * The admin screen states the count before you deactivate — "3 employees are in
 * this department" — so the question is answered before it is asked. That count
 * has to be real: a number computed from a stale cache would be a reassurance
 * rather than a fact.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class MasterDataItem extends Model
{
    public const DEPARTMENTS = 'departments';
    public const DESIGNATIONS = 'designations';
    public const LEAVE_TYPES = 'leave-types';
    public const DOCUMENT_TYPES = 'document-types';

    protected $fillable = ['list', 'name', 'code', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The lists this table holds, and the only values `list` may take.
     *
     * @return list<string>
     */
    public static function lists(): array
    {
        return [self::DEPARTMENTS, self::DESIGNATIONS, self::LEAVE_TYPES, self::DOCUMENT_TYPES];
    }

    /**
     * @param  Builder<MasterDataItem>  $query
     * @return Builder<MasterDataItem>
     */
    public function scopeInList(Builder $query, string $list): Builder
    {
        return $query->where('list', $list)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @param  Builder<MasterDataItem>  $query
     * @return Builder<MasterDataItem>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * How many records point at this row.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THREE LISTS ARE COUNTABLE AND ONE IS NOT, AND THE NULL IS THE POINT
     *
     * Departments and designations are foreign keys on `employees`, so the
     * count is a join. Leave types are matched on `leave_requests.type`, which
     * holds the policy KEY — the seeder writes the code as its uppercase, so
     * the two meet here and nowhere else. Not a foreign key, and worth saying
     * out loud: it is the one list whose link to its records is a string
     * convention.
     *
     * DOCUMENT TYPES ARE COUNTED BY NOTHING, AND THAT IS A REAL GAP.
     *
     * My Profile files a document against EmployeeDocument::KINDS — a fixed
     * four — rather than against this list, so no record anywhere points at
     * these rows. Returning null rather than 0 is the honest answer: 0 reads as
     * "nothing uses this, retiring it costs nothing", and the truth is that
     * nobody knows because nothing is looking. Recorded for the review round
     * rather than patched over by making the profile upload read this list,
     * which is a product decision about what a document type IS.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function inUse(): ?int
    {
        return match ($this->list) {
            self::DEPARTMENTS => Employee::where('department_id', $this->id)->count(),
            self::DESIGNATIONS => Employee::where('designation_id', $this->id)->count(),
            self::LEAVE_TYPES => LeaveRequest::where('type', mb_strtolower($this->code))->count(),
            default => null,
        };
    }
}
