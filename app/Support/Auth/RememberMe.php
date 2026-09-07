<?php

namespace App\Support\Auth;

use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Realm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Remember me (foundation spec §4.5).
 *
 * A rotating token, 30-day expiry, stored hashed. Each use issues a fresh one
 * and retires the previous; presenting an already-used token revokes the whole
 * chain as a theft signal.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * WHY ROTATION, AND WHY THE RETIRED TOKEN IS THE INTERESTING ONE
 *
 * A static remember-me cookie is a password with a 30-day life and no way to
 * tell it has been copied. Rotation fixes the second half: the legitimate
 * browser advances to a new token on every use, so a copy taken at any point
 * goes stale the next time the real owner visits.
 *
 * The stale token is then the alarm. When one is presented, exactly one of two
 * things has happened — the token was stolen and is being replayed, or it was
 * stolen and the thief got there first and the real owner is now replaying.
 * There is no way to tell which, and no need to: both mean the chain is
 * compromised, so the whole chain is revoked and both parties have to sign in
 * properly.
 *
 * Refusing just that one request would be the wrong answer. It leaves the
 * thief's fresh token working.
 * ═════════════════════════════════════════════════════════════════════════════
 *
 * NEVER THE ADMIN ACCOUNT (§4.5), and issuing is refused rather than skipped —
 * a silent no-op would leave somebody believing they were remembered.
 *
 * Restoring a session never bypasses the OTP on an untrusted device. The two
 * are independent: this says who you are, device trust says whether a code is
 * needed, and both are asked.
 */
class RememberMe
{
    public const COOKIE = 'zx_remember';
    public const DAYS = 30;

    public function __construct(private AuditLog $audit)
    {
    }

    /**
     * Issue a token for a new chain, or rotate an existing one.
     *
     * @throws \LogicException on the admin account
     */
    public function issue(User $user, Request $request, ?string $previous = null): Cookie
    {
        if ($user->account_type === Realm::ADMIN) {
            /*
             * §4.5 is a flat prohibition. Thrown rather than silently skipped:
             * a caller that reaches this has a bug, and returning a cookie-less
             * success would hide it behind a user who quietly has to sign in
             * every time and assumes the feature is broken.
             */
            throw new \LogicException('Remember-me is never issued to the admin account (§4.5).');
        }

        $token = Str::random(64);

        DB::table('remember_tokens')->insert([
            'user_id' => $user->id,
            'token_hash' => $this->hash($token),
            'previous_token_hash' => $previous === null ? null : $this->hash($previous),
            'expires_at' => now()->addDays(self::DAYS),
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
     * Resolve a remember-me cookie to its user, rotating the token.
     *
     * Returns null when there is nothing valid to restore. A theft signal also
     * returns null — after revoking the chain and writing an audit entry.
     *
     * @return array{user: User, cookie: Cookie}|null
     */
    public function resolve(Request $request): ?array
    {
        $token = $request->cookie(self::COOKIE);

        if (! is_string($token) || $token === '') {
            return null;
        }

        $hash = $this->hash($token);

        $record = DB::table('remember_tokens')->where('token_hash', $hash)->first();

        if ($record === null) {
            return null;
        }

        $user = User::find($record->user_id);

        if ($user === null || ! $user->isActive()) {
            return null;
        }

        /*
         * A token that has already been rotated past. See the head of this
         * class: this is the theft signal, and the response is to burn the
         * whole chain rather than refuse one request.
         */
        if ($record->used_at !== null) {
            $this->revokeAll($user);

            $this->audit->record(
                action: AuditLog::REMEMBER_THEFT,
                actor: $user,
                entityType: 'user',
                entityId: $user->user_id,
                after: 'A retired remember-me token was presented. Every token for this account was revoked.',
                request: $request,
            );

            return null;
        }

        if (\Illuminate\Support\Carbon::parse($record->expires_at)->isPast()) {
            return null;
        }

        DB::table('remember_tokens')
            ->where('id', $record->id)
            ->update(['used_at' => now(), 'updated_at' => now()]);

        return ['user' => $user, 'cookie' => $this->issue($user, $request, previous: $token)];
    }

    /**
     * Revoke every token for a user.
     *
     * Explicit logout, password change and reset all call this (§4.4, §4.6).
     */
    public function revokeAll(User $user): void
    {
        DB::table('remember_tokens')->where('user_id', $user->id)->delete();
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
