<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Tickets, read from the database.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THERE IS NO METHOD HERE THAT RETURNS A THREAD WITHOUT AN AUDIENCE
 *
 * `commentsFor()` takes the audience as a required argument, exactly as the
 * demo source did, and the client audience cannot reach an internal note. That
 * is the same shape as ClientPortal and ProjectUpdate::clientVisible: the
 * §6 rule is kept by not providing the call that could break it, rather than by
 * a check in a template that somebody eventually forgets.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class TicketDirectory
{
    public const AUDIENCE_STAFF = 'staff';

    public const AUDIENCE_CLIENT = 'client';

    /**
     * The list query, filtered.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Ticket>
     */
    public static function query(array $filters = []): Builder
    {
        $search = ($filters['search'] ?? '') !== '' ? $filters['search'] : null;

        return Ticket::query()
            ->with(['raiser.user', 'assignee.user', 'assignee.designation', 'escalator.user', 'client', 'project.client'])
            ->when($search, fn (Builder $q, string $term) => $q->where(function (Builder $q) use ($term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

                $q->where('subject', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhereHas('client', fn (Builder $c) => $c->where('name', 'like', $like));
            }))
            ->when($filters['status'] ?? null, fn (Builder $q, string $v) => $q->where('status', $v))
            ->when($filters['type'] ?? null, fn (Builder $q, string $v) => $q->where('type', $v))
            ->when($filters['priority'] ?? null, fn (Builder $q, string $v) => $q->where('priority', $v))
            /*
             * Most recently touched first. A support queue is read to find what
             * has moved, and `updated_at` is the only column that says so.
             */
            ->orderByDesc('updated_at');
    }

    /**
     * One ticket in the shape the views read.
     *
     * @return array<string, mixed>
     */
    public static function row(Ticket $ticket): array
    {
        return [
            'id' => $ticket->reference,
            'type' => $ticket->type,
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'raised_by' => $ticket->raiser?->user?->user_id,
            'client' => $ticket->client?->name,
            'client_reference' => $ticket->client?->reference,
            'project' => $ticket->project?->reference,
            'assignee' => $ticket->assignee?->user?->user_id,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'category' => $ticket->category,
            'department' => $ticket->department,
            'escalated_by' => $ticket->escalator?->user?->user_id,
            'created_at' => $ticket->created_at,
            'updated_at' => $ticket->updated_at,

            'raiser_record' => $ticket->raiser ? EmployeeDirectory::row($ticket->raiser) : null,
            'assignee_record' => $ticket->assignee ? EmployeeDirectory::row($ticket->assignee) : null,
            'escalator_record' => $ticket->escalator ? EmployeeDirectory::row($ticket->escalator) : null,
            'project_record' => $ticket->project ? ProjectDirectory::row($ticket->project) : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $reference): ?array
    {
        $ticket = Ticket::query()
            ->with(['raiser.user', 'assignee.user', 'assignee.designation', 'escalator.user', 'client', 'project.client'])
            ->where('reference', $reference)
            ->first();

        return $ticket === null ? null : self::row($ticket) + ['model' => $ticket];
    }

    /**
     * The thread, for one audience.
     *
     * The argument is required and there is no overload without it. A client
     * audience gets public replies only, and no call in this class can be made
     * to return an internal note to one.
     *
     * @return list<array<string, mixed>>
     */
    public static function commentsFor(Ticket $ticket, string $audience): array
    {
        $comments = $audience === self::AUDIENCE_CLIENT
            ? $ticket->publicComments()
            : $ticket->comments();

        return $comments->get()->map(fn (TicketComment $c) => $c->toRecordArray())->all();
    }

    /**
     * @param  Builder<Ticket>|null  $scope
     * @return array<string, int>
     */
    public static function stats(?Builder $scope = null): array
    {
        $base = fn () => clone ($scope ?? Ticket::query());

        return [
            'total' => $base()->count(),
            'unassigned' => $base()->unassigned()->count(),
            'escalated' => $base()->escalated()->count(),
            'in_progress' => $base()->where('status', 'in_progress')->count(),
            'resolved' => $base()->where('status', 'resolved')->count(),
            'open' => $base()->open()->count(),
        ];
    }

    /**
     * Tickets raised against the projects one person works on.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function onProjectsOf(Builder $query, ?Employee $employee): Builder
    {
        if ($employee === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas(
            'project',
            fn (Builder $p) => $p->forEmployee($employee)
        );
    }

    /**
     * The next reference — year-scoped, from the highest existing one.
     */
    public static function nextReference(): string
    {
        $prefix = 'TKT-'.Carbon::today()->year.'-';

        $highest = Ticket::query()
            ->where('reference', 'like', $prefix.'%')
            ->selectRaw('max(cast(substr(reference, ?) as integer)) as n', [strlen($prefix) + 1])
            ->value('n');

        return $prefix.str_pad((string) (((int) $highest) + 1), 3, '0', STR_PAD_LEFT);
    }
}
