<?php

namespace App\Support\Demo;

use Illuminate\Support\Collection;

/**
 * The lookup lists every other module reads — departments, designations, leave
 * types, holidays, document types (§8).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * NOTHING HERE IS DELETED. THINGS ARE DEACTIVATED.
 *
 * These lists are referenced by records that already exist. Twelve employees
 * point at a department; every approved leave request points at a leave type;
 * an attendance summary depends on which days were holidays.
 *
 * Deleting a row does not remove that history — it orphans it. An employee
 * whose department no longer exists renders a blank cell forever, and nobody
 * who reads that record later can find out what it used to say.
 *
 * So the only destructive act available is deactivation: the row stops being
 * offered for new records and keeps answering for old ones. It is the same
 * argument as an invoice number never leaving the sequence, and as a rejected
 * attendance record staying legible rather than being removed.
 *
 * `in_use` below is what makes the rule enforceable rather than advisory — the
 * screen states the count before you deactivate, so "3 employees are in this
 * department" is answered before the question is asked, not after.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class DemoMasterData
{
    public static function enabled(): bool
    {
        return app()->environment('local') && config('app.debug');
    }

    /**
     * @return array<string, array{label: string, note: string, unit: string}>
     */
    public static function lists(): array
    {
        return [
            'departments' => [
                'label' => 'Departments',
                'note' => 'Every employee belongs to one. Referenced by the directory, the '
                    .'attendance roll and the announcements audience.',
                'unit' => 'employee',
            ],
            'designations' => [
                'label' => 'Designations',
                'note' => 'Job titles. A display label — designations carry no rank and grant '
                    .'nothing (§2.3).',
                'unit' => 'employee',
            ],
            'leave-types' => [
                'label' => 'Leave types',
                'note' => 'Their annual entitlements live in Settings, because changing a number '
                    .'of days moves everybody\'s balance.',
                'unit' => 'request',
            ],
            'document-types' => [
                'label' => 'Document types',
                'note' => 'What may be attached to an employee profile. Every one of these is '
                    .'downloaded through an authorising route, never a static path.',
                'unit' => 'document',
            ],
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function rows(string $list): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        return collect(match ($list) {
            'departments' => self::departments(),
            'designations' => self::designations(),
            'leave-types' => self::leaveTypes(),
            'document-types' => self::documentTypes(),
            default => [],
        });
    }

    public static function has(?string $list): bool
    {
        return $list !== null && array_key_exists($list, self::lists());
    }

    /**
     * Departments, with the headcount that makes deactivation a real decision.
     *
     * Counted from DemoEmployees rather than stored, so the number on the
     * screen cannot disagree with the directory behind it.
     *
     * @return list<array<string, mixed>>
     */
    protected static function departments(): array
    {
        $counts = DemoEmployees::all()->countBy('department');

        $rows = [];

        foreach (['Design', 'Development', 'Marketing', 'HR', 'Finance', 'Support', 'Sales'] as $name) {
            $rows[] = [
                'name' => $name,
                'code' => strtoupper(substr(str_replace(' ', '', $name), 0, 3)),
                'active' => true,
                'in_use' => $counts[$name] ?? 0,
            ];
        }

        // Kept deliberately: a deactivated row nobody is in, so the screen shows
        // both states and the difference between them is visible.
        $rows[] = ['name' => 'Operations', 'code' => 'OPS', 'active' => false, 'in_use' => 0];

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function designations(): array
    {
        $counts = DemoEmployees::all()->countBy('designation');

        return collect($counts)
            ->map(fn (int $count, string $name) => [
                'name' => $name,
                'code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name) ?? '', 0, 4)),
                'active' => true,
                'in_use' => $count,
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function leaveTypes(): array
    {
        $counts = DemoLeave::all()->countBy('type');

        $rows = [];

        foreach (\App\Support\LeavePolicy::types() as $key => $type) {
            $rows[] = [
                'name' => $type['label'],
                'code' => strtoupper($key),
                'active' => true,
                'in_use' => $counts[$key] ?? 0,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function documentTypes(): array
    {
        return [
            ['name' => 'PAN card', 'code' => 'PAN', 'active' => true, 'in_use' => 9],
            ['name' => 'Aadhaar', 'code' => 'AADH', 'active' => true, 'in_use' => 9],
            ['name' => 'Offer letter', 'code' => 'OFFER', 'active' => true, 'in_use' => 12],
            ['name' => 'Educational certificate', 'code' => 'EDU', 'active' => true, 'in_use' => 7],
            ['name' => 'Previous payslip', 'code' => 'PPAY', 'active' => false, 'in_use' => 2],
        ];
    }
}
