<?php

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Device trust (foundation spec §4.3).
 *
 * After a code is verified the device is trusted for seven days, during which
 * sign-in needs the password only. Trust is cleared by explicit logout, by a
 * password change, and by manual revocation.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * WHAT TRUST DOES AND DOES NOT SKIP
 *
 * It skips the CODE. It never skips the PASSWORD. A trusted laptop that is
 * stolen is a laptop somebody still cannot sign in from.
 *
 * That is the whole reason this is safe to have: the cookie is not a
 * credential on its own, it is a statement that this browser has already proved
 * it can read the account's email. Losing it costs an extra code; stealing it
 * costs an attacker nothing they did not already need.
 * ═════════════════════════════════════════════════════════════════════════════
 *
 * The user agent and IP on the row are for recognising a device in a list and
 * for making revocation meaningful. They are deliberately NOT part of the
 * check: an address changes every time somebody moves between home and the
 * office, and refusing trust on that basis would make it useless.
 */
class TrustedDevices
{
    public const COOKIE = 'zx_device';
    public const DAYS = 7;

    /**
     * Whether this request arrives from a device this user has trusted.
     *
     * Looked up by hash, never by plaintext. The cookie's value is hashed the
     * same way it was stored, which is why the column can be indexed at all —
     * see the head of the auth migration.
     */
    public function trusts(User $user, Request $request): bool
    {
        $token = $request->cookie(self::COOKIE);

        if (! is_string($token) || $token === '') {
            return false;
        }

        return DB::table('trusted_devices')
            ->where('user_id', $user->id)
            ->where('token_hash', $this->hash($token))
            ->whereNull('revoked_at')
            ->where('trusted_until', '>', now())
            ->exists();
    }

    /**
     * Trust this device, returning the cookie to attach to the response.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * A SHA-256 HASH, NOT bcrypt.
     *
     * Password hashes are deliberately slow, which is right for something a
     * human chose and an attacker can guess. This token is 40 random bytes from
     * a CSPRNG — there is nothing to guess, so slowness buys nothing and costs
     * a bcrypt verification on every page load. The property that matters is
     * that the stored value cannot be reversed, and a fast hash gives that.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function trust(User $user, Request $request): Cookie
    {
        $token = Str::random(64);

        DB::table('trusted_devices')->insert([
            'user_id' => $user->id,
            'token_hash' => $this->hash($token),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
            'ip_address' => $request->ip(),
            'trusted_until' => now()->addDays(self::DAYS),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return cookie(
            name: self::COOKIE,
            value: $token,
            minutes: self::DAYS * 24 * 60,
            secure: $request->isSecure(),
            httpOnly: true,
            sameSite: 'lax',
        );
    }

    /**
     * Revoke the device this request came from.
     *
     * Explicit logout clears trust (§4.3), so signing out on a shared machine
     * really does leave nothing behind.
     */
    public function revokeCurrent(User $user, Request $request): void
    {
        $token = $request->cookie(self::COOKIE);

        if (! is_string($token) || $token === '') {
            return;
        }

        DB::table('trusted_devices')
            ->where('user_id', $user->id)
            ->where('token_hash', $this->hash($token))
            ->update(['revoked_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Revoke every device for a user.
     *
     * Called on a password change and on a reset (§4.6). Somebody changing
     * their password because they think it is compromised expects that to end
     * every other session and every remembered device — a change that left an
     * attacker's laptop trusted would be worse than useless, because it would
     * feel like it worked.
     */
    public function revokeAll(User $user): void
    {
        DB::table('trusted_devices')
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'updated_at' => now()]);
    }

    public function forget(): Cookie
    {
        return cookie()->forget(self::COOKIE);
    }

    protected function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
