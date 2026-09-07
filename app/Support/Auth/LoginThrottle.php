<?php

namespace App\Support\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sign-in rate limiting (foundation spec §4.2 step 1).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * PER IDENTIFIER AND PER IP. BOTH, ALWAYS.
 *
 * Either one alone fails in a way the other covers:
 *
 *   Per identifier only — an attacker spreads attempts for one account across
 *   a botnet and never trips it.
 *
 *   Per IP only — an attacker with one address rotates through every address
 *   in the company and gets a handful of guesses at each; meanwhile one office
 *   behind a single NAT locks out its own staff by getting their own passwords
 *   wrong.
 *
 * Two counters, two thresholds. The IP threshold is deliberately looser and
 * measured over a longer window, because a shared address is a legitimate
 * situation and a locked-out office is a worse outcome than a slowed-down
 * attacker.
 * ═════════════════════════════════════════════════════════════════════════════
 *
 * Counted from `login_attempts`, which is written for every attempt anyway
 * (§4.2 step 3, §6). A separate cache counter would be a second source of truth
 * that a cache flush silently resets — and a cache flush is not something the
 * lockout should survive being.
 */
class LoginThrottle
{
    /** Failures against one identifier before it is locked. */
    public const IDENTIFIER_LIMIT = 5;

    /** The window those failures are counted over, and the lockout length. */
    public const IDENTIFIER_WINDOW_MINUTES = 15;

    /**
     * Failures from one address before it is locked.
     *
     * Higher, and over a longer window: an office behind one NAT is a normal
     * thing, and locking it out is worse than slowing an attacker down.
     */
    public const IP_LIMIT = 20;

    public const IP_WINDOW_MINUTES = 60;

    /**
     * Seconds until this identifier or address may try again, or null.
     *
     * Returns the LONGER of the two locks. Answering with the shorter would
     * invite a retry that is refused for the other reason, which reads as the
     * countdown being wrong.
     */
    public function lockedFor(string $identifier, Request $request): ?int
    {
        $locks = array_filter([
            $this->lockFor('identifier', $identifier, self::IDENTIFIER_LIMIT, self::IDENTIFIER_WINDOW_MINUTES),
            $this->lockFor('ip_address', (string) $request->ip(), self::IP_LIMIT, self::IP_WINDOW_MINUTES),
        ]);

        return $locks === [] ? null : max($locks);
    }

    public function isLocked(string $identifier, Request $request): bool
    {
        return $this->lockedFor($identifier, $request) !== null;
    }

    /**
     * Seconds remaining on one counter's lock, or null if it is not tripped.
     */
    protected function lockFor(string $column, string $value, int $limit, int $windowMinutes): ?int
    {
        $since = now()->subMinutes($windowMinutes);

        $failures = DB::table('login_attempts')
            ->where($column, $value)
            ->where('succeeded', false)
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->pluck('created_at');

        if ($failures->count() < $limit) {
            return null;
        }

        /*
         * The lock lifts a full window after the OLDEST failure in the block,
         * not after the newest.
         *
         * Measuring from the newest would let somebody keep an account locked
         * indefinitely by failing once every few minutes — a denial of service
         * against a colleague that costs the attacker nothing.
         */
        $oldest = \Illuminate\Support\Carbon::parse($failures->last());
        $until = $oldest->addMinutes($windowMinutes);

        return $until->isFuture() ? (int) ceil(now()->diffInSeconds($until, false)) : null;
    }

    /**
     * Record an attempt (§4.2 step 3 — "Attempt logged").
     *
     * Successes are recorded too, and not only for the throttle: a successful
     * sign-in from an unfamiliar address is the thing somebody investigating an
     * incident actually needs, and a table holding only failures cannot show
     * it.
     */
    public function record(string $identifier, Request $request, bool $succeeded, ?string $reason = null, ?int $userId = null): void
    {
        DB::table('login_attempts')->insert([
            'identifier' => mb_substr($identifier, 0, 255),
            'user_id' => $userId,
            'ip_address' => $request->ip(),
            'succeeded' => $succeeded,
            'failure_reason' => $reason,
            'created_at' => now(),
        ]);
    }

    /**
     * Clear an identifier's failures after a successful sign-in.
     *
     * The identifier's only — never the IP's. Someone who guesses one password
     * correctly on a shared address must not thereby reset the counter
     * protecting everybody else behind it.
     */
    public function clear(string $identifier): void
    {
        DB::table('login_attempts')
            ->where('identifier', $identifier)
            ->where('succeeded', false)
            ->delete();
    }
}
