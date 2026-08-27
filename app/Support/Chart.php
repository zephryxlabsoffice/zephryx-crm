<?php

namespace App\Support;

/**
 * Geometry and palette for the donut charts.
 *
 * Shared: Employees breaks down by department, Teams breaks down by the
 * departments represented in a team. One implementation, so the two cannot
 * drift apart.
 */
class Chart
{
    /** Matches the `.dot-*` classes in components/chart.css. */
    public const PALETTE = 8;

    /**
     * One segment of a donut.
     *
     * An SVG circle draws its stroke from three o'clock; the container is
     * rotated -90deg so the first segment starts at the top. `offsetShare` is
     * the running total of everything drawn before this segment.
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
     * Palette position, cycling so master data of any size gets a colour.
     */
    public static function dot(int $index, int $palette = self::PALETTE): string
    {
        return 'dot-'.(($index % $palette) + 1);
    }

    /**
     * Group rows by a field into the shape the donut and legend both consume.
     *
     * @param  iterable<int, array<string, mixed>>  $rows
     * @return list<array{name: string, count: int, share: float}>
     */
    public static function breakdown(iterable $rows, string $field): array
    {
        $collection = collect($rows);
        $total = $collection->count();

        if ($total === 0) {
            return [];
        }

        return $collection
            ->groupBy($field)
            ->map(fn ($group, $name) => [
                'name' => (string) $name,
                'count' => $group->count(),
                'share' => round($group->count() / $total * 100, 1),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }
}
