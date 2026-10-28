<?php

namespace App\Support;

use App\Mail\LaunchWelcomeMail;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Launch day (owner, 2026-10-28).
 *
 * The launch date is Admin → Settings → "Attendance starts on": everything
 * works before it, for data entry, and from it attendance is strict. On the
 * day itself every active staff member is emailed a welcome, and the first
 * time each of them opens the site on or after it they see a welcome popup.
 *
 * Nothing runs on a timer here except the nightly backup job, so the email
 * goes out from there at 2 AM and, as a fallback, from the first page anybody
 * opens that day - whichever comes first; it is only ever sent once per
 * launch date.
 */
class Launch
{
    public const SENT_KEY = 'launch.welcome_sent_for';

    protected const BATCH = 50;

    public static function date(): ?Carbon
    {
        return AttendancePolicy::startsOn();
    }

    /**
     * Whether this person should see the popup now. Marks them welcomed, so
     * it shows once per launch date.
     */
    public static function popupFor(?User $user): bool
    {
        $date = self::date();

        if ($user === null || $date === null || $user->account_type !== Realm::STAFF || Carbon::today()->lt($date)) {
            return false;
        }

        if ($user->launch_welcomed_for?->toDateString() === $date->toDateString()) {
            return false;
        }

        $user->forceFill(['launch_welcomed_for' => $date->toDateString()])->save();

        return true;
    }

    /**
     * Email the welcome if today is launch day and it has not gone yet.
     * Returns how many were emailed (0 when not due).
     */
    public static function sendWelcomeIfDue(): int
    {
        $date = self::date();

        if ($date === null || ! Carbon::today()->isSameDay($date)) {
            return 0;
        }

        $day = $date->toDateString();
        $sent = DB::table('company_settings')->where('key', self::SENT_KEY)->value('value');

        if ($sent !== null && json_decode($sent, true) === $day) {
            return 0;
        }

        // Claimed before sending, so two requests at once cannot both send.
        DB::table('company_settings')->updateOrInsert(
            ['key' => self::SENT_KEY],
            ['value' => json_encode($day), 'updated_at' => now(), 'created_at' => now()],
        );

        $emails = User::query()
            ->where('status', 'active')
            ->where('account_type', Realm::STAFF)
            ->whereNotNull('email')
            ->pluck('email')
            ->unique()
            ->values();

        try {
            foreach ($emails->chunk(self::BATCH) as $batch) {
                Mail::to(config('mail.from.address'))->bcc($batch->all())->send(new LaunchWelcomeMail($day));
            }
        } catch (Throwable $e) {
            report($e);
        }

        return $emails->count();
    }
}
