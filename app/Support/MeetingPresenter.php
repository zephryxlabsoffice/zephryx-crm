<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Turns a meeting into the things its pages need to draw it.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TIMES ARE STORED IN UTC AND SHOWN IN ONE ZONE
 *
 * Decided 2026-08-28. Every date on a meeting record is UTC; nothing displays a
 * raw stored value. `at()` and friends are the only way a time reaches a page,
 * so there is one place the conversion happens and one place to change if the
 * displayed zone ever does.
 *
 * Storing local time is what makes a meeting with an overseas client drift by
 * an hour twice a year, when their daylight saving shifts and ours does not.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class MeetingPresenter
{
    /** A client or a colleague asked for it. Nothing exists on Google yet. */
    public const REQUESTED = 'requested';

    /** Created on Google. The invite has gone out and there is a link. */
    public const SCHEDULED = 'scheduled';

    /** Called off. The Google event is cancelled too, or it is not really off. */
    public const CANCELLED = 'cancelled';

    /**
     * ─────────────────────────────────────────────────────────────────────────
     * THERE IS NO "COMPLETED" STATUS
     *
     * The handover had one. Nothing in this system can see whether a meeting
     * took place — Google knows a room existed, not whether anybody joined it
     * or whether the conversation happened. "Completed" asserts something we
     * cannot know, and it is the kind of claim that gets quoted back.
     *
     * A scheduled meeting whose end time has passed reads as **Ended**, which
     * is a fact about the clock rather than about the meeting.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public const ENDED = 'ended';

    /** @var array<string, array{0: string, 1: string, 2: string}> tone, label, meaning */
    protected const STATUSES = [
        self::REQUESTED => ['pill-amber', 'Requested', 'Asked for, but not created on Google yet'],
        self::SCHEDULED => ['pill-green', 'Scheduled', 'On Google, invites sent, link ready'],
        self::ENDED => ['pill-gray', 'Ended', 'Its time has passed — whether it happened is not something this records'],
        self::CANCELLED => ['pill-red', 'Cancelled', 'Called off, and removed from everyone’s calendar'],
    ];

    /**
     * Where a meeting stands.
     *
     * Derived, not stored: `ended` in particular is a fact about the clock and
     * would go stale the moment it were written down.
     *
     * @param  array<string, mixed>  $meeting
     */
    public static function statusOf(array $meeting): string
    {
        if ($meeting['cancelled_at'] !== null) {
            return self::CANCELLED;
        }

        // No Google event means nothing was ever sent, whatever the clock says.
        if ($meeting['event_id'] === null) {
            return self::REQUESTED;
        }

        return Carbon::parse($meeting['ends_at'])->isPast() ? self::ENDED : self::SCHEDULED;
    }

    /**
     * @return array{tone: string, label: string, meaning: string}
     */
    public static function status(string $status): array
    {
        [$tone, $label, $meaning] = self::STATUSES[$status]
            ?? ['pill-gray', ucfirst(str_replace('_', ' ', $status)), ''];

        return ['tone' => $tone, 'label' => $label, 'meaning' => $meaning];
    }

    /**
     * @return list<string>
     */
    public static function statusOptions(): array
    {
        return array_keys(self::STATUSES);
    }

    /* ─────────────────────────  RSVP  ───────────────────────── */

    public const ACCEPTED = 'accepted';
    public const DECLINED = 'declined';
    public const TENTATIVE = 'tentative';
    public const AWAITING = 'awaiting';

    /** @var array<string, array{0: string, 1: string}> tone, label */
    protected const RESPONSES = [
        self::ACCEPTED => ['rsvp-yes', 'Coming'],
        self::DECLINED => ['rsvp-no', 'Not coming'],
        self::TENTATIVE => ['rsvp-maybe', 'Maybe'],
        self::AWAITING => ['rsvp-none', 'No reply yet'],
    ];

    /**
     * How an attendee responded.
     *
     * Read from Google Calendar, never set here — a person accepts or declines
     * in their own calendar. Anything unrecognised reads as no reply rather
     * than as attendance, because assuming somebody is coming is the error that
     * costs a meeting.
     *
     * @return array{tone: string, label: string}
     */
    public static function response(?string $response): array
    {
        [$tone, $label] = self::RESPONSES[$response ?? self::AWAITING]
            ?? self::RESPONSES[self::AWAITING];

        return ['tone' => $tone, 'label' => $label];
    }

    /* ─────────────────────────  times  ───────────────────────── */

    public static function zone(): string
    {
        return (string) config('meetings.display_timezone', 'Asia/Kolkata');
    }

    /**
     * A stored UTC time, in the zone this company reads.
     */
    public static function local(Carbon|string $utc): Carbon
    {
        return Carbon::parse($utc, 'UTC')->setTimezone(self::zone());
    }

    /**
     * `28 Aug 2026`
     */
    public static function date(Carbon|string $utc): string
    {
        return self::local($utc)->format('d M Y');
    }

    /**
     * `4:00 PM`
     */
    public static function time(Carbon|string $utc): string
    {
        return self::local($utc)->format('g:i A');
    }

    /**
     * `4:00 – 4:30 PM` — the span, with the meridiem stated once when both ends
     * share it.
     *
     * @param  array<string, mixed>  $meeting
     */
    public static function timeRange(array $meeting): string
    {
        $from = self::local($meeting['starts_at']);
        $to = self::local($meeting['ends_at']);

        return $from->format('A') === $to->format('A')
            ? $from->format('g:i').' – '.$to->format('g:i A')
            : $from->format('g:i A').' – '.$to->format('g:i A');
    }

    /**
     * `Fri, 28 Aug 2026 · 4:00 – 4:30 PM IST`
     *
     * The zone abbreviation is not decoration: a client reading this in another
     * country needs to know which four o'clock is meant.
     *
     * @param  array<string, mixed>  $meeting
     */
    public static function when(array $meeting): string
    {
        $from = self::local($meeting['starts_at']);

        return $from->format('D, d M Y').' · '.self::timeRange($meeting).' '.$from->format('T');
    }

    /**
     * @param  array<string, mixed>  $meeting
     */
    public static function duration(array $meeting): string
    {
        $minutes = (int) self::local($meeting['starts_at'])
            ->diffInMinutes(self::local($meeting['ends_at']));

        if ($minutes < 60) {
            return $minutes.' min';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest === 0 ? $hours.' hr' : $hours.' hr '.$rest.' min';
    }

    /**
     * How soon it is, for something still to come.
     *
     * @param  array<string, mixed>  $meeting
     * @return array{label: string, tone: string}
     */
    public static function timing(array $meeting): array
    {
        $start = Carbon::parse($meeting['starts_at'], 'UTC');
        $end = Carbon::parse($meeting['ends_at'], 'UTC');
        $now = Carbon::now('UTC');

        if ($end->isPast()) {
            return ['label' => 'Ended', 'tone' => ''];
        }

        if ($start->isPast()) {
            return ['label' => 'Happening now', 'tone' => 'is-overdue'];
        }

        $minutes = (int) $now->diffInMinutes($start, false);

        return match (true) {
            $minutes <= 60 => ['label' => "In {$minutes} min", 'tone' => 'is-overdue'],
            $minutes <= 60 * 24 => ['label' => 'In '.intdiv($minutes, 60).' hr', 'tone' => 'is-soon'],
            default => ['label' => 'In '.(int) $now->diffInDays($start, false).' days', 'tone' => ''],
        };
    }

    /**
     * Whether the meeting is close enough that a join button is worth offering.
     *
     * A Meet link works whenever, so this is not a lock — it is about not
     * putting a live "Join" beside a meeting three weeks out, where the only
     * reason to press it is by accident.
     *
     * @param  array<string, mixed>  $meeting
     */
    public static function isJoinable(array $meeting): bool
    {
        if (self::statusOf($meeting) !== self::SCHEDULED) {
            return false;
        }

        return Carbon::now('UTC')->greaterThanOrEqualTo(
            Carbon::parse($meeting['starts_at'], 'UTC')->subMinutes(15)
        );
    }
}
