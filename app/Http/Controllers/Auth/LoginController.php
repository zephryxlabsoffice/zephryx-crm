<?php

namespace App\Http\Controllers\Auth;

use App\Http\Middleware\EnforceSessionLifetime;
use App\Mail\OneTimeCodeMail;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Auth\LoginThrottle;
use App\Support\Auth\OneTimeCode;
use App\Support\Auth\RememberMe;
use App\Support\Auth\TrustedDevices;
use App\Support\Realm;
use App\Support\SupportContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * The shared sign-in form (foundation spec §4, §9.2).
 *
 * One form serves all three realms; it never asks which kind of user you are
 * and never reveals it before authentication (§3).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE ORDER OF §4.2 IS THE SECURITY MODEL, NOT A SUGGESTION
 *
 *   1. Rate-limit check          — before the database is touched at all
 *   2. Look up, verify password  — in constant time, whether or not the
 *                                  account exists
 *   3. Failure                   — ONE message, always the same one
 *   4. Inactive                  — the same message again, no detail
 *   5. Success, trusted device   — session issued
 *   6. Success, untrusted device — code emailed, NO SESSION until it verifies
 *
 * Step 3 is the one that gets softened in practice, and softening it is how an
 * attacker learns who works here. "No account with that email" and "wrong
 * password" are different answers to the same question, and the difference is
 * a staff directory.
 *
 * Step 6 is the one that gets implemented wrongly: a session must not exist
 * before the code is verified. Signing somebody in and then asking for a code
 * makes the code decorative — anything holding a session cookie is already in.
 * The pending state lives in the session as an id and a timestamp, and nothing
 * else.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class LoginController extends Controller
{
    /** Where the half-finished sign-in lives between the password and the code. */
    protected const PENDING_USER = 'auth.pending_user';
    protected const PENDING_AT = 'auth.pending_at';
    protected const PENDING_REMEMBER = 'auth.pending_remember';
    protected const PENDING_EMAIL = 'auth.pending_email';

    /**
     * How long the code step stays open.
     *
     * Slightly longer than the code's own life, so somebody who is a few
     * seconds late is told the code expired rather than being bounced to the
     * form with no explanation.
     */
    protected const PENDING_MINUTES = OneTimeCode::EXPIRY_MINUTES + 2;

    public function __construct(
        private LoginThrottle $throttle,
        private OneTimeCode $codes,
        private TrustedDevices $devices,
        private RememberMe $remember,
        private AuditLog $audit,
    ) {
    }

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

        $pending = $this->pending($request);

        // No half-finished sign-in, no code page. Reaching this URL directly
        // must not suggest that a code was sent to somebody.
        if ($pending === null) {
            return redirect()->route('login');
        }

        return response()->view('auth.verify', [
            'maskedEmail' => (string) $request->session()->get(self::PENDING_EMAIL, ''),
            'expiresInMinutes' => OneTimeCode::EXPIRY_MINUTES,
            'resendCooldown' => $this->codes->resendCooldown($pending),
        ]);
    }

    /**
     * POST /login — §4.2 steps 1 to 6.
     */
    public function attempt(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [
            'identifier.required' => 'Enter your email address or user ID.',
            'password.required' => 'Enter your password.',
        ]);

        $identifier = trim($validated['identifier']);

        // Step 1. Before anything is looked up.
        if ($this->throttle->isLocked($identifier, $request)) {
            $this->throttle->record($identifier, $request, false, 'throttled');

            return $this->refuse($request, $identifier);
        }

        $user = $this->lookUp($identifier);

        /*
         * Step 2, in constant time.
         *
         * When there is no account we still hash something. Otherwise the
         * response for an unknown address returns in a millisecond and the
         * response for a known one takes as long as bcrypt does — which is the
         * enumeration §4.2 forbids, leaking through the clock instead of
         * through the message.
         */
        $correct = $user !== null
            ? Hash::check($validated['password'], $user->password)
            : $this->burnTime($validated['password']);

        if ($user === null || ! $correct) {
            $this->throttle->record($identifier, $request, false, 'invalid_credentials', $user?->id);

            $this->audit->record(
                action: AuditLog::SIGN_IN_REFUSED,
                actor: $user,
                actorLabel: $identifier,
                entityType: 'user',
                entityId: $user?->user_id,
                after: 'Invalid credentials',
                request: $request,
            );

            return $this->refuse($request, $identifier);
        }

        /*
         * Step 4. The password was right and the account cannot sign in.
         *
         * The FORM says nothing about why — that is still the generic refusal,
         * because saying more here would let anybody who guesses an address
         * learn whether it belongs to a closed account.
         *
         * But the person has now proved they hold the credentials, so they are
         * sent to the closed-account page, which explains. See
         * AccountStatusController for the full argument.
         */
        if (! $user->isActive()) {
            $this->throttle->record($identifier, $request, false, 'inactive', $user->id);

            $this->audit->record(
                action: AuditLog::SIGN_IN_REFUSED,
                actor: $user,
                entityType: 'user',
                entityId: $user->user_id,
                after: 'Account is '.$user->status,
                request: $request,
            );

            $request->session()->flash(
                AccountStatusController::SESSION_KEY,
                $user->account_type === Realm::CLIENT ? Realm::CLIENT : Realm::STAFF,
            );

            return redirect()->route('account.inactive');
        }

        $this->throttle->record($identifier, $request, true, null, $user->id);
        $this->throttle->clear($identifier);

        $wantsRemember = $request->boolean('remember') && $user->account_type !== Realm::ADMIN;

        // Step 5. A trusted device skips the code — never the password.
        if ($this->devices->trusts($user, $request)) {
            return $this->establish($user, $request, $wantsRemember);
        }

        // Step 6. No session yet. Only a pending id and a code in the post.
        return $this->beginVerification($user, $request, $wantsRemember);
    }

    /**
     * POST /login/verify — §4.3.
     */
    public function verify(Request $request): RedirectResponse
    {
        $pending = $this->pending($request);

        if ($pending === null) {
            return redirect()->route('login')->withErrors([
                'code' => 'That sign-in timed out. Start again.',
            ]);
        }

        // The form posts six separate inputs so each digit gets its own box.
        $code = implode('', array_map('strval', (array) $request->input('code', [])));

        if (! preg_match('/^\d{6}$/', $code)) {
            return back()->withErrors(['code' => 'Enter all six digits of the code.']);
        }

        if (! $this->codes->verify($pending, $code)) {
            $this->throttle->record($pending->email, $request, false, 'otp_failed', $pending->id);

            $this->audit->record(
                action: AuditLog::OTP_FAILED,
                actor: $pending,
                entityType: 'user',
                entityId: $pending->user_id,
                request: $request,
            );

            /*
             * One message for wrong, expired and exhausted — the same reasoning
             * as the sign-in form. "That code has expired" tells somebody
             * holding a stolen password that they had the right account and
             * only needed to be faster.
             */
            return back()->withErrors([
                'code' => 'That code was not accepted. Check it, or ask for a new one.',
            ]);
        }

        $remember = (bool) $request->session()->get(self::PENDING_REMEMBER, false);

        return $this->establish($pending, $request, $remember, trustDevice: true);
    }

    /**
     * POST /login/resend — §4.3, rate-limited, invalidates the previous code.
     */
    public function resend(Request $request): RedirectResponse
    {
        $pending = $this->pending($request);

        if ($pending === null) {
            return redirect()->route('login');
        }

        $cooldown = $this->codes->resendCooldown($pending);

        if ($cooldown > 0) {
            return back()
                ->with('status', 'A code was just sent. You can ask for another in '.$cooldown.' seconds.')
                ->with('status_tone', 'info');
        }

        $this->sendCode($pending, $request);

        return back()
            ->with('status', 'A new code is on its way. The previous one no longer works.')
            ->with('status_tone', 'success');
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE STEPS
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * §4.1 — one field, an `@` decides how it is read.
     */
    protected function lookUp(string $identifier): ?User
    {
        return str_contains($identifier, '@')
            ? User::where('email', $identifier)->first()
            : User::where('user_id', $identifier)->first();
    }

    /**
     * Hash something so a missing account costs the same as a present one.
     *
     * Always returns false. The return value is not the point; the elapsed time
     * is. See step 2 above.
     */
    protected function burnTime(string $password): bool
    {
        Hash::check($password, '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');

        return false;
    }

    /**
     * Put the sign-in on hold and email a code. No session is created.
     */
    protected function beginVerification(User $user, Request $request, bool $remember): RedirectResponse
    {
        $request->session()->put(self::PENDING_USER, $user->id);
        $request->session()->put(self::PENDING_AT, now()->timestamp);
        $request->session()->put(self::PENDING_REMEMBER, $remember);
        $request->session()->put(self::PENDING_EMAIL, OneTimeCode::maskEmail($user->email));

        $this->sendCode($user, $request);

        return redirect()->route('login.verify');
    }

    protected function sendCode(User $user, Request $request): void
    {
        $code = $this->codes->issue($user, $request);

        /*
         * §4.3 accepts that a mail failure blocks sign-in — there are no backup
         * codes, deliberately. So the send is not swallowed: if SMTP is down,
         * this throws and somebody sees a 500 rather than a verify page that
         * will never accept anything.
         *
         * The recovery path for the owner's account, which has nobody above it,
         * is the manual procedure in §11.3.
         */
        Mail::to($user->email)->send(new OneTimeCodeMail(
            code: $code,
            name: $user->name,
            ip: $request->ip(),
        ));

        $this->audit->record(
            action: AuditLog::OTP_ISSUED,
            actor: $user,
            entityType: 'user',
            entityId: $user->user_id,
            request: $request,
        );
    }

    /**
     * Issue the session — the only place in the application that does.
     */
    protected function establish(User $user, Request $request, bool $remember, bool $trustDevice = false): RedirectResponse
    {
        $this->clearPending($request);

        auth()->login($user);

        // §4.4 — regenerated on login. Without this the pre-auth session id
        // survives, which is session fixation.
        $request->session()->regenerate();

        // Starts the absolute-lifetime clock (§4.4). Stamped here rather than
        // inferred by the middleware, so the ceiling is measured from the
        // sign-in and cannot be reset by activity.
        EnforceSessionLifetime::stamp($request);

        $user->forceFill(['last_login_at' => now()])->save();

        $this->audit->record(
            action: AuditLog::SIGNED_IN,
            actor: $user,
            entityType: 'user',
            entityId: $user->user_id,
            request: $request,
        );

        $response = redirect()->intended(Realm::dashboardFor($user));

        if ($trustDevice) {
            $response->withCookie($this->devices->trust($user, $request));

            $this->audit->record(
                action: AuditLog::DEVICE_TRUSTED,
                actor: $user,
                entityType: 'user',
                entityId: $user->user_id,
                after: 'Trusted for '.TrustedDevices::DAYS.' days',
                request: $request,
            );
        }

        // §4.5 — never the admin account. Checked here as well as in the
        // service, because a second reader of this code should not have to go
        // and look.
        if ($remember && $user->account_type !== Realm::ADMIN) {
            $response->withCookie($this->remember->issue($user, $request));
        }

        return $response;
    }

    /**
     * The account waiting on a code, if the hold is still open.
     */
    protected function pending(Request $request): ?User
    {
        $id = $request->session()->get(self::PENDING_USER);
        $at = $request->session()->get(self::PENDING_AT);

        if ($id === null || $at === null) {
            return null;
        }

        if (now()->timestamp - (int) $at > self::PENDING_MINUTES * 60) {
            $this->clearPending($request);

            return null;
        }

        $user = User::find($id);

        // Suspended between the password and the code. Rare, and exactly the
        // moment somebody would want the suspension to take effect.
        return $user !== null && $user->isActive() ? $user : null;
    }

    protected function clearPending(Request $request): void
    {
        $request->session()->forget([
            self::PENDING_USER, self::PENDING_AT, self::PENDING_REMEMBER, self::PENDING_EMAIL,
        ]);
    }

    /**
     * The one refusal (§4.2 step 3).
     *
     * Identical for an unknown identifier, a wrong password and a throttled
     * attempt. The only thing that varies is the lockout countdown, which the
     * page shows because it is useful and because a locked-out person can see
     * they are locked out by trying twice anyway.
     */
    protected function refuse(Request $request, string $identifier): RedirectResponse
    {
        return back()
            ->withInput($request->except('password'))
            ->withErrors(['auth' => 'Invalid credentials.']);
    }

    protected function supportMailto(): string
    {
        return SupportContact::mailto('ZephryxLabs CRM — account access');
    }

    /**
     * The single banner shown above the form, or null for none.
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
     * Design-time previews of states that are otherwise hard to produce.
     *
     * @return array{tone: string, title?: string, message: string}|null
     */
    protected function previewNotice(Request $request): ?array
    {
        return match ($this->preview($request)) {
            'expired' => [
                'tone' => 'info',
                'title' => 'Session expired',
                'message' => 'You were signed out after a period of inactivity. Sign in again to continue.',
            ],
            'disabled' => [
                'tone' => 'danger',
                'title' => 'Cannot sign in',
                'message' => 'This account is not able to sign in. Contact your administrator.',
            ],
            default => null,
        };
    }

    /**
     * The lockout countdown for whatever was last typed.
     *
     * Read from the old input rather than from a session flag: the form
     * repopulates the identifier anyway, and a flag would have to be cleared
     * somewhere.
     */
    protected function lockedUntil(Request $request): ?int
    {
        if ($this->preview($request) === 'lockout') {
            return 300;
        }

        $identifier = old('identifier');

        return is_string($identifier) && $identifier !== ''
            ? $this->throttle->lockedFor($identifier, $request)
            : null;
    }

    /**
     * Local + debug only. Everywhere else this returns null, so the query
     * parameter cannot put a fabricated notice on a deployed sign-in page.
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
