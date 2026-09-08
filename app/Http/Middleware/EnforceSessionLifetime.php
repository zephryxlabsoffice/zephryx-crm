<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Realm;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Session lifetime (foundation spec §4.4).
 *
 * | Property           | Staff / Client | Admin              |
 * |--------------------|----------------|--------------------|
 * | Absolute lifetime  | 7 days         | 7 days             |
 * | Idle timeout       | 30 days        | 2 hours            |
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * WHY THIS IS NOT `config('session.lifetime')`
 *
 * Laravel has one lifetime for the whole application, and §4.4 needs three
 * different things at once:
 *
 *   An ABSOLUTE ceiling. Laravel's lifetime is idle-based, so a session that is
 *   used daily lives forever. Seven days from issue means a stolen session
 *   cookie has a horizon whatever the thief does with it.
 *
 *   TWO idle timeouts. Thirty days is right for somebody who lives in this CRM
 *   and should not be asked to type a password because they took a holiday. Two
 *   hours is right for the account that can rewrite everybody's permissions,
 *   and applying the staff window to it because that is what the config says
 *   would be the wrong trade in the one place it matters most.
 *
 * Both stamps live in the session itself, so they travel with it and cannot
 * drift from it.
 * ─────────────────────────────────────────────────────────────────────────────
 * THE CEILING OUTLIVES NOTHING — READ THIS BEFORE "FIXING" THE 30 DAYS
 *
 * The staff idle window is longer than the absolute ceiling, so on its own it
 * never fires: a session hits seven days first. That is deliberate, not an
 * oversight to tidy up (decided 2026-09-08).
 *
 * Staying signed in for thirty days is remember-me's job (§4.5), and it is
 * better at it — the token rotates on every use, and a spent one coming back
 * revokes the whole chain as a theft signal. A session cookie has no such
 * tell, so a copied one must age out on its own; seven days is that horizon.
 *
 * The thirty days is what governs when the ceiling is not the binding
 * constraint — a session restored from remember-me is stamped fresh, and this
 * is the window it then idles against.
 * ═════════════════════════════════════════════════════════════════════════════
 *
 * Expiry signs the person out and sends them to the form with the "session
 * expired" notice, rather than dropping them on a 404 with no explanation.
 */
class EnforceSessionLifetime
{
    public const ISSUED_AT = 'auth.issued_at';
    public const LAST_SEEN = 'auth.last_seen';

    public const ABSOLUTE_DAYS = 7;
    public const IDLE_MINUTES = 30 * 24 * 60;
    public const ADMIN_IDLE_MINUTES = 2 * 60;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $session = $request->session();
        $now = now()->timestamp;

        $issuedAt = (int) $session->get(self::ISSUED_AT, $now);
        $lastSeen = (int) $session->get(self::LAST_SEEN, $now);

        $idleLimit = ($user->account_type === Realm::ADMIN ? self::ADMIN_IDLE_MINUTES : self::IDLE_MINUTES) * 60;

        $tooOld = $now - $issuedAt > self::ABSOLUTE_DAYS * 24 * 60 * 60;
        $tooIdle = $now - $lastSeen > $idleLimit;

        if ($tooOld || $tooIdle) {
            return $this->expire($request, $tooOld);
        }

        $session->put(self::ISSUED_AT, $issuedAt);
        $session->put(self::LAST_SEEN, $now);

        return $next($request);
    }

    /**
     * End the session and say why.
     *
     * The two reasons are worded differently on purpose. "Signed out after a
     * period of inactivity" is a normal thing that needs no thought; "your
     * session reached its maximum length" is unusual enough that somebody
     * seeing it repeatedly should ask why.
     */
    protected function expire(Request $request, bool $absolute): Response
    {
        auth()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', $absolute
                ? 'Your session reached its maximum length. Sign in again to continue.'
                : 'You were signed out after a period of inactivity. Sign in again to continue.')
            ->with('status_tone', 'info');
    }

    /**
     * Stamp a freshly issued session.
     *
     * Called from the sign-in flow rather than inferred here: a session that
     * has never been stamped is treated as issued now, which is right for the
     * first request of a new session and wrong as a way to reset the clock.
     */
    public static function stamp(Request $request): void
    {
        $request->session()->put(self::ISSUED_AT, now()->timestamp);
        $request->session()->put(self::LAST_SEEN, now()->timestamp);
    }
}
