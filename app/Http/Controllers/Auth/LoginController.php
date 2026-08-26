<?php

namespace App\Http\Controllers\Auth;

use App\Support\Realm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * The shared sign-in form (foundation spec §9.2).
 *
 * One form serves all three realms; it never asks which kind of user you are
 * and never reveals it before authentication (§3).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FRONT END ONLY. The credential check, rate limiting, OTP issue/verify and
 * session handling described in §4 are not implemented yet. `attempt()`,
 * `verify()` and `resend()` are stubs that exercise the rendered states; they
 * must be replaced before this page is deployed anywhere reachable.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class LoginController extends Controller
{
    /**
     * GET /login
     */
    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return redirect(Realm::dashboardFor($request->user()));
        }

        return response()->view('auth.login', [
            'supportMailto' => $this->supportMailto(),
            'lockedUntil' => $this->previewedLockout($request),
        ]);
    }

    /**
     * GET /login/verify — the one-time code step.
     */
    public function showVerify(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return redirect(Realm::dashboardFor($request->user()));
        }

        return response()->view('auth.verify', [
            // Placeholder until the credential step exists and can put the real
            // (masked) address in the session.
            'maskedEmail' => $request->session()->get('otp.masked_email', 'y•••••@zephryxlabs.com'),
            'expiresInMinutes' => 10,
            'resendCooldown' => (int) $request->session()->get('otp.resend_cooldown', 0),
        ]);
    }

    /**
     * POST /login — NOT IMPLEMENTED.
     *
     * Validation is real, because the inline field states are part of this
     * page's design. Everything past it is deferred to the backend phase: a
     * genuine attempt must go through rate limiting, a constant-time credential
     * check, a generic failure message and the OTP step (§4.2).
     */
    public function attempt(Request $request): RedirectResponse
    {
        $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [
            'identifier.required' => 'Enter your email address or user ID.',
            'password.required' => 'Enter your password.',
        ]);

        return back()
            ->withInput($request->except('password'))
            ->withErrors(['auth' => 'Sign-in is not connected yet — the authentication backend is still being built.']);
    }

    /**
     * POST /login/verify — NOT IMPLEMENTED.
     */
    public function verify(Request $request): RedirectResponse
    {
        $code = implode('', array_map('strval', (array) $request->input('code', [])));

        if (! preg_match('/^\d{6}$/', $code)) {
            return back()->withErrors(['code' => 'Enter all six digits of the code.']);
        }

        return back()->withErrors([
            'code' => 'Code checking is not connected yet — the authentication backend is still being built.',
        ]);
    }

    /**
     * POST /login/resend — NOT IMPLEMENTED.
     */
    public function resend(Request $request): RedirectResponse
    {
        return back()
            ->with('status', 'Resending is not connected yet — the authentication backend is still being built.')
            ->with('status_tone', 'info');
    }

    protected function supportMailto(): string
    {
        return 'mailto:'.config('zephryx.support.email')
            .'?subject='.rawurlencode('ZephryxLabs CRM — account access');
    }

    /**
     * Renders the lockout banner on demand while the page is being designed.
     *
     * Local, debug-mode only: in every other environment this returns null, so
     * the query parameter cannot be used to fake a lockout notice on a
     * deployed site.
     */
    protected function previewedLockout(Request $request): ?int
    {
        if (! app()->environment('local') || ! config('app.debug')) {
            return null;
        }

        return $request->query('preview') === 'lockout' ? 300 : null;
    }
}
