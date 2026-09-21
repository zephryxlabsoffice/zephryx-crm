<?php

namespace App\Support;

use App\Models\CompOff;
use Illuminate\Support\Carbon;

/**
 * The comp-off rule, and the arithmetic over it.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ONE DAY EARNS ONE COMP-OFF, WHOLE OR NOTHING
 *
 * "Half a Sunday earns nothing — only a full day earns one" (the decision
 * that overrides an earlier, looser answer). There is no fractional amount
 * anywhere in this class for exactly that reason.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * "LAPSED" IS DERIVED, NEVER STAMPED
 *
 * The same rule AttendancePolicy::autoRejected follows for the ten-hour
 * window: a comp-off past its expiry reads as lapsed because the clock
 * passed it, not because a job ran overnight and wrote a flag. See
 * `isLapsed()` — it is the only place this fact is decided, and every page
 * that draws a comp-off's status asks it rather than trusting the stored
 * `status` column alone.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class CompOffPolicy
{
    /**
     * The date a comp-off earned on `$earnedOn` expires — the NEXT Sunday
     * after it, exclusive of the date itself even when `$earnedOn` is
     * already a Sunday.
     */
    public static function expiresOn(Carbon|string $earnedOn): Carbon
    {
        return Carbon::parse($earnedOn)->next(Carbon::SUNDAY);
    }

    /**
     * Whether an available comp-off has run past its expiry.
     *
     * Only meaningful for one still `available` — a `taken` or `rejected`
     * row has already left that question behind.
     */
    public static function isLapsed(CompOff $compOff, ?Carbon $asOf = null): bool
    {
        if ($compOff->status !== CompOff::AVAILABLE) {
            return false;
        }

        $asOf ??= Carbon::today();

        return $compOff->expires_on->lessThan($asOf->startOfDay());
    }

    /**
     * Whether a comp-off may be requested for taking right now.
     */
    public static function isTakeable(CompOff $compOff, ?Carbon $asOf = null): bool
    {
        return $compOff->status === CompOff::AVAILABLE && ! self::isLapsed($compOff, $asOf);
    }
}
