<?php

namespace App\Support\Admin;

use App\Models\MasterDataItem;
use Illuminate\Support\Collection;

/**
 * The lookup lists, read from `master_data_items`.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * NOTHING HERE IS DELETED. THINGS ARE DEACTIVATED.
 *
 * These lists are referenced by records that already exist. Every employee
 * points at a department and a designation; every leave request names a type.
 *
 * Deleting a row does not remove that history — it orphans it. An employee
 * whose department no longer exists renders a blank cell forever, and nobody
 * reading that record later can find out what it used to say. So the only
 * destructive act is deactivation: the row stops being offered for new records
 * and keeps answering for old ones. The same argument as an invoice number
 * never leaving the sequence, and a rejected attendance record staying legible.
 *
 * `in_use` is what makes that rule enforceable rather than advisory — the
 * screen states the count before you retire something.
 *
 * AND `in_use` CAN BE NULL, WHICH IS NOT ZERO
 *
 * Zero means "nothing points at this row, retiring it costs nothing". Null
 * means "nobody counted", and displaying it as zero would turn an unknown into
 * a reassurance. See MasterDataItem::inUse and `unitFor` below.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class MasterDataDirectory
{
    /**
     * The four lists, and what each one is for.
     *
     * Keyed by the same constants MasterDataItem uses, so a list cannot exist
     * on the screen and not in the table.
     *
     * @return array<string, array{label: string, note: string, unit: string}>
     */
    public static function lists(): array
    {
        return [
            MasterDataItem::DEPARTMENTS => [
                'label' => 'Departments',
                'note' => 'Every employee belongs to one. Referenced by the directory, the '
                    .'attendance roll and the announcements audience.',
                'unit' => 'employee',
            ],
            MasterDataItem::DESIGNATIONS => [
                'label' => 'Designations',
                'note' => 'Job titles. A display label — designations carry no rank and grant '
                    .'nothing (§2.3).',
                'unit' => 'employee',
            ],
            MasterDataItem::LEAVE_TYPES => [
                'label' => 'Leave types',
                'note' => 'Their annual entitlements live in Settings, because changing a number '
                    .'of days moves everybody\'s balance.',
                'unit' => 'request',
            ],
            MasterDataItem::DOCUMENT_TYPES => [
                'label' => 'Document types',
                'note' => 'What may be attached to an employee profile. Every one of these is '
                    .'downloaded through an authorising route, never a static path.',
                'unit' => 'document',
            ],
            MasterDataItem::TICKET_CATEGORIES => [
                'label' => 'Ticket categories',
                'note' => 'The support team\'s own labels for what a ticket is about.',
                'unit' => 'ticket',
            ],
            MasterDataItem::ANNOUNCEMENT_CATEGORIES => [
                'label' => 'Announcement categories',
                'note' => 'One of these — "holiday" — closes the office on its observed dates; '
                    .'see Announcements. "Milestone" is generated, never posted by hand, and does '
                    .'not appear on the compose form however it is set here.',
                'unit' => 'announcement',
            ],
        ];
    }

    public static function has(?string $list): bool
    {
        return $list !== null && array_key_exists($list, self::lists());
    }

    /**
     * One list's rows, retired ones last.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function rows(string $list): Collection
    {
        return MasterDataItem::query()
            ->inList($list)
            ->get()
            ->map(fn (MasterDataItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'code' => $item->code,
                'active' => $item->is_active,
                'in_use' => $item->inUse(),
            ])
            ->sortByDesc('active')
            ->values();
    }

    /**
     * The overview: one summary per list.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function overview(): array
    {
        $lists = [];

        foreach (self::lists() as $key => $meta) {
            $rows = self::rows($key);

            $lists[$key] = $meta + [
                'key' => $key,
                'total' => $rows->count(),
                'active' => $rows->where('active', true)->count(),
                // A list with nothing in it is a module that cannot be used —
                // worth surfacing on the overview rather than discovering when
                // somebody tries to add an employee.
                'empty' => $rows->isEmpty(),
            ];
        }

        return $lists;
    }

    /**
     * How many records the whole list is answering for.
     *
     * Null when the list is not countable at all, so the rail can say so
     * instead of printing a zero that reads as "safe".
     */
    public static function totalInUse(string $list): ?int
    {
        $counts = self::rows($list)->pluck('in_use');

        return $counts->every(fn ($n) => $n === null) ? null : (int) $counts->sum();
    }
}
