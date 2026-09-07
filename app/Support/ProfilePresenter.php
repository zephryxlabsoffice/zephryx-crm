<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Turns a profile into the things its pages need to draw it.
 */
class ProfilePresenter
{
    /*
     * The four pages.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * TABS ARE ROUTES, NOT BUTTONS
     *
     * The handover's four tabs were <button>s switched by an inline <script>,
     * which our Content-Security-Policy blocks — so they would not have switched
     * at all. They are also four genuinely different things: a form, a set of
     * preferences, a security action and a log. Each gets its own URL, so it is
     * bookmarkable, the back button works, and each can be permission-checked on
     * its own rather than all four sharing whatever the page was granted.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @var array<string, array{0: string, 1: string}> route, label
     */
    protected const TABS = [
        'details' => ['profile.show', 'Personal information'],
        'preferences' => ['profile.preferences', 'Preferences'],
        'password' => ['profile.password', 'Password'],
        'activity' => ['profile.activity', 'Activity'],
    ];

    /**
     * @return array<string, array{route: string, label: string}>
     */
    public static function tabs(): array
    {
        $tabs = [];

        foreach (self::TABS as $key => [$route, $label]) {
            $tabs[$key] = ['route' => $route, 'label' => $label];
        }

        return $tabs;
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE ACTIVITY LOG
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * ─────────────────────────────────────────────────────────────────────────
     * THIS LOG ONLY CLAIMS WHAT IS ACTUALLY RECORDED
     *
     * The handover's read "New login from Kolkata, IN" — which means IP
     * geolocation, a third-party lookup service, and a location attached to
     * every sign-in. None of that exists, and none of it was decided. An entry
     * that invents a fact is worse than no entry, because this is the screen
     * somebody checks when they think their account has been used by somebody
     * else, and a wrong city sends them chasing nothing.
     *
     * So the kinds below are exactly the events the audit log (§6) will hold:
     * who did what, and when. Where it was done from is a separate decision,
     * and if it is ever taken, it lands here as its own kind.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @var array<string, array{0: string, 1: string}> tone, label
     */
    protected const ACTIVITY = [
        'sign_in' => ['tone-accent', 'Signed in'],
        'sign_out' => ['tone-soft', 'Signed out'],
        'password_changed' => ['tone-warn', 'Password changed'],
        'profile_updated' => ['tone-soft', 'Profile updated'],
        'preferences_updated' => ['tone-soft', 'Preferences updated'],
        'email_changed' => ['tone-warn', 'Email address changed'],
        'document_uploaded' => ['tone-alt', 'Document uploaded'],
        'hr_updated' => ['tone-alt', 'Updated by HR'],
    ];

    /**
     * @return array{tone: string, label: string}
     */
    public static function activity(string $kind): array
    {
        [$tone, $label] = self::ACTIVITY[$kind] ?? ['tone-soft', ucfirst(str_replace('_', ' ', $kind))];

        return ['tone' => $tone, 'label' => $label];
    }

    /**
     * @return list<string>
     */
    public static function activityKinds(): array
    {
        return array_keys(self::ACTIVITY);
    }

    /**
     * Whether an entry is a security event rather than routine housekeeping.
     *
     * Marked on the log because these are the lines somebody scans for when
     * they are worried, and "you changed your password" buried between two
     * "preferences updated" is the one that gets missed.
     */
    public static function isSecurityEvent(string $kind): bool
    {
        return in_array($kind, ['password_changed', 'email_changed', 'sign_in'], true);
    }

    /* ══════════════════════════════════════════════════════════════════════
       FORMATTING
       ══════════════════════════════════════════════════════════════════════ */

    public static function date(Carbon|string|null $when): string
    {
        return $when === null ? '—' : Carbon::parse($when)->format('d M Y');
    }

    public static function dateTime(Carbon|string|null $when): string
    {
        return $when === null ? '—' : Carbon::parse($when)->format('d M Y, g:i A');
    }

    /**
     * A birthday as a day and a month. Never the year.
     *
     * The same rule Announcements holds to (App\Support\Milestones): a colleague
     * needs to know when to say happy birthday, not how old somebody is. It
     * applies on a person's OWN profile too — not to protect them from
     * themselves, but because a screenshot of this page is a screenshot of this
     * page, and the year is the half that matters if it leaves the building.
     */
    public static function dayAndMonth(Carbon|string|null $when): string
    {
        return $when === null ? '—' : Carbon::parse($when)->format('d F');
    }

    /**
     * `2 years, 4 months` — how long somebody has been here.
     */
    public static function tenure(Carbon|string|null $joined): string
    {
        if ($joined === null) {
            return '—';
        }

        $start = Carbon::parse($joined);

        if ($start->isFuture()) {
            return 'Starts '.$start->format('d M Y');
        }

        $months = (int) $start->diffInMonths(Carbon::today());

        if ($months < 1) {
            return 'Joined this month';
        }

        if ($months < 12) {
            return $months.' '.($months === 1 ? 'month' : 'months');
        }

        $years = intdiv($months, 12);
        $rest = $months % 12;

        $said = $years.' '.($years === 1 ? 'year' : 'years');

        return $rest === 0 ? $said : $said.', '.$rest.' '.($rest === 1 ? 'month' : 'months');
    }

    /**
     * A file size somebody can read. `245 KB`, `1.2 MB`.
     */
    public static function fileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024).' KB';
        }

        return round($bytes / (1024 * 1024), 1).' MB';
    }

    /**
     * How long since a password was last changed, and whether to say anything.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * A NUMBER, NOT A NAG
     *
     * §4.7 is explicit that there is NO forced rotation — it is known to produce
     * weaker passwords, because people iterate a digit rather than think. So
     * this reports the age and stops. It does not expire anything, does not
     * colour it red at ninety days, and does not ask anybody to change a
     * password that is fine.
     *
     * It is here because "when did I last change this" is a reasonable question
     * with a factual answer, which is a different thing from a reminder.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public static function passwordAge(Carbon|string|null $changed): string
    {
        if ($changed === null) {
            return 'Never changed since your account was created.';
        }

        $days = (int) Carbon::parse($changed)->diffInDays(Carbon::today());

        return match (true) {
            $days === 0 => 'Changed today.',
            $days === 1 => 'Changed yesterday.',
            $days < 31 => "Changed {$days} days ago.",
            default => 'Changed on '.self::date($changed).'.',
        };
    }
}
