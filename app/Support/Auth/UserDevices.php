<?php

namespace App\Support\Auth;

use App\Http\Middleware\EnforceSessionLifetime;
use App\Mail\NewDeviceMail;
use App\Models\User;
use App\Models\UserDevice;
use App\Support\Audit\AuditLog;
use App\Support\Realm;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
use Throwable;

/**
 * My Devices and the new-device email (owner, 2026-11-02).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * A DEVICE IS A BROWSER WITH ITS OWN LONG-LIVED COOKIE
 *
 * Device trust cannot answer "have we seen this one before": it ends after
 * seven days and on every sign-out, so everybody would be told about a "new"
 * device they have used for months. This cookie proves nothing and unlocks
 * nothing, it only stops that false alarm, so it stays after a sign-out.
 *
 * SIGNING A DEVICE OUT WORKS ON EVERY SESSION DRIVER
 *
 * The session holds a token and so does the device's row, and they are compared
 * on every request (App\Http\Middleware\TrackSignedInDevice). Clearing the row's
 * token ends that session the next time it is used. A new token per sign-in
 * means a copy of an old session cookie does not come back to life when the
 * same browser signs in again.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class UserDevices
{
    public const COOKIE = 'zx_browser';

    public const COOKIE_DAYS = 365;

    protected const SESSION_TOKEN = 'auth.device_token_';

    /** How often a device's "last active" and address are rewritten. */
    protected const TOUCH_SECONDS = 60;

    public function __construct(private AuditLog $audit) {}

    /**
     * Whether the My Devices migration has been applied.
     *
     * After an upload the new code runs on the old database until somebody signs
     * in and presses Admin > Updates. This code runs at sign-in and on every page,
     * so it must step aside until then, or that page can never be reached. The
     * column is added last, so it standing means the table does too.
     */
    public function isReady(): bool
    {
        return Schema::hasColumn('trusted_devices', 'user_device_id');
    }

    /**
     * Record a sign-in on this browser and tie the session to it.
     *
     * `isNew` is false for the very first device an account ever has. That is
     * its first sign-in, or its first since this release, and neither is news:
     * otherwise everybody would get a false alarm on the day this went live.
     *
     * @return array{device: UserDevice, isNew: bool, cookie: Cookie}
     */
    public function signIn(User $user, Request $request): array
    {
        $browser = $request->cookie(self::COOKIE);
        $browser = is_string($browser) && $browser !== '' ? $browser : Str::random(64);

        $hash = hash('sha256', $browser);
        $hadDevices = UserDevice::where('user_id', $user->id)->exists();

        $device = UserDevice::firstOrNew(['user_id' => $user->id, 'browser_hash' => $hash]);
        $isNew = ! $device->exists && $hadDevices;

        $token = Str::random(64);

        $device->fill($this->details($request) + [
            'sign_in_token' => $token,
            'signed_in_at' => now(),
            'last_seen_at' => now(),
        ])->save();

        $request->session()->put(self::SESSION_TOKEN.$user->id, $token);

        return [
            'device' => $device,
            'isNew' => $isNew,
            'cookie' => cookie(
                name: self::COOKIE,
                value: $browser,
                minutes: self::COOKIE_DAYS * 24 * 60,
                secure: $request->isSecure(),
                httpOnly: true,
                sameSite: 'lax',
            ),
        ];
    }

    /** Whether this session has been tied to a device for this account yet. */
    public function isTracked(User $user, Request $request): bool
    {
        return $this->sessionToken($user, $request) !== null;
    }

    /** The token this session holds, which marks this browser on the page. */
    public function sessionToken(User $user, Request $request): ?string
    {
        return $request->session()->get(self::SESSION_TOKEN.$user->id);
    }

    /** The device this session belongs to, or null if it has been signed out. */
    public function forSession(User $user, Request $request): ?UserDevice
    {
        $token = $this->sessionToken($user, $request);

        if (! is_string($token) || $token === '') {
            return null;
        }

        return UserDevice::where('user_id', $user->id)->where('sign_in_token', $token)->first();
    }

    public function touch(UserDevice $device, Request $request): void
    {
        if ($device->last_seen_at !== null && $device->last_seen_at->diffInSeconds(now()) < self::TOUCH_SECONDS) {
            return;
        }

        $device->fill($this->details($request) + ['last_seen_at' => now()])->save();
    }

    /**
     * Everywhere this account is signed in, this browser first.
     *
     * Sessions that have aged out never tell anybody they ended, so the limits
     * of EnforceSessionLifetime are applied here too.
     *
     * @return Collection<int, UserDevice>
     */
    public function signedIn(User $user, Request $request): Collection
    {
        if (! $this->isReady()) {
            return new Collection;
        }

        $current = $this->sessionToken($user, $request);

        return UserDevice::where('user_id', $user->id)
            ->whereNotNull('sign_in_token')
            ->where('signed_in_at', '>', now()->subDays(EnforceSessionLifetime::ABSOLUTE_DAYS))
            ->where('last_seen_at', '>', now()->subMinutes(
                $user->account_type === Realm::ADMIN ? EnforceSessionLifetime::ADMIN_IDLE_MINUTES : EnforceSessionLifetime::IDLE_MINUTES
            ))
            ->orderByDesc('last_seen_at')
            ->get()
            ->sortByDesc(fn (UserDevice $device) => $device->sign_in_token === $current)
            ->values();
    }

    /**
     * Sign one device out. Its trust goes too, so getting back in takes the
     * password and a fresh code, and a remember-me cookie cannot restore it.
     */
    public function signOut(UserDevice $device): void
    {
        $device->forceFill(['sign_in_token' => null])->save();

        DB::table('trusted_devices')
            ->where('user_device_id', $device->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'updated_at' => now()]);
    }

    /** This browser only, for the ordinary Log out button. */
    public function signOutCurrent(User $user, Request $request): void
    {
        if (! $this->isReady()) {
            return;
        }

        $this->forSession($user, $request)?->forceFill(['sign_in_token' => null])->save();
    }

    /**
     * Every device, or every one but this request's, for the places that already
     * end all of an account's sessions. Trust is theirs to deal with: some keep
     * it on purpose (see Admin\AccountController::signOutEverywhere).
     */
    public function signOutAll(User $user, ?Request $except = null): void
    {
        if (! $this->isReady()) {
            return;
        }

        UserDevice::where('user_id', $user->id)
            ->whereNotNull('sign_in_token')
            ->when($except !== null, fn ($query) => $query->where(
                'sign_in_token', '!=', (string) $this->sessionToken($user, $except)
            ))
            ->update(['sign_in_token' => null]);
    }

    /**
     * Email the account about a device it has not used before.
     *
     * A failure is reported and goes no further. The person has already proved
     * who they are, and a mail outage must not stop them signing in.
     */
    public function announce(User $user, UserDevice $device, Request $request): void
    {
        $this->audit->record(
            action: AuditLog::NEW_DEVICE,
            actor: $user,
            entityType: 'user',
            entityId: $user->user_id,
            after: $device->device_name.($device->location ? ', '.$device->location : ''),
            request: $request,
        );

        try {
            Mail::to($user->email)->send(new NewDeviceMail(
                name: (string) $user->name,
                deviceName: $device->device_name,
                location: $device->location ?? 'Location unknown',
                ip: $device->ip_address,
                signedInAt: $device->signed_in_at->format('j M Y, g:i A'),
                devicesUrl: route(self::routeNames($user->account_type).'index'),
                notMeUrl: SecurityLock::url($user, (string) $user->email, SecurityLock::NEW_DEVICE),
            ));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** The route-name prefix of the page in each realm. */
    public static function routeNames(?string $realm): string
    {
        return match ($realm) {
            Realm::ADMIN => 'admin.mydevices.',
            Realm::CLIENT => 'client.mydevices.',
            default => 'mydevices.',
        };
    }

    /**
     * @return array{device_name: string, user_agent: string, ip_address: ?string, location: ?string}
     */
    protected function details(Request $request): array
    {
        return [
            'device_name' => DeviceDescription::name($request->userAgent()),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
            'ip_address' => $request->ip(),
            'location' => DeviceDescription::location($request),
        ];
    }
}
