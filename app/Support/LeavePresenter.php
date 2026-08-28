<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Turns a leave request into the things its pages need to draw it.
 */
class LeavePresenter
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const CANCELLED = 'cancelled';

    /**
     * ─────────────────────────────────────────────────────────────────────────
     * REJECTED AND CANCELLED ARE NOT THE SAME COLOUR
     *
     * The handover drew both red. They are opposite events: cancelled is
     * something the employee did to their own request, rejected is something
     * that was done to them. Someone scanning their own history should be able
     * to tell "I withdrew this" from "this was refused" without reading the
     * word, and red on both makes the withdrawal look like a refusal.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @var array<string, array{0: string, 1: string, 2: string}> tone, label, meaning
     */
    protected const STATUSES = [
        self::PENDING => ['pill-amber', 'Pending', 'Waiting for a decision'],
        self::APPROVED => ['pill-green', 'Approved', 'Granted — these days come off the balance'],
        self::REJECTED => ['pill-red', 'Rejected', 'Refused, with a reason on the request'],
        // "Withdrawn", not "Cancelled": it says who did it. The key stays
        // `cancelled` because that is the state, but nothing shows that word to
        // a reader — one vocabulary, or the tab and the pill disagree.
        self::CANCELLED => ['pill-gray', 'Withdrawn', 'Pulled by the person who asked'],
    ];

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

    /**
     * A request can only be decided while it is pending.
     *
     * The guard behind both approve and reject: deciding an already-decided
     * request would silently overwrite somebody else's decision.
     *
     * @param  array<string, mixed>  $request
     */
    public static function isDecidable(array $request): bool
    {
        return $request['status'] === self::PENDING;
    }

    /**
     * Whether the person who asked can still withdraw it.
     *
     * Pending, obviously. Also approved-but-not-started, because plans change
     * and the alternative is somebody's balance being spent on days they did
     * not take. Leave already under way is a conversation, not a button.
     *
     * @param  array<string, mixed>  $request
     */
    public static function isCancellable(array $request): bool
    {
        if (! in_array($request['status'], [self::PENDING, self::APPROVED], true)) {
            return false;
        }

        return Carbon::parse($request['from'])->startOfDay()->isFuture();
    }

    /**
     * The dates a request covers, written the way people say them.
     *
     * `20 Aug 2026 (Thu)` for one day; `20–24 Aug 2026 (Thu–Mon)` for a span.
     * The weekday matters more than it looks: it is how somebody notices that
     * the range they typed runs across a weekend.
     *
     * @param  array<string, mixed>  $request
     */
    public static function range(array $request): string
    {
        $from = Carbon::parse($request['from']);
        $to = Carbon::parse($request['to']);

        if ($from->isSameDay($to)) {
            return $from->format('d M Y').' ('.$from->format('D').')';
        }

        $dates = $from->isSameMonth($to) && $from->isSameYear($to)
            ? $from->format('d').'–'.$to->format('d M Y')
            : $from->format('d M Y').' – '.$to->format('d M Y');

        return $dates.' ('.$from->format('D').'–'.$to->format('D').')';
    }

    /**
     * `1 day` / `2.5 days`.
     *
     * Half days are shown when they were asked for. Nothing here works the
     * figure out — the requester states it and the approver agrees it; see
     * App\Support\LeavePolicy for why.
     *
     * @param  array<string, mixed>  $request
     */
    public static function duration(array $request): string
    {
        $days = (float) $request['days'];
        $shown = fmod($days, 1.0) === 0.0 ? (string) (int) $days : rtrim(rtrim(number_format($days, 1), '0'), '.');

        return $shown.' '.($days === 1.0 ? 'day' : 'days');
    }

    /**
     * How far away the leave is, for a pending request.
     *
     * An approver reading a queue needs to know which decision is urgent, and
     * "starts tomorrow" is the thing that makes one urgent.
     *
     * @param  array<string, mixed>  $request
     * @return array{label: string, tone: string}
     */
    public static function timing(array $request): array
    {
        $from = Carbon::parse($request['from'])->startOfDay();
        $to = Carbon::parse($request['to'])->endOfDay();
        $today = Carbon::today();

        if ($to->isPast()) {
            return ['label' => 'Already over', 'tone' => ''];
        }

        if ($from->lessThanOrEqualTo($today)) {
            return ['label' => 'Under way', 'tone' => 'is-soon'];
        }

        $days = (int) $today->diffInDays($from, false);

        return match (true) {
            // A request for tomorrow that nobody has decided is the one that
            // costs somebody a day off, so it gets the loudest tone.
            $days <= 1 => ['label' => $days === 0 ? 'Starts today' : 'Starts tomorrow', 'tone' => 'is-overdue'],
            $days <= 7 => ['label' => "Starts in {$days} days", 'tone' => 'is-soon'],
            default => ['label' => "Starts in {$days} days", 'tone' => ''],
        };
    }

    public static function date(Carbon|string|null $when): string
    {
        return $when === null ? '—' : Carbon::parse($when)->format('d M Y');
    }

    public static function dateTime(Carbon|string|null $when): string
    {
        return $when === null ? '—' : Carbon::parse($when)->format('d M Y, g:i A');
    }

    /**
     * Whether two requests cover any of the same days.
     *
     * Used to tell an approver who else is already off. Inclusive at both ends,
     * because a request that ends the day another begins does not overlap but
     * one that ends the same day another ends does.
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    public static function overlaps(array $a, array $b): bool
    {
        return Carbon::parse($a['from'])->startOfDay()->lessThanOrEqualTo(Carbon::parse($b['to'])->endOfDay())
            && Carbon::parse($b['from'])->startOfDay()->lessThanOrEqualTo(Carbon::parse($a['to'])->endOfDay());
    }
}
