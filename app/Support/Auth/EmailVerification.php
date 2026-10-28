<?php

namespace App\Support\Auth;

use App\Mail\EmailChangedNoticeMail;
use App\Mail\EmailVerifyMail;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Which of somebody's addresses are proven to reach them (owner, 2026-10-28).
 *
 * Two addresses can sign in (StaffEmails): `users.email`, and a staff member's
 * personal email. Each carries its own stamp - `users.email_verified_at` and
 * `employee_profiles.personal_email_verified_at` - and they are often the same
 * address, in which case proving one proves both.
 *
 * - Choosing a password from an emailed link, or signing in with an emailed
 *   code, proves the sign-in address.
 * - Changing an address clears its stamp and emails a link to the new one,
 *   wherever the change was made (My Profile, HR, the Admin Panel) - the
 *   models call addressChanged() so no path can forget.
 * - My Profile can ask for the link again.
 */
class EmailVerification
{
    public const LINK_HOURS = 48;

    /** The person has just received mail at their sign-in address. */
    public static function proveSignInAddress(User $user): void
    {
        self::stamp($user, (string) $user->email);
    }

    /**
     * An address on this account changed and is now unproven. Sends the link;
     * a failed send is reported, never allowed to undo the change.
     */
    public static function addressChanged(User $user, string $address, ?string $old = null): void
    {
        $address = trim($address);

        self::warnOldAddress($user, (string) $old, $address);

        if ($address === '' || ! self::needsProof($user, $address)) {
            return;
        }

        // Moved onto the account's other address, which is already proven:
        // nothing to ask, just carry the proof across.
        $profile = self::profileOf($user);

        if ((self::same($address, $user->email) && $user->email_verified_at !== null)
            || ($profile !== null && self::same($address, $profile->personal_email) && $profile->personal_email_verified_at !== null)) {
            self::stamp($user, $address);

            return;
        }

        // Both models change in one save sometimes; one email per address.
        $sent = (array) request()->attributes->get('verify_mail_sent', []);

        if (in_array(strtolower($address), $sent, true)) {
            return;
        }

        request()->attributes->set('verify_mail_sent', [...$sent, strtolower($address)]);

        try {
            self::send($user, $address);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Email a link to every address on the account that is not verified yet.
     * Returns the addresses written to.
     *
     * @return list<string>
     */
    public static function sendPending(User $user): array
    {
        return collect([$user->email, self::profileOf($user)?->personal_email])
            ->map(fn ($a) => trim((string) $a))
            ->filter()
            ->unique(fn (string $a) => strtolower($a))
            ->filter(fn (string $a) => self::needsProof($user, $a))
            ->each(fn (string $a) => self::send($user, $a))
            ->values()
            ->all();
    }

    /** Whether this address on this account still needs proving. */
    public static function needsProof(User $user, string $address): bool
    {
        if (self::same($address, $user->email) && $user->email_verified_at === null) {
            return true;
        }

        $profile = self::profileOf($user);

        return $profile !== null && self::same($address, $profile->personal_email)
            && $profile->personal_email_verified_at === null;
    }

    /** The personal email's state, for My Profile. */
    public static function personalVerified(User $user): ?bool
    {
        $profile = self::profileOf($user);

        if ($profile === null || trim((string) $profile->personal_email) === '') {
            return null;
        }

        return $profile->personal_email_verified_at !== null;
    }

    /**
     * Follow a link. True when it matched an address still on the account.
     */
    public static function confirm(User $user, string $hash): bool
    {
        foreach ([$user->email, self::profileOf($user)?->personal_email] as $address) {
            if ($address !== null && hash_equals(self::hash($address), $hash)) {
                self::stamp($user, $address);

                return true;
            }
        }

        return false;
    }

    protected static function send(User $user, string $address): void
    {
        $url = URL::temporarySignedRoute(
            'email.verify',
            now()->addHours(self::LINK_HOURS),
            ['user' => $user->id, 'hash' => self::hash($address)],
        );

        Mail::to($address)->send(new EmailVerifyMail($url, (string) $user->name, $address, self::LINK_HOURS, self::notMeUrl($user, $address)));
    }

    /**
     * The red button: a signed link to the "This wasn't me" page. See
     * AppSupportAuthSecurityLock.
     */
    public static function notMeUrl(User $user, string $address): string
    {
        return URL::temporarySignedRoute(
            'account.not-me',
            now()->addDays(SecurityLock::LINK_DAYS),
            ['user' => $user->id, 'address' => $address],
        );
    }

    /**
     * Tell the address that was replaced. Its owner is the one who would
     * notice a change they did not make, so the red button matters most here.
     */
    protected static function warnOldAddress(User $user, string $old, string $new): void
    {
        $old = trim($old);

        if ($old === '' || self::same($old, $new)) {
            return;
        }

        $sent = (array) request()->attributes->get('change_notice_sent', []);

        if (in_array(strtolower($old), $sent, true)) {
            return;
        }

        request()->attributes->set('change_notice_sent', [...$sent, strtolower($old)]);

        try {
            Mail::to($old)->send(new EmailChangedNoticeMail((string) $user->name, $old, $new, self::notMeUrl($user, $old)));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Mark the address proven on whichever of the two places it is. Written
     * with forceFill on the columns alone so the models' change hooks see no
     * address change and send nothing.
     */
    protected static function stamp(User $user, string $address): void
    {
        if (self::same($address, $user->email) && $user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $profile = self::profileOf($user);

        if ($profile !== null && self::same($address, $profile->personal_email) && $profile->personal_email_verified_at === null) {
            $profile->forceFill(['personal_email_verified_at' => now()])->save();
        }
    }

    protected static function profileOf(User $user): ?EmployeeProfile
    {
        return EmployeeProfile::query()
            ->whereHas('employee', fn ($q) => $q->where('user_id', $user->id))
            ->first();
    }

    /** Tied to the address, so a link for an old address stops working. */
    protected static function hash(string $address): string
    {
        return sha1(strtolower(trim($address)));
    }

    protected static function same(?string $a, ?string $b): bool
    {
        return $a !== null && $b !== null && strcasecmp(trim($a), trim($b)) === 0;
    }
}
