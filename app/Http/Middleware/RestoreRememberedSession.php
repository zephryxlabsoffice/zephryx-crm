<?php

namespace App\Http\Middleware;

use App\Support\Auth\RememberMe;
use App\Support\Auth\TrustedDevices;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remember me, on the way in (foundation spec §4.5).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * RESTORING A SESSION NEVER BYPASSES THE CODE
 *
 * §4.5: "Remember-me restores a session but never bypasses OTP on an untrusted
 * device."
 *
 * The two cookies answer different questions and both are asked. Remember-me
 * says WHO; device trust says whether a second factor is still owed. A browser
 * holding a remember-me cookie and no device trust — a copied cookie, or a
 * trust that has expired — is sent to sign in properly.
 *
 * Getting this wrong is the difference between remember-me being a convenience
 * and remember-me being a way to skip two-factor authentication permanently by
 * stealing one cookie.
 * ═════════════════════════════════════════════════════════════════════════════
 *
 * Laravel's own remember-me is not used. Its token is static for the life of
 * the cookie, so a copy keeps working silently; §4.5 requires rotation with
 * theft detection, which App\Support\Auth\RememberMe implements.
 */
class RestoreRememberedSession
{
    public function __construct(
        private RememberMe $remember,
        private TrustedDevices $devices,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null || $request->cookie(RememberMe::COOKIE) === null) {
            return $next($request);
        }

        /*
         * Resolving rotates the token — and, if a retired one was presented,
         * revokes the whole chain and returns null. That happens even though
         * this request is not signing anybody in: a theft signal is worth
         * acting on the moment it arrives.
         */
        $resolved = $this->remember->resolve($request);

        if ($resolved === null) {
            return $this->forget($next($request));
        }

        $user = $resolved['user'];

        // The second question. A remembered browser that is not a trusted
        // device still owes a code, so it is not signed in here.
        if (! $this->devices->trusts($user, $request)) {
            return $next($request);
        }

        auth()->login($user);

        $request->session()->regenerate();
        EnforceSessionLifetime::stamp($request);

        return $next($request)->withCookie($resolved['cookie']);
    }

    /**
     * Clear a cookie that no longer resolves.
     *
     * Left in place, the browser would keep presenting a credential the
     * database has already revoked on every request for thirty days.
     */
    protected function forget(Response $response): Response
    {
        return $response->withCookie($this->remember->forget());
    }
}
