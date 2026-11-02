<?php

namespace App\Http\Controllers\Auth;

use App\Mail\PasswordResetMail;
use App\Models\UserDevice;
use App\Support\Audit\AuditLog;
use App\Support\Auth\OneTimeCode;
use App\Support\Auth\PasswordResets;
use App\Support\Auth\SecurityLock;
use App\Support\Auth\UserDevices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * /mydevices - where this account is signed in (owner, 2026-11-02).
 *
 * One page in all three realms, mounted from each realm's own route file. Like
 * /profile, the account is the session. A device id belonging to somebody else
 * 404s exactly as an imaginary one does.
 *
 * Reset password does not sign anything out itself. It sends the ordinary link
 * to the account's own address, because somebody at a borrowed or stolen
 * session must not be able to change the password. Applying the link is what
 * signs out every device (PasswordResetController, §4.6).
 */
class MyDevicesController extends Controller
{
    public function __construct(
        private UserDevices $devices,
        private PasswordResets $resets,
        private AuditLog $audit,
    ) {}

    /**
     * GET /mydevices
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return response()->view('account.my-devices', [
            'devices' => $this->devices->signedIn($user, $request),
            'currentToken' => $this->devices->sessionToken($user, $request),
            'routes' => UserDevices::routeNames($user->account_type),
        ]);
    }

    /**
     * POST /mydevices/{device}/sign-out
     */
    public function signOut(Request $request, int $device): RedirectResponse
    {
        $user = $request->user();

        $record = UserDevice::where('user_id', $user->id)
            ->whereNotNull('sign_in_token')
            ->findOrFail($device);

        $this->devices->signOut($record);

        $this->audit->record(
            action: AuditLog::DEVICE_SIGNED_OUT,
            actor: $user,
            entityType: 'user',
            entityId: $user->user_id,
            after: $record->device_name.' signed out from My Devices',
            request: $request,
        );

        return back()
            ->with('status', $record->device_name.' has been signed out.')
            ->with('status_tone', 'success');
    }

    /**
     * POST /mydevices/reset-password
     */
    public function resetPassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        try {
            Mail::to($user->email)->send(new PasswordResetMail(
                url: route('password.reset.form', ['token' => $this->resets->issue($user, $request)]),
                name: $user->name,
                ip: $request->ip(),
                notMeUrl: SecurityLock::url($user, (string) $user->email, SecurityLock::RESET),
            ));
        } catch (Throwable $e) {
            report($e);

            return back()
                ->with('status', 'The email could not be sent. Try again in a few minutes.')
                ->with('status_tone', 'warning');
        }

        $this->audit->record(
            action: AuditLog::PASSWORD_RESET_REQUESTED,
            actor: $user,
            entityType: 'user',
            entityId: $user->user_id,
            after: 'Requested from My Devices',
            request: $request,
        );

        return back()
            ->with('status', 'A reset link is on its way to '.OneTimeCode::maskEmail((string) $user->email)
                .'. The link expires in '.PasswordResets::EXPIRY_MINUTES.' minutes.')
            ->with('status_tone', 'success');
    }
}
