<?php

namespace App\Http\Controllers\Auth;

use App\Rules\NotACommonPassword;
use App\Support\Realm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rules\Password;

/**
 * Self-service password reset (foundation spec §4.6, §4.7).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FRONT END ONLY. Token issue, mail delivery, token verification and the
 * invalidation of sessions / remember-me tokens / device trust on a successful
 * reset are all deferred to the backend phase.
 *
 * Two things below are NOT placeholders and must survive that work:
 *   · request() always answers identically whether or not the account exists
 *   · the password rules are the real §4.7 policy
 * ─────────────────────────────────────────────────────────────────────────────
 */
class PasswordResetController extends Controller
{
    /**
     * GET /forgot-password
     */
    public function showRequest(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return redirect(Realm::dashboardFor($request->user()));
        }

        return response()->view('auth.forgot-password');
    }

    /**
     * POST /forgot-password
     *
     * Returns the same confirmation regardless of whether the identifier
     * matches an account. Anything else turns this form into a way to discover
     * who works here (§4.6).
     */
    public function request(Request $request): RedirectResponse
    {
        $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ], [
            'identifier.required' => 'Enter your email address or user ID.',
        ]);

        // The backend phase looks the account up and mails a token here. The
        // response is deliberately outside that branch: it must not vary.
        return back()
            ->with('status', 'If that account exists, a reset link is on its way. The link expires in 60 minutes.')
            ->with('status_tone', 'success');
    }

    /**
     * GET /reset-password/{token}
     */
    public function showReset(Request $request, string $token): Response
    {
        return response()->view('auth.reset-password', [
            'token' => $token,
            'identifier' => (string) $request->query('identifier', ''),
        ]);
    }

    /**
     * POST /reset-password — NOT IMPLEMENTED past validation.
     */
    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'identifier' => ['required', 'string', 'max:255'],
            'password' => array_merge(['required', 'confirmed'], $this->passwordRules()),
        ], [
            'password.confirmed' => 'The two passwords do not match.',
        ]);

        return back()->withErrors([
            'token' => 'Password reset is not connected yet — the authentication backend is still being built.',
        ]);
    }

    /**
     * Spec §4.7: minimum 12 characters, checked against a common-password
     * blocklist, and no composition rules — both forced complexity and forced
     * rotation are known to produce weaker passwords in practice.
     *
     * Laravel's `uncompromised()` is deliberately not used: it calls the
     * HaveIBeenPwned API and treats an unreachable API as a pass, so on shared
     * hosting with blocked outbound traffic the check would silently do
     * nothing. App\Rules\NotACommonPassword always runs.
     *
     * @return list<mixed>
     */
    protected function passwordRules(): array
    {
        return [Password::min(12), new NotACommonPassword()];
    }
}
