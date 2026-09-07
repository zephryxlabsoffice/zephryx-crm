<?php

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Self-service password reset (foundation spec §4.6).
 *
 * Single-use token, stored hashed, 60-minute expiry. Applying a reset
 * invalidates the token, all sessions, all remember-me tokens and all device
 * trust for that user.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE TOKEN CARRIES THE USER, AND THE FORM DOES NOT DECIDE WHO IS RESET
 *
 * The reset form posts a token and a new password. It also posts an identifier,
 * because the page shows one — and that identifier is used for NOTHING. The
 * account being reset is the one the token belongs to.
 *
 * If the identifier were trusted, anybody with a valid token for their own
 * account could reset somebody else's by editing one field. That is the whole
 * vulnerability, it is a single line of code, and it is the reason `consume()`
 * takes only a token.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class PasswordResets
{
    public const EXPIRY_MINUTES = 60;

    /**
     * Issue a token, returning the plaintext for the mail.
     *
     * Every earlier live token for the user is retired first. Two working reset
     * links at once means the older email — often the one forwarded, screenshot
     * or left in a shared inbox — stays live after somebody has already used
     * the newer.
     */
    public function issue(User $user, Request $request): string
    {
        $this->invalidateAll($user);

        $token = Str::random(64);

        DB::table('password_resets')->insert([
            'user_id' => $user->id,
            'token_hash' => $this->hash($token),
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $token;
    }

    /**
     * The user a live token belongs to, or null.
     *
     * Used to render the reset form — a link that is already expired should say
     * so on arrival rather than after somebody has typed a new password twice.
     */
    public function userFor(string $token): ?User
    {
        $record = $this->live($token);

        return $record === null ? null : User::find($record->user_id);
    }

    /**
     * Spend a token. Returns the user it belonged to, or null.
     *
     * Takes ONLY the token. See the head of this class.
     */
    public function consume(string $token): ?User
    {
        $record = $this->live($token);

        if ($record === null) {
            return null;
        }

        $user = User::find($record->user_id);

        if ($user === null) {
            return null;
        }

        DB::table('password_resets')
            ->where('id', $record->id)
            ->update(['consumed_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    protected function live(string $token): ?object
    {
        return DB::table('password_resets')
            ->where('token_hash', $this->hash($token))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();
    }

    public function invalidateAll(User $user): void
    {
        DB::table('password_resets')
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now(), 'updated_at' => now()]);
    }

    /**
     * A fast hash, not bcrypt.
     *
     * 64 random characters from a CSPRNG — nothing to guess, so a deliberately
     * slow hash buys nothing. What matters is that the stored value cannot be
     * reversed into a working link, and sha256 gives that.
     */
    protected function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function expiresAt(): Carbon
    {
        return now()->addMinutes(self::EXPIRY_MINUTES);
    }
}
