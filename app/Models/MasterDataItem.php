<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

/**
 * One row of one master data list - a department, a designation, a leave
 * type, a document type, a ticket category, an announcement category.
 *
 * The lists share a table because they share a shape and a screen; `list` is
 * the discriminator. See the migration for why that is one table and not one
 * per list. Ticket categories and announcement categories joined the other
 * four in the review round (decided 2026-09-21) - both used to be
 * `config()` arrays, and both still validate against exactly this table now.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * DEACTIVATION IS NOT DELETION, AND `in_use` IS WHY
 *
 * The admin screen states the count before you deactivate - "3 employees are in
 * this department" - so the question is answered before it is asked. That count
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
    public const TICKET_CATEGORIES = 'ticket-categories';
    public const ANNOUNCEMENT_CATEGORIES = 'announcement-categories';

    protected $fillable = ['list', 'name', 'code', 'department_id', 'is_active', 'sort_order'];

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
        return [
            self::DEPARTMENTS, self::DESIGNATIONS, self::LEAVE_TYPES, self::DOCUMENT_TYPES,
            self::TICKET_CATEGORIES, self::ANNOUNCEMENT_CATEGORIES,
        ];
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
     * The department a designation belongs to. Null on every other list.
     *
     * @return BelongsTo<MasterDataItem, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(self::class, 'department_id');
    }

    /**
     * The department to save for somebody, given the designation chosen for them.
     *
     * An empty department takes the designation's. A different one is refused,
     * unless the pair is what the record already says: an old mismatch should not
     * stop somebody correcting a phone number. A designation not placed yet, or
     * placed in a retired department, asks nothing of the choice.
     */
    public static function departmentForJob(?int $designationId, ?int $departmentId, ?Employee $existing = null): ?int
    {
        $designation = $designationId === null ? null : self::with('department')->find($designationId);
        $home = $designation?->department;

        if ($home === null || ! $home->is_active || $departmentId === $home->id) {
            return $departmentId;
        }

        if ($departmentId === null) {
            return $home->id;
        }

        if ($existing !== null && $existing->designation_id === $designationId && $existing->department_id === $departmentId) {
            return $departmentId;
        }

        throw ValidationException::withMessages([
            'department_id' => $designation->name.' belongs to '.$home->name.'. Choose that department, or move the designation in Admin > Master data.',
        ]);
    }

    /**
     * How many records point at this row.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * FIVE LISTS ARE COUNTABLE AND ONE IS NOT, AND THE NULL IS THE POINT
     *
     * Departments and designations are foreign keys on `employees`, so the
     * count is a join. Leave types, ticket categories and announcement
     * categories are all matched on a string held by the record - not a
     * foreign key, and worth saying out loud: renaming one of these rows does
     * not move existing records with it, the same as renaming a department
     * would not either (the join makes that automatic; the string convention
     * does not).
     *
     * DOCUMENT TYPES ARE COUNTED BY NOTHING, AND THAT IS A REAL GAP.
     *
     * My Profile files a document against EmployeeDocument::KINDS - a fixed
     * four - rather than against this list, so no record anywhere points at
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
            // Tickets store the category as the label itself - the same
            // free-text shape config('tickets.categories') always was, so the
            // move to master data changes where the list lives, not what a
            // ticket's own column holds.
            self::TICKET_CATEGORIES => Ticket::where('category', $this->name)->count(),
            // Announcements store the CODE, lowercased - the same convention
            // leave types use, and the one that keeps AnnouncementPresenter::
            // HOLIDAY and the 'milestone' special case stable across the move.
            self::ANNOUNCEMENT_CATEGORIES => Announcement::where('category', mb_strtolower($this->code))->count(),
            default => null,
        };
    }
}
