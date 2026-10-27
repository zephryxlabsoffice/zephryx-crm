<?php

namespace App\Support;

use App\Models\EmployeeProfile;
use App\Models\User;

/**
 * A staff member's two email addresses, and which one signs in.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE RULE (owner, 2026-10-27)
 *
 *   Personal email — required.  Work email — optional.
 *
 *   Until a work email exists, the personal email is the sign-in, and the
 *   one-time codes, invites and reset links go to it. Once a work email is
 *   added, BOTH sign in, and the mail goes to the work email.
 *
 * Replaces the September rule ("work email is the sign-in, personal email is
 * record only"): interns and freelancers often have no company mailbox yet.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHERE EACH ADDRESS LIVES — NO NEW COLUMNS
 *
 *   users.email                     the MAIL address: work email if there is
 *                                   one, otherwise the personal email
 *   employee_profiles.personal_email  the personal email, always
 *
 * So "the work email" is users.email when it differs from the personal one,
 * and nothing otherwise. Every address that signs in has to be unique across
 * BOTH columns, or one typed address could mean two people.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class StaffEmails
{
    /**
     * The address that goes on users.email.
     */
    public static function mailAddress(?string $personal, ?string $work): string
    {
        $work = self::clean($work);

        return $work !== '' ? $work : self::clean($personal);
    }

    /**
     * The work email on record, or null when the person has none (their
     * sign-in is their personal email).
     */
    public static function workEmailOf(?User $user, ?string $personal): ?string
    {
        if ($user === null) {
            return null;
        }

        return strcasecmp((string) $user->email, (string) $personal) === 0 ? null : $user->email;
    }

    /**
     * The account an email address signs in to: users.email first, then an
     * active staff member's personal email.
     */
    public static function findUser(string $email): ?User
    {
        $email = self::clean($email);

        $user = User::where('email', $email)->first();

        if ($user !== null) {
            return $user;
        }

        /*
         * Active accounts only. An intern converted to full-time keeps a closed
         * account carrying a copy of the same profile — the personal email
         * must find the live account, not the closed one.
         */
        $profile = EmployeeProfile::query()
            ->with('employee.user')
            ->where('personal_email', $email)
            ->whereHas('employee.user', fn ($q) => $q->where('status', 'active')->where('account_type', Realm::STAFF))
            ->first();

        return $profile?->employee?->user;
    }

    /**
     * Whether an address is already somebody else's — as a sign-in or as a
     * personal email.
     */
    public static function takenByAnother(string $email, ?int $ownUserId): bool
    {
        $email = self::clean($email);

        if ($email === '') {
            return false;
        }

        $asMail = User::where('email', $email)
            ->when($ownUserId !== null, fn ($q) => $q->where('id', '!=', $ownUserId))
            ->exists();

        // A closed account's personal email does not block anybody (see findUser).
        $asPersonal = EmployeeProfile::query()
            ->where('personal_email', $email)
            ->whereHas('employee.user', fn ($u) => $u->where('status', 'active')
                ->when($ownUserId !== null, fn ($q) => $q->where('id', '!=', $ownUserId)))
            ->exists();

        return $asMail || $asPersonal;
    }

    protected static function clean(?string $email): string
    {
        return trim((string) $email);
    }
}
