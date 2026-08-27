<?php

namespace App\Http\Controllers\Auth;

use App\Support\Realm;
use App\Support\SupportContact;
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

        $lockedUntil = $this->lockedUntil($request);

        return response()->view('auth.login', [
            'supportMailto' => $this->supportMailto(),
            'lockedUntil' => $lockedUntil,
            'notice' => $this->notice($request, $lockedUntil),
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
            'resendCooldown' => $this->preview($request) === 'cooldown'
                ? 45
                : (int) $request->session()->get('otp.resend_cooldown', 0),
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
        return SupportContact::mailto('ZephryxLabs CRM — account access');
    }

    /**
     * The single banner shown above the form, or null for none.
     *
     * Every form-level state resolves through here — lockout, session expiry,
     * a disabled account — so there is one place that decides what the user
     * sees and one rendering path. Field-level errors are separate and handled
     * in the view.
     *
     * @return array{tone: string, title?: string, message: string}|null
     */
    protected function notice(Request $request, ?int $lockedUntil): ?array
    {
        if ($lockedUntil !== null) {
            $minutes = max(1, (int) ceil($lockedUntil / 60));

            return [
                'tone' => 'warning',
                'title' => 'Too many attempts',
                'message' => 'Sign-in is paused for this account. Try again in about '
                    .$minutes.' minute'.($minutes === 1 ? '' : 's').'.',
            ];
        }

        if ($previewed = $this->previewNotice($request)) {
            return $previewed;
        }

        if ($request->session()->has('status')) {
            return [
                'tone' => (string) $request->session()->get('status_tone', 'info'),
                'message' => (string) $request->session()->get('status'),
            ];
        }

        return null;
    }

    /**
     * Design-time previews of states that only the backend can produce.
     *
     * @return array{tone: string, title?: string, message: string}|null
     */
    protected function previewNotice(Request $request): ?array
    {
        return match ($this->preview($request)) {
            // §4.4 — the session reached its idle timeout.
            'expired' => [
                'tone' => 'info',
                'title' => 'Session expired',
                'message' => 'You were signed out after a period of inactivity. Sign in again to continue.',
            ],
            // §4.2 step 4 — inactive or suspended. Deliberately says nothing
            // about which, or why; that detail is for an administrator.
            'disabled' => [
                'tone' => 'danger',
                'title' => 'Cannot sign in',
                'message' => 'This account is not able to sign in. Contact your administrator.',
            ],
            default => null,
        };
    }

    protected function lockedUntil(Request $request): ?int
    {
        return $this->preview($request) === 'lockout' ? 300 : null;
    }

    /**
     * The requested design preview, or null.
     *
     * Local + debug only. Everywhere else this returns null, so the query
     * parameter cannot be used to put a fabricated notice — "session expired",
     * "cannot sign in" — on a deployed sign-in page.
     */
    protected function preview(Request $request): ?string
    {
        if (! app()->environment('local') || ! config('app.debug')) {
            return null;
        }

        $requested = $request->query('preview');

        return in_array($requested, ['lockout', 'expired', 'disabled', 'cooldown'], true)
            ? $requested
            : null;
    }
}
