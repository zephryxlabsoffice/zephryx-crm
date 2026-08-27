<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Turns a team row into the things its pages need to draw it.
 */
class TeamPresenter
{
    /** @var array<string, array{0: string, 1: string}> */
    protected const STATUSES = [
        'active' => ['pill-green', 'Active'],
        'inactive' => ['pill-red', 'Inactive'],
        'archived' => ['pill-gray', 'Archived'],
    ];

    /**
     * @return array{tone: string, label: string}
     */
    public static function status(string $status): array
    {
        [$tone, $label] = self::STATUSES[$status] ?? ['pill-gray', ucfirst($status)];

        return ['tone' => $tone, 'label' => $label];
    }

    /**
     * @return list<string>
     */
    public static function statusOptions(): array
    {
        return array_keys(self::STATUSES);
    }

    /**
     * Initials for a team's chip: one letter per significant word, two at most.
     * "Web Development Team" → WD, not WDT — the trailing "Team" is on every
     * one of them and distinguishes nothing.
     */
    public static function chip(string $name): string
    {
        $words = array_values(array_filter(
            preg_split('/\s+/', trim($name)) ?: [],
            // The empty check matters: preg_split on an empty string yields
            // one empty element, which would otherwise pass the filter and
            // produce a blank chip.
            fn (string $word) => $word !== ''
                && ! in_array(mb_strtolower($word), ['team', 'the', 'and', '&', 'of'], true)
        ));

        if ($words === []) {
            return mb_strtoupper(mb_substr(trim($name), 0, 2)) ?: '??';
        }

        if (count($words) === 1) {
            return mb_strtoupper(mb_substr($words[0], 0, 2));
        }

        return mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1));
    }

    public static function created(string $date): string
    {
        return Carbon::parse($date)->format('d M Y');
    }

    public static function age(string $date): string
    {
        return Carbon::parse($date)->diffForHumans(syntax: Carbon::DIFF_ABSOLUTE);
    }

    /**
     * Mean time the current members have been with the company.
     *
     * The handover showed "Average tenure 3.2 months" as a fixed figure; it is
     * computed here so it cannot drift from the data beside it.
     *
     * @param  list<array<string, mixed>>  $members
     */
    public static function averageTenure(array $members): string
    {
        if ($members === []) {
            return '—';
        }

        $days = collect($members)
            ->map(fn (array $member) => Carbon::parse($member['joined'])->diffInDays(now()))
            ->avg();

        return Carbon::now()->subDays((int) round($days))->diffForHumans(syntax: Carbon::DIFF_ABSOLUTE);
    }
}
