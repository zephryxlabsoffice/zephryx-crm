<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Auth\RememberMe;
use App\Support\Auth\TrustedDevices;
use App\Support\Auth\UserDevices;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends a session whose device was signed out, and keeps "last active" current.
 * See App\Support\Auth\UserDevices.
 *
 * A session with no device token at all is adopted without an email: one open
 * since before this release, or one restored from remember-me, which only a
 * browser that already holds device trust can do.
 *
 * verify.zephryxlabs.in has its own cookies and is not a place to manage devices,
 * so it is left out, as it is in LoginController.
 */
class TrackSignedInDevice
{
    public function __construct(
        private UserDevices $devices,
        private TrustedDevices $trust,
        private RememberMe $remember,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || VerifyHost::is($request) || ! $this->devices->isReady()) {
            return $next($request);
        }

        if (! $this->devices->isTracked($user, $request)) {
            $sighting = $this->devices->signIn($user, $request);
            $this->trust->linkCurrent($user, $request, $sighting['device']->id);

            $response = $next($request);
            $response->headers->setCookie($sighting['cookie']);

            return $response;
        }

        $device = $this->devices->forSession($user, $request);

        if ($device === null) {
            return $this->signedOutElsewhere($request);
        }

        $this->devices->touch($device, $request);

        return $next($request);
    }

    protected function signedOutElsewhere(Request $request): Response
    {
        auth()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', 'This device was signed out from your account. Sign in again to continue.')
            ->with('status_tone', 'info')
            ->withCookie($this->remember->forget())
            ->withCookie($this->trust->forget());
    }
}
