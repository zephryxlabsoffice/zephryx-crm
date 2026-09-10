<?php

namespace App\Support;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The bell, read from the database.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THERE IS NO `all()`, AND THAT IS THE WHOLE DESIGN
 *
 * Every method here takes the reader as a REQUIRED first argument. The shape is
 * inherited from DemoNotifications, which took it from DemoTickets, and the
 * reason has not changed: forgetting to scope should be a syntax error rather
 * than one person reading another's queue.
 *
 * It matters more now than it did against a fixture. `Notification::query()` is
 * still reachable — this is not a sandbox — but nothing in the application
 * reaches for it, so a review question as blunt as "who calls the unscoped
 * query" has an answer, and the answer is nobody.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class NotificationDirectory
{
    /**
     * What the bell shows: the newest few, not everything.
     *
     * A panel listing two hundred rows is a page, and this one has to open and
     * be understood in a second.
     */
    public const BELL = 6;

    /**
     * One reader's queue, newest first.
     *
     * @return Builder<Notification>
     */
    public static function query(User $reader): Builder
    {
        return Notification::query()
            ->where('user_id', $reader->id)
            // `id` and not `created_at`: several notifications out of one write
            // — a meeting scheduled for four people — share a timestamp to the
            // second, and a tie there puts them in whatever order the database
            // felt like.
            ->orderByDesc('id');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function for(User $reader): Collection
    {
        return self::query($reader)->get()->map(fn (Notification $n) => $n->toRecordArray());
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function unreadFor(User $reader): Collection
    {
        return self::query($reader)->unread()->get()->map(fn (Notification $n) => $n->toRecordArray());
    }

    public static function unreadCountFor(User $reader): int
    {
        return self::query($reader)->unread()->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function bellFor(User $reader, int $limit = self::BELL): array
    {
        return self::query($reader)
            ->limit($limit)
            ->get()
            ->map(fn (Notification $n) => $n->toRecordArray())
            ->all();
    }

    /**
     * Mark the reader's unread rows read, and report how many moved.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * SCOPED TO THE READER, AND ONLY THE UNREAD ONES
     *
     * The `whereNull` is not an optimisation. Without it, pressing the button
     * twice would rewrite `read_at` on everything already read, moving the
     * timestamp that says when somebody actually saw a thing to the moment they
     * pressed a button about something else.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public static function markAllRead(User $reader): int
    {
        return self::query($reader)->unread()->update(['read_at' => now()]);
    }
}
