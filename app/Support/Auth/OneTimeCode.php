<?php

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * The email one-time code (foundation spec §4.3).
 *
 * Six digits, ten minutes, five attempts, single use — and it applies to every
 * account type including the owner's.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE ARITHMETIC THAT MAKES SIX DIGITS SAFE
 *
 * A six-digit code is one of a million. Five guesses is a 1-in-200,000 chance,
 * which is fine. Five guesses PER CODE with unlimited resends is not — a
 * hundred resends buys five hundred guesses at a fresh target each time.
 *
 * So `issue()` invalidates every live code for the user before writing a new
 * one. There is never more than one code that will open the door, and the
 * attempt counter that protects it cannot be reset by asking for another.
 * ═════════════════════════════════════════════════════════════════════════════
 *
 * Codes are stored hashed and the plaintext exists only in the email. A dump of
 * `two_factor_codes` hands over nothing.
 */
class OneTimeCode
{
    public const LENGTH = 6;
    public const EXPIRY_MINUTES = 10;
    public const MAX_ATTEMPTS = 5;

    /** Seconds before another code may be requested. */
    public const RESEND_COOLDOWN_SECONDS = 60;

    /**
     * Issue a code, returning the plaintext for the mailer.
     *
     * The only place the plaintext exists. It is returned rather than mailed
     * here so that sending is the caller's decision — and so a test can verify
     * the code without reading mail.
     */
    public function issue(User $user, Request $request): string
    {
        // One live code at a time. See the head of this class.
        $this->invalidateAll($user);

        $code = $this->generate();

        DB::table('two_factor_codes')->insert([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
            'attempts' => 0,
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $code;
    }

    /**
     * Six digits, from a cryptographically secure source, leading zeros kept.
     *
     * `random_int`, not `rand` or `mt_rand`: the others are predictable from a
     * few observed outputs, and a predictable second factor is not one.
     */
    protected function generate(): string
    {
        return str_pad((string) random_int(0, 999999), self::LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Check a code. Consumes it on success; counts the attempt on failure.
     *
     * Returns false for every kind of failure — wrong, expired, exhausted, none
     * issued. The verify page says only that the code was not accepted, for the
     * same reason the sign-in form says only "invalid credentials": the
     * differences are useful to an attacker and to nobody else.
     */
    public function verify(User $user, string $code): bool
    {
        $record = $this->live($user);

        if ($record === null) {
            return false;
        }

        if ($record->attempts >= self::MAX_ATTEMPTS) {
            // Burn it rather than leaving an exhausted row that a later attempt
            // would keep failing against with no explanation.
            $this->invalidateAll($user);

            return false;
        }

        if (! Hash::check($code, $record->code_hash)) {
            DB::table('two_factor_codes')->where('id', $record->id)->increment('attempts');

            return false;
        }

        DB::table('two_factor_codes')
            ->where('id', $record->id)
            ->update(['consumed_at' => now(), 'updated_at' => now()]);

        return true;
    }

    /**
     * Seconds before another code may be requested, or zero.
     *
     * §4.3 requires resend to be rate-limited. Measured from the live code's
     * creation, so the cooldown cannot be sidestepped by letting one expire.
     */
    public function resendCooldown(User $user): int
    {
        $record = $this->live($user);

        if ($record === null) {
            return 0;
        }

        $ready = \Illuminate\Support\Carbon::parse($record->created_at)
            ->addSeconds(self::RESEND_COOLDOWN_SECONDS);

        return $ready->isFuture() ? (int) ceil(now()->diffInSeconds($ready, false)) : 0;
    }

    public function hasLiveCode(User $user): bool
    {
        return $this->live($user) !== null;
    }

    /**
     * The user's current unconsumed, unexpired code.
     */
    protected function live(User $user): ?object
    {
        return DB::table('two_factor_codes')
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Retire every live code for a user.
     *
     * Marked consumed rather than deleted: the row is evidence that a code was
     * issued and when, which is worth keeping when somebody asks why they got
     * an email they did not expect.
     */
    public function invalidateAll(User $user): void
    {
        DB::table('two_factor_codes')
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now(), 'updated_at' => now()]);
    }

    /**
     * The address a code was sent to, masked for the verify page.
     *
     * Shown so somebody can tell which of their addresses to check. Masked
     * because the page is reachable with only a password — revealing the full
     * address there would leak it to anybody who got that far.
     */
    public static function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        $visible = mb_substr($name, 0, 1);

        return $visible.str_repeat('•', max(3, mb_strlen($name) - 1)).'@'.$domain;
    }
}
