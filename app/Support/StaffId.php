<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * The identifier a person is known by — `ZEPH261001`.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE SCHEME (decided 2026-09-11, replacing `EMP001`)
 *
 *   ZEPH 26 1 001
 *   ───┬ ─┬ ┬ ─┬─
 *      │  │ │  └─ NNN, restarting at 001 each year within each series
 *      │  │ └──── D: 1 full-time, 2 intern, 3 freelance
 *      │  └────── YY: the year the RECORD IS CREATED
 *      └───────── the company
 *
 * Mentors and clients hold no employment, so neither the year nor the type
 * means anything for them: `ZEPH4NNN` and `ZEPH5NNN`, one running series each.
 *
 * THE NUMBER COMES FROM THE HIGHEST, NEVER FROM A COUNT
 *
 * A count reissues an identifier the moment a row is removed, and an identifier
 * that has belonged to two people makes every audit entry naming it ambiguous.
 * This carried over from the `EMP` generator it replaces, for that same reason.
 *
 * THE YEAR IS THE RECORD'S, NOT THE JOINING DATE'S
 *
 * Joining dates are backdated — somebody is entered in March for a February
 * start, and a record created in 2027 for a 2024 joiner is not unusual. Keyed
 * to the joining year, such a record would be handed a number that year had
 * already issued.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class StaffId
{
    public const PREFIX = 'ZEPH';

    /**
     * The type digit, by employment type.
     *
     * @var array<string, int>
     */
    public const EMPLOYMENT_DIGITS = [
        Employee::FULL_TIME => 1,
        Employee::INTERN => 2,
        Employee::FREELANCE => 3,
    ];

    public const MENTOR_DIGIT = 4;

    public const CLIENT_DIGIT = 5;

    /**
     * The next identifier for somebody joining on this kind of engagement.
     */
    public static function forEmployee(string $employmentType): string
    {
        $digit = self::EMPLOYMENT_DIGITS[$employmentType] ?? null;

        if ($digit === null) {
            /*
             * Rather than defaulting to full-time. A wrong type digit is
             * invisible afterwards — it reads as a perfectly ordinary
             * identifier — and nothing downstream would ever question it.
             */
            throw new InvalidArgumentException(
                'Unknown employment type ['.$employmentType.']. Expected one of: '
                .implode(', ', array_keys(self::EMPLOYMENT_DIGITS)).'.'
            );
        }

        return self::next(self::PREFIX.Carbon::now()->format('y').$digit);
    }

    public static function forMentor(): string
    {
        return self::next(self::PREFIX.self::MENTOR_DIGIT);
    }

    public static function forClient(): string
    {
        return self::next(self::PREFIX.self::CLIENT_DIGIT);
    }

    /**
     * The next number in one series, padded to three digits.
     *
     * Scoped to the prefix, so the series never read each other: a mentor at
     * `ZEPH4009` must not push the next employee to 010, and last year's
     * numbers must not push this year's.
     */
    protected static function next(string $prefix): string
    {
        $highest = User::query()
            ->where('user_id', 'like', $prefix.'%')
            /*
             * `cast(... as integer)` on the tail, so `ZEPH261010` sorts above
             * `ZEPH261009`. A string `max()` would not: '9' is greater than
             * '1' character by character, and the generator would start
             * handing out numbers it had already issued at exactly the point
             * the series reached double figures.
             */
            ->selectRaw('max(cast(substr(user_id, ?) as integer)) as n', [strlen($prefix) + 1])
            ->value('n');

        return $prefix.str_pad((string) (((int) $highest) + 1), 3, '0', STR_PAD_LEFT);
    }
}
