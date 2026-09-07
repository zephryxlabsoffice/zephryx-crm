<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Auth\RememberMe;
use App\Support\Auth\TrustedDevices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * POST /logout — foundation spec §3.2, §4.4.
 *
 * POST, never GET: a GET sign-out can be fired by any `<img src="/logout">` on
 * any page the user visits.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * SIGNING OUT TAKES ALL THREE THINGS AWAY
 *
 * §4.4: "Explicit logout destroys the session, the remember-me token and the
 * device trust record."
 *
 * All three, because of what signing out MEANS on a shared or borrowed machine.
 * Somebody who presses it in an internet cafe expects to have left nothing
 * behind — a session that ends while a remember-me cookie survives has left the
 * next person a working account, and one that skips device trust has left them
 * a machine that no longer needs a code.
 *
 * Only the CURRENT device's trust is revoked, not every device. Signing out on
 * a shared machine should not un-trust somebody's own laptop at home.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class LogoutController extends Controller
{
    public function __construct(
        private RememberMe $remember,
        private TrustedDevices $devices,
        private AuditLog $audit,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->remember->revokeAll($user);
            $this->devices->revokeCurrent($user, $request);

            $this->audit->record(
                action: AuditLog::SIGNED_OUT,
                actor: $user,
                entityType: 'user',
                entityId: $user->user_id,
                request: $request,
            );
        }

        auth()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', 'You have been signed out.')
            ->with('status_tone', 'success')
            // The cookies go too. Leaving them to expire would mean the browser
            // keeps presenting credentials that the database has already
            // revoked — harmless, and confusing to anybody reading traffic.
            ->withCookie($this->remember->forget())
            ->withCookie($this->devices->forget());
    }
}
