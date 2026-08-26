<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Turns an employee row into the things the list needs to draw it.
 *
 * Kept out of the view so the status vocabulary lives in one place: when the
 * Employees migration lands with a real status enum, only this file changes.
 */
class EmployeePresenter
{
    /** @var array<string, array{0: string, 1: string}> */
    protected const STATUSES = [
        'active' => ['pill-green', 'Active'],
        'on_leave' => ['pill-amber', 'On Leave'],
        'inactive' => ['pill-gray', 'Inactive'],
        'suspended' => ['pill-red', 'Suspended'],
    ];

    /**
     * @return array{tone: string, label: string}
     */
    public static function status(string $status): array
    {
        [$tone, $label] = self::STATUSES[$status] ?? ['pill-gray', ucfirst(str_replace('_', ' ', $status))];

        return ['tone' => $tone, 'label' => $label];
    }

    /**
     * @return list<string>
     */
    public static function statusOptions(): array
    {
        return array_keys(self::STATUSES);
    }

    public static function joined(string $date): string
    {
        return Carbon::parse($date)->format('d M Y');
    }

    /**
     * The donut's geometry for one segment.
     *
     * An SVG circle draws its stroke from three o'clock; the container is
     * rotated -90deg so the first segment starts at the top. `offset` is the
     * running total of everything before this segment.
     *
     * Returned as attribute values rather than CSS, because an inline `style`
     * attribute is blocked by our Content-Security-Policy (§6) while SVG
     * presentation attributes are not.
     *
     * @return array{dash: string, offset: string}
     */
    public static function donutSegment(float $share, float $offsetShare, float $circumference): array
    {
        $length = $circumference * ($share / 100);

        return [
            'dash' => round($length, 2).' '.round($circumference - $length, 2),
            'offset' => (string) round(-$circumference * ($offsetShare / 100), 2),
        ];
    }

    /**
     * Palette position for a department, cycling so master data of any size
     * always gets a colour. See `.dot-*` in pages/employees.css.
     */
    public static function dot(int $index, int $palette = 8): string
    {
        return 'dot-'.(($index % $palette) + 1);
    }
}
