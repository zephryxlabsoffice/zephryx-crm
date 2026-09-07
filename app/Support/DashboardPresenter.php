<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Wording and formatting for the dashboard.
 *
 * Kept out of the Blade files for the usual reason — a rule written in one
 * place is a rule, and the same rule written in fourteen widget partials is
 * fourteen chances to word a count differently.
 */
class DashboardPresenter
{
    /**
     * "Good morning", and the boundaries are the office's, not UTC's.
     *
     * Carbon reads config('app.timezone'), which is Asia/Kolkata — see the note
     * in config/app.php about why that is a config value and not an env one. A
     * dashboard that says "Good evening" at half past nine in the morning is a
     * small thing that makes the whole page feel like it belongs to somebody
     * else's company.
     */
    public static function greeting(?Carbon $now = null): string
    {
        $hour = ($now ?? Carbon::now())->hour;

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };
    }

    /**
     * The name to greet somebody by.
     *
     * First name only. "Good morning, Amit" is how a colleague speaks; "Good
     * morning, Amit Verma" is how a bank does.
     */
    public static function firstName(string $name): string
    {
        $first = trim(explode(' ', trim($name))[0] ?? '');

        return $first !== '' ? $first : 'there';
    }

    /**
     * A comparison against a previous period, in whole units and words.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * NOT A PERCENTAGE. The handover put "12.5% vs last month" under every KPI
     * tile, all four hardcoded. The shape is wrong even with real data behind
     * it: a percentage change on a count of two late days is a hundred per
     * cent, which reads as a crisis and means one day.
     *
     * Whole units, said in words, or nothing at all. This matches
     * resources/views/attendance/partials/mine-kpis.blade.php, which reached
     * the same conclusion first.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @return array{label: string, tone: string}
     */
    public static function delta(int $now, int $before, string $unit, string $period = 'last month'): array
    {
        $difference = $now - $before;

        if ($difference === 0) {
            return ['label' => 'Same as '.$period, 'tone' => ''];
        }

        $direction = $difference > 0 ? 'more' : 'fewer';

        return [
            'label' => self::count(abs($difference), $unit).' '.$direction.' than '.$period,
            'tone' => '',
        ];
    }

    /**
     * "1 task", "3 tasks", and "no tasks" rather than "0 tasks".
     *
     * Zero gets a word because zero is the answer people most often skim past,
     * and "no tasks overdue" reads as good news where "0 tasks overdue" reads
     * as a widget that failed to load.
     */
    public static function count(int $n, string $singular, ?string $plural = null): string
    {
        $plural ??= $singular.'s';

        return match ($n) {
            0 => 'No '.$plural,
            1 => '1 '.$singular,
            default => $n.' '.$plural,
        };
    }

    /**
     * The date under the greeting: "Monday, 07 September".
     */
    public static function today(?Carbon $now = null): string
    {
        return ($now ?? Carbon::now())->format('l, d F');
    }
}
