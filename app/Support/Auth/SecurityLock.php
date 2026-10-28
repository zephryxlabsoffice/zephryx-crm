<?php

namespace App\Support\Auth;

use App\Mail\SecurityAlertMail;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Notifier;
use App\Support\Realm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * "This wasn't me" (owner, 2026-10-28).
 *
 * Every email a person gets because of their own account - the sign-in code,
 * a password reset they asked for, an email change - carries a red button.
 * The one exception is a reset link an administrator sent: that was not
 * something the person did, so there is nothing for them to disown.
 *
 * Pressing it puts the account ON HOLD: a status of its own, not a
 * suspension, so it reads as "held for your safety" rather than "removed".
 * Every session, remember-me token and trusted device is revoked, and the
 * owner and every System Administrator are told by email and on their
 * dashboards. Taking it off hold (Accounts → Restore) emails the person a
 * fresh password link.
 */
class SecurityLock
{
    public const STATUS = 'on_hold';

    public const LINK_DAYS = 7;

    /** What the email was about, for the page and the alert. */
    public const SIGN_IN = 'sign-in';

    public const RESET = 'reset';

    public const EMAIL = 'email';

    /** The red button's link. */
    public static function url(User $user, string $address, string $reason): string
    {
        return URL::temporarySignedRoute(
            'account.not-me',
            now()->addDays(self::LINK_DAYS),
            ['user' => $user->id, 'address' => $address, 'reason' => $reason],
        );
    }

    /** What happened, in words, for each reason. */
    public static function describe(string $reason): string
    {
        return match ($reason) {
            self::SIGN_IN => 'a sign-in code',
            self::RESET => 'a password reset link',
            default => 'an email change',
        };
    }

    public static function lock(User $user, string $address, string $reason, Request $request): void
    {
        if ($user->status === self::STATUS) {
            return;
        }

        $was = $user->status;

        $user->forceFill(['status' => self::STATUS])->save();

        app(RememberMe::class)->revokeAll($user);
        app(TrustedDevices::class)->revokeAll($user);
        DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();

        app(AuditLog::class)->record(
            action: AuditLog::SECURITY_LOCKED,
            actor: $user,
            entityType: 'user',
            entityId: $user->user_id,
            before: $was,
            after: 'On hold: "This wasn\'t me" on '.self::describe($reason).' sent to '.$address.'; signed out everywhere',
            request: $request,
        );

        self::tell($user, $address, $reason);
    }

    /**
     * The owner's Admin Panel account and every System Administrator: an
     * email each, and a notification on the staff dashboard for the latter
     * (the Admin Panel shows its own alert - see admin/dashboard).
     */
    protected static function tell(User $user, string $address, string $reason): void
    {
        $admins = User::query()
            ->where('status', 'active')
            ->whereKeyNot($user->id)
            ->where(fn ($q) => $q
                ->where('account_type', Realm::ADMIN)
                ->orWhereHas('roles', fn ($r) => $r->where('role_key', 'system_admin')))
            ->get();

        foreach ($admins as $admin) {
            if ($admin->account_type !== Realm::ADMIN) {
                app(Notifier::class)->send(
                    reader: $admin,
                    kind: 'security',
                    title: $user->name.'\'s account is on hold',
                    body: 'Somebody at '.$address.' said they did not ask for '.self::describe($reason).'. Restore it from Accounts once you have checked with them.',
                    route: 'staff-accounts.show',
                    params: ['account' => $user->user_id],
                );
            }

            try {
                Mail::to($admin->email)->send(new SecurityAlertMail(
                    (string) $user->name,
                    (string) $user->user_id,
                    $address,
                    self::describe($reason),
                    $admin->account_type === Realm::ADMIN
                        ? url('/admin/accounts/'.$user->user_id)
                        : url('/accounts/'.$user->user_id),
                ));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
