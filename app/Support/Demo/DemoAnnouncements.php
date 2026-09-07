<?php

namespace App\Support\Demo;

use App\Support\AnnouncementPresenter;
use App\Support\Milestones;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sample announcements, for reviewing the board before the database exists.
 * Local + debug only, like the other demo sources.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TWO SOURCES, ONE BOARD
 *
 * Authored posts are written by a person and live here. Milestone posts —
 * birthdays and work anniversaries — are COMPUTED from employee records by
 * App\Support\Milestones and are never stored: a stored birthday post would be
 * wrong the following year, and would survive somebody opting out.
 *
 * `all()` merges the two so the board reads as one feed, and
 * `authored()` is what the managing table paginates, because a computed post
 * has nothing to edit, schedule or delete.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class DemoAnnouncements
{
    public const VIEWER = 'EMP002';

    public static function enabled(): bool
    {
        return app()->environment('local') && config('app.debug');
    }

    /**
     * `from` and `to` are day offsets from today; `to` of null never expires.
     *
     * @return list<array<string, mixed>>
     */
    protected static function rows(): array
    {
        return [
            /*
             * Holiday announcements carry `observed` — the days the OFFICE IS
             * SHUT — as well as `from`/`to`, which is only how long the notice
             * stays on the board (added 2026-09-03).
             *
             * They are not the same range and confusing them is expensive: this
             * notice is up for a week and closes the office for one day, and
             * Attendance reads `observed` to decide who is not absent. See
             * App\Support\Holidays.
             */
            [
                'id' => 'ANN-2026-036', 'title' => 'Office closed for the festival holiday',
                'category' => 'holiday', 'author' => 'EMP005', 'audience' => 'everyone', 'audience_value' => null,
                'from' => -2, 'to' => 12, 'draft' => false, 'pinned' => true,
                'observed' => [10, 10],
                'body' => 'The office is closed for the day. Client calls have been moved — check Meetings for the new times.',
            ],
            // A holiday that has already been and gone. Its notice is long
            // expired; the day it closed is still a day nobody was absent for,
            // which is why Holidays reads published announcements rather than
            // live ones.
            [
                'id' => 'ANN-2026-037', 'title' => 'Office closed for Independence Day',
                'category' => 'holiday', 'author' => 'EMP005', 'audience' => 'everyone', 'audience_value' => null,
                'from' => -26, 'to' => -22, 'draft' => false, 'pinned' => false,
                'observed' => [-24, -24],
                'body' => 'The office was closed for the public holiday.',
            ],
            [
                'id' => 'ANN-2026-035', 'title' => 'New laptop policy',
                'category' => 'policy', 'author' => 'EMP005', 'audience' => 'everyone', 'audience_value' => null,
                'from' => -5, 'to' => null, 'draft' => false, 'pinned' => false,
                'body' => 'Machines are replaced every three years, or sooner if repair costs more than half a replacement. Raise a ticket under IT Support to start one.',
            ],
            [
                'id' => 'ANN-2026-034', 'title' => 'Advanced Excel training — Thursday',
                'category' => 'training', 'author' => 'EMP005', 'audience' => 'everyone', 'audience_value' => null,
                'from' => -1, 'to' => 3, 'draft' => false, 'pinned' => false,
                'body' => 'Two hours, Thursday afternoon, run internally. Optional, but useful if you touch reporting.',
            ],
            [
                'id' => 'ANN-2026-033', 'title' => 'Quarterly reviews open next week',
                'category' => 'hr', 'author' => 'EMP005', 'audience' => 'everyone', 'audience_value' => null,
                'from' => 3, 'to' => 20, 'draft' => false, 'pinned' => false,
                'body' => 'Self-assessments go out on Monday. You will have a week to complete yours before the conversations start.',
            ],
            [
                'id' => 'ANN-2026-032', 'title' => 'Server maintenance on Sunday',
                'category' => 'it', 'author' => 'EMP004', 'audience' => 'everyone', 'audience_value' => null,
                'from' => 1, 'to' => 4, 'draft' => false, 'pinned' => false,
                'body' => 'The CRM will be unavailable from 2am to about 5am on Sunday while the host applies updates.',
            ],
            [
                'id' => 'ANN-2026-031', 'title' => 'Design team stand-up moving to 9:30',
                'category' => 'event', 'author' => 'EMP001', 'audience' => 'department', 'audience_value' => 'Design',
                'from' => -8, 'to' => null, 'draft' => false, 'pinned' => false,
                'body' => 'From next week the design stand-up is at 9:30 rather than 10, so it does not clash with the client call.',
            ],
            [
                'id' => 'ANN-2026-030', 'title' => 'Team outing — save the date',
                'category' => 'event', 'author' => 'EMP005', 'audience' => 'everyone', 'audience_value' => null,
                'from' => -30, 'to' => -12, 'draft' => false, 'pinned' => false,
                'body' => 'A day out at the end of the month. Details to follow once numbers are confirmed.',
            ],
            [
                'id' => 'ANN-2026-029', 'title' => 'Revised leave policy',
                'category' => 'policy', 'author' => 'EMP005', 'audience' => 'everyone', 'audience_value' => null,
                'from' => -45, 'to' => -20, 'draft' => false, 'pinned' => false,
                'body' => 'Superseded by the current policy in the Leave module.',
            ],
            // A draft: written, not published, and nobody else can see it.
            [
                'id' => 'ANN-2026-028', 'title' => 'Health insurance renewal',
                'category' => 'hr', 'author' => 'EMP005', 'audience' => 'everyone', 'audience_value' => null,
                'from' => 7, 'to' => 30, 'draft' => true, 'pinned' => false,
                'body' => 'Waiting on the final numbers from the broker before this goes out.',
            ],
            [
                'id' => 'ANN-2026-027', 'title' => 'Managers: budget submissions',
                'category' => 'hr', 'author' => 'EMP005', 'audience' => 'managers', 'audience_value' => null,
                'from' => 2, 'to' => 16, 'draft' => false, 'pinned' => false,
                'body' => 'Next quarter’s figures are due by the end of the month.',
            ],
        ];
    }

    /**
     * The authored posts — what the managing table lists.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function authored(): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        $employees = DemoEmployees::all()->keyBy('user_id');

        return collect(self::rows())->map(function (array $row) use ($employees) {
            $announcement = $row + [
                'kind' => 'authored',
                'author_record' => $employees->get($row['author']),
                'published_at' => Carbon::today()->addDays($row['from'])->setTime(9, 0)->toDateTimeString(),
                'expires_at' => $row['to'] === null ? null : Carbon::today()->addDays($row['to'])->toDateString(),
                // The days the office is shut, which is NOT the window the
                // notice is up for. Null on everything that is not a holiday.
                'observed_from' => isset($row['observed']) ? Carbon::today()->addDays($row['observed'][0])->toDateString() : null,
                'observed_to' => isset($row['observed']) ? Carbon::today()->addDays($row['observed'][1])->toDateString() : null,
            ];

            $announcement['status'] = AnnouncementPresenter::statusOf($announcement);

            return $announcement;
        })->values();
    }

    /**
     * Today's milestones, shaped like announcements so the board renders one
     * feed rather than two.
     *
     * Computed on every request. A stored birthday post would be wrong the
     * following year and would survive somebody opting out.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function milestones(): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        return collect(Milestones::today())->map(fn (array $milestone) => [
            'id' => 'MS-'.$milestone['kind'].'-'.$milestone['employee']['user_id'],
            'title' => $milestone['title'],
            'body' => $milestone['body'],
            'category' => 'milestone',
            'kind' => 'milestone',
            'milestone_kind' => $milestone['kind'],
            'author' => null,
            'author_record' => null,
            'employee_record' => $milestone['employee'],
            'audience' => 'everyone',
            'audience_value' => null,
            'published_at' => Carbon::today()->setTime(9, 0)->toDateTimeString(),
            'expires_at' => Carbon::today()->toDateString(),
            'draft' => false,
            'pinned' => false,
            'status' => AnnouncementPresenter::ACTIVE,
        ])->values();
    }

    /**
     * Published holiday announcements that name the days the office is shut.
     *
     * Note what is NOT filtered here: status. A holiday whose notice expired
     * three weeks ago is still a day the office was closed, and dropping it
     * would mark everybody absent for it retrospectively. Only drafts are
     * excluded — an unpublished notice closes nothing.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function holidays(): Collection
    {
        return self::authored()
            ->where('category', 'holiday')
            ->where('draft', false)
            ->filter(fn (array $row) => $row['observed_from'] !== null)
            ->values();
    }

    /**
     * The board: what is on it right now, milestones included, pinned first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function board(): Collection
    {
        return self::authored()
            ->where('status', AnnouncementPresenter::ACTIVE)
            ->concat(self::milestones())
            ->sortByDesc(fn (array $a) => [$a['pinned'] ? 1 : 0, $a['published_at']])
            ->values();
    }

    public static function find(string $id): ?array
    {
        return self::authored()->firstWhere('id', $id)
            ?? self::milestones()->firstWhere('id', $id);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $announcements
     * @return array<string, int>
     */
    public static function stats(Collection $announcements): array
    {
        $countOf = fn (string $status) => $announcements->where('status', $status)->count();

        return [
            'total' => $announcements->count(),
            'active' => $countOf(AnnouncementPresenter::ACTIVE),
            'scheduled' => $countOf(AnnouncementPresenter::SCHEDULED),
            'draft' => $countOf(AnnouncementPresenter::DRAFT),
            'expired' => $countOf(AnnouncementPresenter::EXPIRED),
        ];
    }

    /**
     * How many live announcements sit in each category — the rail list.
     *
     * Computed from the board rather than stored, so a count can never
     * disagree with the list beneath it. The handover's counts were written in.
     *
     * @return list<array{key: string, label: string, tone: string, icon: string, count: int}>
     */
    public static function categoryCounts(): array
    {
        $board = self::board();
        $counts = [];

        foreach (AnnouncementPresenter::categories() as $key => $meta) {
            $count = $board->where('category', $key)->count();

            if ($count === 0) {
                continue;
            }

            $counts[] = ['key' => $key, 'count' => $count] + $meta;
        }

        return $counts;
    }
}
