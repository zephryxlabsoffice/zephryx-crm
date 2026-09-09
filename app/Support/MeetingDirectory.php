<?php

namespace App\Support;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Meetings, read from the database.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * EVERY READ TAKES THE VIEWER, AND THAT IS NOT CONVENIENCE
 *
 * The join link is withheld per person — a Meet link is effectively a password
 * — so there is no method here that returns a meeting without being told whose
 * behalf it is for. Same shape as TicketDirectory::commentsFor and
 * ProjectUpdate::clientVisible: the rule is kept by not providing the call that
 * could break it.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class MeetingDirectory
{
    /**
     * The list query, filtered by everything that is a column.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Meeting>
     */
    public static function query(array $filters = []): Builder
    {
        $search = ($filters['search'] ?? '') !== '' ? $filters['search'] : null;

        return Meeting::query()
            ->with(['project', 'organiser.user', 'requestedByClient', 'attendees.user'])
            ->when($search, fn (Builder $q, string $term) => $q->where(function (Builder $q) use ($term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

                $q->where('title', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhereHas('project', fn (Builder $p) => $p->where('name', 'like', $like));
            }))
            ->when($filters['project'] ?? null, fn (Builder $q, string $ref) => $q->whereHas(
                'project', fn (Builder $p) => $p->where('reference', $ref)
            ))
            // Soonest first. A meeting list is read to find out what is next.
            ->orderBy('starts_at');
    }

    /**
     * Rows for one viewer, with the status filter applied after they are built.
     *
     * Status is derived — cancelled, requested, scheduled or ended come from the
     * event id, the cancellation and the clock — so it is filtered here rather
     * than in SQL, for the same reason invoices are.
     *
     * @param  Builder<Meeting>  $query
     * @return Collection<int, array<string, mixed>>
     */
    public static function rows(Builder $query, ?User $viewer, ?string $status = null): Collection
    {
        return $query->get()
            ->map(fn (Meeting $m) => $m->toRecordArray($viewer))
            ->when($status, fn (Collection $rows) => $rows->where('status', $status))
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $reference, ?User $viewer): ?array
    {
        $meeting = Meeting::query()
            ->with(['project.client', 'organiser.user', 'organiser.designation', 'requestedByClient', 'attendees.user'])
            ->where('reference', $reference)
            ->first();

        return $meeting === null ? null : $meeting->toRecordArray($viewer) + ['model' => $meeting];
    }

    /**
     * The viewer's next meeting, or null.
     *
     * Theirs, not the company's: the card says "you are in this one in twenty
     * minutes", and the company's next meeting is not that.
     *
     * @return array<string, mixed>|null
     */
    public static function nextFor(?User $viewer): ?array
    {
        if ($viewer === null) {
            return null;
        }

        $meeting = Meeting::query()
            ->with(['project', 'organiser.user', 'requestedByClient', 'attendees.user'])
            ->upcoming()
            ->whereNotNull('event_id')
            ->where(function (Builder $q) use ($viewer) {
                $q->whereHas('attendees', fn (Builder $a) => $a->where('user_id', $viewer->id))
                    ->orWhereHas('organiser', fn (Builder $o) => $o->where('user_id', $viewer->id));
            })
            ->orderBy('starts_at')
            ->first();

        return $meeting?->toRecordArray($viewer);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $meetings
     * @return array<string, int>
     */
    public static function stats(Collection $meetings): array
    {
        $countOf = fn (string $status) => $meetings->where('status', $status)->count();

        return [
            'total' => $meetings->count(),
            'scheduled' => $countOf(MeetingPresenter::SCHEDULED),
            'requested' => $countOf(MeetingPresenter::REQUESTED),
            'ended' => $countOf(MeetingPresenter::ENDED),
            'cancelled' => $countOf(MeetingPresenter::CANCELLED),
        ];
    }

    /**
     * The next reference — year-scoped, from the highest existing one.
     */
    public static function nextReference(): string
    {
        $prefix = 'MTG-'.now()->year.'-';

        $highest = Meeting::query()
            ->where('reference', 'like', $prefix.'%')
            ->selectRaw('max(cast(substr(reference, ?) as integer)) as n', [strlen($prefix) + 1])
            ->value('n');

        return $prefix.str_pad((string) (((int) $highest) + 1), 3, '0', STR_PAD_LEFT);
    }
}
