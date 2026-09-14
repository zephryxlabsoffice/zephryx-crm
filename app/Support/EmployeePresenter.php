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
     * The engagement types, and how they read on a page.
     *
     * Full-time carries no chip anywhere it is the norm — a directory where
     * every second row says "Full-time" has taught the reader to skip the
     * column the interns and freelancers are in.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    protected const EMPLOYMENT_TYPES = [
        'full_time' => ['pill-gray', 'Full-time'],
        'intern' => ['pill-blue', 'Intern'],
        'freelance' => ['pill-purple', 'Freelance'],
    ];

    /**
     * @return array{tone: string, label: string}
     */
    public static function employmentType(?string $type): array
    {
        [$tone, $label] = self::EMPLOYMENT_TYPES[$type] ?? ['pill-gray', 'Full-time'];

        return ['tone' => $tone, 'label' => $label];
    }

    /**
     * The dropdown, as value => label.
     *
     * @return array<string, string>
     */
    public static function employmentTypeOptions(): array
    {
        return array_map(fn (array $row) => $row[1], self::EMPLOYMENT_TYPES);
    }

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
     * Donut geometry and palette are shared with Teams — see App\Support\Chart.
     *
     * @return array{dash: string, offset: string}
     */
    public static function donutSegment(float $share, float $offsetShare, float $circumference): array
    {
        return Chart::donutSegment($share, $offsetShare, $circumference);
    }

    public static function dot(int $index, int $palette = Chart::PALETTE): string
    {
        return Chart::dot($index, $palette);
    }
}
