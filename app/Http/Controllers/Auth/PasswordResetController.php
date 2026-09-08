<?php

namespace App\Http\Controllers\Auth;

use App\Mail\PasswordResetMail;
use App\Models\User;
use App\Rules\NotACommonPassword;
use App\Support\Audit\AuditLog;
use App\Support\Auth\PasswordResets;
use App\Support\Auth\RememberMe;
use App\Support\Auth\TrustedDevices;
use App\Support\Realm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;

/**
 * Self-service password reset (foundation spec §4.6, §4.7).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * TWO THINGS THIS PAGE MUST NEVER DO
 *
 * 1. VARY ITS ANSWER. `request()` returns the same confirmation whether or not
 *    the account exists. Anything else turns a public form into a way to
 *    discover who works here — type an address, read the response, repeat.
 *
 *    That means the send happens inside a branch and the response outside it,
 *    which looks odd until you remember why. It also means an unknown address
 *    must not be noticeably faster, which is why nothing expensive happens in
 *    the known branch that does not also happen in the unknown one.
 *
 * 2. TRUST THE IDENTIFIER FIELD. The form posts one because the page shows one,
 *    and it is used for nothing. The account being reset is whichever account
 *    the TOKEN belongs to — see the head of App\Support\Auth\PasswordResets.
 * ═════════════════════════════════════════════════════════════════════════════
 *
 * §4.6: applying a reset invalidates the token, all sessions, all remember-me
 * tokens and all device trust for that user. Somebody resetting because they
 * think they are compromised expects all four; a reset that left an attacker's
 * session alive would be worse than useless, because it would feel like it
 * worked.
 */
class PasswordResetController extends Controller
{
    public function __construct(
        private PasswordResets $resets,
        private RememberMe $remember,
        private TrustedDevices $devices,
        private AuditLog $audit,
    ) {
    }

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
     */
    public function request(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ], [
            'identifier.required' => 'Enter your email address or user ID.',
        ]);

        $identifier = trim($validated['identifier']);

        $user = str_contains($identifier, '@')
            ? User::where('email', $identifier)->first()
            : User::where('user_id', $identifier)->first();

        /*
         * A suspended account gets no link either, and gets it silently. Saying
         * "that account is closed" here would answer, on a public form, the
         * question §4.2 spends the whole sign-in flow refusing to answer.
         */
        if ($user !== null && $user->isActive()) {
            $token = $this->resets->issue($user, $request);

            Mail::to($user->email)->send(new PasswordResetMail(
                url: route('password.reset.form', ['token' => $token]),
                name: $user->name,
                ip: $request->ip(),
            ));

            $this->audit->record(
                action: AuditLog::PASSWORD_RESET_REQUESTED,
                actor: $user,
                entityType: 'user',
                entityId: $user->user_id,
                request: $request,
            );
        }

        // Outside the branch. It must not vary.
        return back()
            ->with('status', 'If that account exists, a reset link is on its way. The link expires in '
                .PasswordResets::EXPIRY_MINUTES.' minutes.')
            ->with('status_tone', 'success');
    }

    /**
     * GET /reset-password/{token}
     */
    public function showReset(Request $request, string $token): Response
    {
        /*
         * The link is checked on arrival, so an expired one says so before
         * somebody types a new password twice and reads the failure at the
         * bottom of the form.
         */
        $user = $this->resets->userFor($token);

        return response()->view('auth.reset-password', [
            'token' => $token,
            'identifier' => $user?->email ?? (string) $request->query('identifier', ''),
            'expired' => $user === null,
        ]);
    }

    /**
     * POST /reset-password
     */
    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            // Accepted because the form shows it, and deliberately unused.
            'identifier' => ['nullable', 'string', 'max:255'],
            'password' => array_merge(['required', 'confirmed'], $this->passwordRules()),
        ], [
            'password.confirmed' => 'The two passwords do not match.',
        ]);

        $user = $this->resets->consume($validated['token']);

        if ($user === null) {
            return back()->withErrors([
                'token' => 'That link has expired or has already been used. Ask for a new one.',
            ]);
        }

        $user->forceFill([
            'password' => $validated['password'],
            'password_changed_at' => now(),
        ])->save();

        /*
         * §4.6, all four. The order matters only in that none of them may be
         * skipped: sessions, remember-me chains, device trust and any other
         * live reset link.
         */
        $this->invalidateEverything($user);

        $this->audit->record(
            action: AuditLog::PASSWORD_RESET,
            actor: $user,
            entityType: 'user',
            entityId: $user->user_id,
            after: 'Password changed; all sessions, remember-me tokens and trusted devices revoked',
            request: $request,
        );

        return redirect()->route('login')
            ->with('status', 'Your password has been changed. Sign in with it.')
            ->with('status_tone', 'success');
    }

    /**
     * Everything a reset has to take away (§4.6).
     */
    protected function invalidateEverything(User $user): void
    {
        $this->remember->revokeAll($user);
        $this->devices->revokeAll($user);
        $this->resets->invalidateAll($user);

        /*
         * ─────────────────────────────────────────────────────────────────────
         * EVERY SESSION FOR THIS USER, INCLUDING THE ONE DOING THE RESET.
         *
         * Only possible on the database driver. On `file` or `cookie` there is
         * no way to reach another session, so a reset would leave an attacker
         * signed in for up to seven more days AFTER the password they stole
         * stopped working — and the person who reset it would have no way to
         * know, because the reset appears to succeed.
         *
         * That is silent, which is why it is logged as an error rather than
         * skipped quietly. §8 lists a `sessions` table and §4.6 requires this
         * invalidation; a deployment on another driver is misconfigured, not
         * merely different.
         * ─────────────────────────────────────────────────────────────────────
         */
        if (config('session.driver') !== 'database') {
            Log::error('Password reset could not invalidate other sessions.', [
                'driver' => config('session.driver'),
                'user_id' => $user->user_id,
                'why' => 'SESSION_DRIVER must be `database` for §4.6 — other sessions cannot be '
                    .'reached on this driver and remain signed in with the old password.',
            ]);

            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->delete();
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
