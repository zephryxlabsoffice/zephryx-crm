<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Turns an announcement into the things its pages need to draw it.
 */
class AnnouncementPresenter
{
    public const DRAFT = 'draft';
    public const SCHEDULED = 'scheduled';
    public const ACTIVE = 'active';
    public const EXPIRED = 'expired';

    /** @var array<string, array{0: string, 1: string, 2: string}> tone, label, meaning */
    protected const STATUSES = [
        self::DRAFT => ['pill-gray', 'Draft', 'Written, not published — nobody else can see it'],
        self::SCHEDULED => ['pill-blue', 'Scheduled', 'Goes up on its publish date'],
        self::ACTIVE => ['pill-green', 'Active', 'On the board now'],
        self::EXPIRED => ['pill-gray', 'Expired', 'Past its end date — kept, but off the board'],
    ];

    /**
     * Where an announcement stands.
     *
     * Derived from two dates and a draft flag, so nothing can be marked active
     * while its publish date is next week. `expired` in particular is a fact
     * about the clock and would go stale the moment it were stored.
     *
     * @param  array<string, mixed>  $announcement
     */
    public static function statusOf(array $announcement): string
    {
        if ($announcement['draft'] ?? false) {
            return self::DRAFT;
        }

        $now = Carbon::now();

        if (Carbon::parse($announcement['published_at'])->isFuture()) {
            return self::SCHEDULED;
        }

        if ($announcement['expires_at'] !== null && Carbon::parse($announcement['expires_at'])->endOfDay()->isPast()) {
            return self::EXPIRED;
        }

        return self::ACTIVE;
    }

    /**
     * @return array{tone: string, label: string, meaning: string}
     */
    public static function status(string $status): array
    {
        [$tone, $label, $meaning] = self::STATUSES[$status]
            ?? ['pill-gray', ucfirst($status), ''];

        return ['tone' => $tone, 'label' => $label, 'meaning' => $meaning];
    }

    /**
     * @return list<string>
     */
    public static function statusOptions(): array
    {
        return array_keys(self::STATUSES);
    }

    /* ─────────────────────────  categories  ───────────────────────── */

    /**
     * @return array<string, array{label: string, tone: string, icon: string}>
     */
    public static function categories(): array
    {
        return config('announcements.categories', []);
    }

    /**
     * @return array{key: string, label: string, tone: string, icon: string}
     */
    public static function category(string $key): array
    {
        $category = self::categories()[$key] ?? [
            // A category removed from configuration still has to render. An
            // announcement pointing at one is a data problem, and hiding it
            // makes it an invisible one.
            'label' => ucfirst($key),
            'tone' => 'an-it',
            'icon' => 'announcements',
        ];

        return ['key' => $key] + $category;
    }

    /**
     * The categories a person may actually choose when writing.
     *
     * Milestones are generated from employee records — birthdays and work
     * anniversaries — so the compose form must not offer the category. A
     * hand-written "milestone" would sit in the feed looking identical to a
     * computed one and be wrong the following year.
     *
     * @return array<string, array{label: string, tone: string, icon: string}>
     */
    public static function authorableCategories(): array
    {
        return array_filter(
            self::categories(),
            fn (string $key) => self::isAuthorable($key),
            ARRAY_FILTER_USE_KEY
        );
    }

    public static function isAuthorable(string $category): bool
    {
        return $category !== 'milestone';
    }

    /** The category whose announcements close the office. */
    public const HOLIDAY = 'holiday';

    /**
     * The permission needed to set a holiday's closure dates.
     *
     * Separate from `post_permission` on purpose (2026-09-03): those dates are
     * read by Attendance and decide who is not marked absent, so setting them
     * is a write to the attendance record made through this form. Posting is
     * broad; closing the office is HR and the owner. See config/announcements.
     */
    public static function holidayPermission(): string
    {
        return (string) config('announcements.holiday_permission', 'announcements.holiday');
    }

    /* ─────────────────────────  audience  ───────────────────────── */

    /**
     * @return array<string, string>
     */
    public static function audiences(): array
    {
        return [
            'everyone' => 'Everyone',
            'department' => 'One department',
            'managers' => 'Managers only',
        ];
    }

    /**
     * @param  array<string, mixed>  $announcement
     */
    public static function audienceLabel(array $announcement): string
    {
        if ($announcement['audience'] === 'department' && $announcement['audience_value']) {
            return $announcement['audience_value'];
        }

        return self::audiences()[$announcement['audience']] ?? 'Everyone';
    }

    /* ─────────────────────────  dates  ───────────────────────── */

    public static function date(Carbon|string|null $when): string
    {
        return $when === null ? '—' : Carbon::parse($when)->format('d M Y');
    }

    public static function dateTime(Carbon|string|null $when): string
    {
        return $when === null ? '—' : Carbon::parse($when)->format('d M Y, g:i A');
    }

    /**
     * How long an active announcement has left.
     *
     * An announcement with no end date does not expire, and says so rather than
     * showing a blank — "no end date" is a decision somebody made.
     *
     * @param  array<string, mixed>  $announcement
     * @return array{label: string, tone: string}
     */
    public static function runsUntil(array $announcement): array
    {
        if ($announcement['expires_at'] === null) {
            return ['label' => 'No end date', 'tone' => ''];
        }

        $end = Carbon::parse($announcement['expires_at'])->endOfDay();

        if ($end->isPast()) {
            return ['label' => 'Ended '.self::date($announcement['expires_at']), 'tone' => ''];
        }

        $days = (int) Carbon::today()->diffInDays($end, false);

        return match (true) {
            $days === 0 => ['label' => 'Last day', 'tone' => 'is-soon'],
            $days <= 3 => ['label' => "Ends in {$days} days", 'tone' => 'is-soon'],
            default => ['label' => 'Until '.self::date($announcement['expires_at']), 'tone' => ''],
        };
    }

    /**
     * `2 hours ago`
     */
    public static function ago(Carbon|string $when): string
    {
        return Carbon::parse($when)->diffForHumans();
    }
}
