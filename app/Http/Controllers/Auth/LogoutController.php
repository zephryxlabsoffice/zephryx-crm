<?php

namespace App\Http\Controllers\Auth;

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
 * FRONT END ONLY. §4.4 requires explicit sign-out to destroy the session, the
 * remember-me token and the device-trust record. None of those exist yet.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class LogoutController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', 'You have been signed out.')
            ->with('status_tone', 'success');
    }
}
