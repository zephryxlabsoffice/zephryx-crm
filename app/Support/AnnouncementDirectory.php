<?php

namespace App\Support;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The board, read from the database.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * TWO SOURCES, ONE FEED
 *
 * `authored()` is the table. `milestones()` is computed from employee records
 * and stored nowhere. `board()` merges them so the page reads as one feed, and
 * the managing table paginates `authored()` alone — a computed post has nothing
 * to edit, schedule or delete.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class AnnouncementDirectory
{
    /**
     * The managing query — every authored post, drafts included.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Announcement>
     */
    public static function query(array $filters = []): Builder
    {
        $search = ($filters['search'] ?? '') !== '' ? $filters['search'] : null;

        return Announcement::query()
            ->with(['author.user', 'author.designation', 'audienceDepartment'])
            ->when($search, fn (Builder $q, string $term) => $q->where(function (Builder $q) use ($term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

                $q->where('title', 'like', $like)
                    ->orWhere('body', 'like', $like)
                    ->orWhere('reference', 'like', $like);
            }))
            ->when($filters['category'] ?? null, fn (Builder $q, string $v) => $q->where('category', $v))
            // Pinned first, then newest — the order the board is read in.
            ->orderByDesc('pinned')
            ->orderByDesc('starts_on')
            ->orderByDesc('id');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function authored(?array $filters = null): Collection
    {
        return self::query($filters ?? [])->get()->map(fn (Announcement $a) => $a->toRecordArray());
    }

    /**
     * Today's milestones, shaped like announcements.
     *
     * Computed on every request, never stored: a birthday post written down
     * would be wrong the following year and would survive somebody opting out.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function milestones(): Collection
    {
        return collect(Milestones::today())->map(fn (array $milestone) => [
            'id' => 'MS-'.$milestone['kind'].'-'.$milestone['employee']['user_id'],
            'kind' => 'milestone',
            'title' => $milestone['title'],
            'body' => $milestone['body'],
            'category' => 'milestone',
            'author' => null,
            'author_record' => null,
            'audience' => 'everyone',
            'audience_value' => null,
            'draft' => false,
            'pinned' => false,
            'published_at' => now()->startOfDay()->toDateTimeString(),
            'expires_at' => now()->toDateString(),
            'observed_from' => null,
            'observed_to' => null,
            'status' => AnnouncementPresenter::ACTIVE,
            // Whose birthday or anniversary it is. The card draws their avatar
            // and department instead of an author, because nobody wrote it.
            'employee_record' => $milestone['employee'],
        ]);
    }

    /**
     * The board: what is live, plus today's milestones, newest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function board(): Collection
    {
        $live = Announcement::query()
            ->with(['author.user', 'author.designation', 'audienceDepartment'])
            ->live()
            ->orderByDesc('pinned')
            ->orderByDesc('starts_on')
            ->get()
            ->map(fn (Announcement $a) => $a->toRecordArray());

        return $live->concat(self::milestones())
            ->sortByDesc(fn (array $a) => [$a['pinned'] ? 1 : 0, $a['published_at']])
            ->values();
    }

    /**
     * The client board: live announcements marked `for_clients`, newest first.
     *
     * No milestones — those are internal (birthdays, anniversaries) and were
     * never meant for a client to read. No `audience` filtering either: that
     * column narrows which STAFF see a post and a client account holds none
     * of `everyone`/`department`/`managers` — see Announcement::scopeForClientBoard.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function forClients(): Collection
    {
        return Announcement::query()
            ->with(['author.user', 'author.designation'])
            ->forClientBoard()
            ->orderByDesc('pinned')
            ->orderByDesc('starts_on')
            ->get()
            ->map(fn (Announcement $a) => $a->toRecordArray());
    }

    /**
     * One client-visible announcement, or null if it is not on the client
     * board — closed off the same way an unowned record 404s, rather than
     * fetched and then checked.
     *
     * @return array<string, mixed>|null
     */
    public static function findForClients(string $reference): ?array
    {
        $announcement = Announcement::query()
            ->with(['author.user', 'author.designation'])
            ->forClientBoard()
            ->where('reference', $reference)
            ->first();

        return $announcement === null ? null : $announcement->toRecordArray();
    }

    /**
     * Every holiday notice that has been published, for App\Support\Holidays.
     *
     * Published only — a draft closes nothing.
     *
     * @return list<array<string, mixed>>
     */
    public static function holidays(): array
    {
        return Announcement::query()
            ->published()
            ->where('category', 'holiday')
            ->whereNotNull('observed_from')
            ->orderBy('observed_from')
            ->get()
            ->map(fn (Announcement $a) => $a->toRecordArray())
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $reference): ?array
    {
        $announcement = Announcement::query()
            ->with(['author.user', 'author.designation', 'audienceDepartment'])
            ->where('reference', $reference)
            ->first();

        return $announcement === null ? null : $announcement->toRecordArray() + ['model' => $announcement];
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
     * The board's filter chips: every category with something live in it.
     *
     * Counted from the board itself, so a figure can never disagree with the
     * list beneath it. A category with nothing in it is left out rather than
     * shown as a zero — an empty filter is a control that does nothing.
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

    /**
     * The next reference — year-scoped, from the highest existing one.
     */
    public static function nextReference(): string
    {
        $prefix = 'ANN-'.now()->year.'-';

        $highest = Announcement::query()
            ->where('reference', 'like', $prefix.'%')
            ->selectRaw('max(cast(substr(reference, ?) as integer)) as n', [strlen($prefix) + 1])
            ->value('n');

        return $prefix.str_pad((string) (((int) $highest) + 1), 3, '0', STR_PAD_LEFT);
    }
}
