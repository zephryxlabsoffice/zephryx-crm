<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Auth\EmailVerification;
use App\Support\Auth\SecurityLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Throwable;

/**
 * Following a verification link, and asking for one. See
 * App\Support\Auth\EmailVerification.
 */
class EmailVerificationController extends Controller
{
    public function __construct(protected AuditLog $audit) {}

    /**
     * GET /verify-email/{user}/{hash} - signed and time-limited, so it works
     * signed out; the hash ties it to one address on the account.
     */
    public function confirm(Request $request, User $user, string $hash): RedirectResponse
    {
        $confirmed = EmailVerification::confirm($user, $hash);

        if ($confirmed) {
            $this->audit->record(
                action: AuditLog::EMAIL_VERIFIED,
                actor: $user,
                entityType: 'user',
                entityId: $user->user_id,
                request: $request,
            );
        }

        $to = $request->user()?->is($user) ? route('profile.show') : route('login');

        return redirect($to)
            ->with('status', $confirmed
                ? 'Your email is verified.'
                : 'That link is for an address no longer on your account. Ask for a new one from My Profile.')
            ->with('status_tone', $confirmed ? 'success' : 'warning');
    }

    /**
     * GET /account/not-me/{user} - the red button's page. Signed; nothing
     * changes until the button on it is pressed.
     */
    public function notMe(Request $request, User $user): Response
    {
        return response()->view('account.not-me', [
            'name' => (string) $user->name,
            'address' => (string) $request->query('address'),
            'reason' => (string) $request->query('reason', SecurityLock::EMAIL),
            'action' => $request->fullUrl(),
            'locked' => false,
        ]);
    }

    /**
     * POST /account/not-me/{user} - lock it. See App\Support\Auth\SecurityLock.
     */
    public function lock(Request $request, User $user): Response
    {
        SecurityLock::lock($user, (string) $request->query('address'), (string) $request->query('reason', SecurityLock::EMAIL), $request);

        return response()->view('account.not-me', [
            'name' => (string) $user->name,
            'address' => (string) $request->query('address'),
            'reason' => (string) $request->query('reason', SecurityLock::EMAIL),
            'action' => $request->fullUrl(),
            'locked' => true,
        ]);
    }

    /**
     * POST /profile/email/verify - send a link to every unverified address.
     */
    public function send(Request $request): RedirectResponse
    {
        try {
            $sent = EmailVerification::sendPending($request->user());
        } catch (Throwable $e) {
            report($e);

            return back()->with('status', 'The email could not be sent. Try again in a few minutes.')
                ->with('status_tone', 'warning');
        }

        return back()
            ->with('status', $sent === []
                ? 'Your email addresses are already verified.'
                : 'A verification link is on its way to '.implode(' and ', $sent).'.')
            ->with('status_tone', $sent === [] ? 'info' : 'success');
    }
}
