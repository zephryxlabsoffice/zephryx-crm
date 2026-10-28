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
use Throwable;

/**
 * "This wasn't me" (owner, 2026-10-28).
 *
 * Every email about an address change carries a red button. Pressing it
 * locks the account: it is suspended (the same status the Admin Panel sets,
 * so the Admin Panel is also how it is unlocked), every session, remember-me
 * token and trusted device is revoked, and the owner and every System
 * Administrator are told by email and on their dashboard.
 */
class SecurityLock
{
    public const LINK_DAYS = 7;

    public static function lock(User $user, string $address, Request $request): void
    {
        if ($user->status === 'suspended') {
            return;
        }

        $user->forceFill(['status' => 'suspended'])->save();

        app(RememberMe::class)->revokeAll($user);
        app(TrustedDevices::class)->revokeAll($user);
        DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();

        app(AuditLog::class)->record(
            action: AuditLog::SECURITY_LOCKED,
            actor: $user,
            entityType: 'user',
            entityId: $user->user_id,
            before: 'active',
            after: 'Locked from the "This wasn\'t me" link sent to '.$address.'; signed out everywhere',
            request: $request,
        );

        self::tell($user, $address);
    }

    /**
     * The owner's Admin Panel account and every System Administrator: an
     * email each, and a notification on the staff dashboard for the latter
     * (the Admin Panel shows its own alert - see admin/dashboard).
     */
    protected static function tell(User $user, string $address): void
    {
        $admins = User::query()
            ->where('status', 'active')
            ->where(fn ($q) => $q
                ->where('account_type', Realm::ADMIN)
                ->orWhereHas('roles', fn ($r) => $r->where('role_key', 'system_admin')))
            ->get();

        foreach ($admins as $admin) {
            if ($admin->account_type !== Realm::ADMIN) {
                app(Notifier::class)->send(
                    reader: $admin,
                    kind: 'security',
                    title: $user->name.'\'s account was locked',
                    body: 'Somebody at '.$address.' said they did not ask for an email change. The account is suspended until an admin restores it.',
                );
            }

            try {
                Mail::to($admin->email)->send(new SecurityAlertMail((string) $user->name, (string) $user->user_id, $address));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
