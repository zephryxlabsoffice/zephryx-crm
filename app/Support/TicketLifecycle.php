<?php

namespace App\Support;

use App\Mail\TicketUpdateMail;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Audit\AuditLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * What happens to a ticket after it is resolved, and what the person who asked is
 * told (owner, 2026-11-06).
 *
 * A resolved ticket closes by itself after `tickets.auto_close_after_days` days.
 * If the person who raised it replies before then, it reopens. The requester is
 * emailed when somebody replies publicly, and when the ticket is resolved, closed
 * or reopened. Never about an internal note, and never the note's text.
 */
class TicketLifecycle
{
    public const REPLY = 'reply';

    public const RESOLVED = 'resolved';

    public const CLOSED = 'closed';

    public const REOPENED = 'reopened';

    /**
     * Run nightly by `backup:database`. Returns how many were closed.
     */
    public static function closeStaleResolved(): int
    {
        $days = (int) config('tickets.auto_close_after_days', 7);

        $tickets = Ticket::query()
            ->where('status', 'resolved')
            ->where('resolved_at', '<=', now()->subDays($days))
            ->get();

        foreach ($tickets as $ticket) {
            $ticket->update(['status' => 'closed']);

            app(AuditLog::class)->record(
                action: AuditLog::TICKET_CLOSED,
                actorLabel: 'System',
                entityType: 'ticket',
                entityId: $ticket->reference,
                after: 'Closed automatically, '.$days.' days after it was resolved',
            );

            self::tellRequester($ticket, self::CLOSED);
        }

        return $tickets->count();
    }

    public static function closeStaleResolvedQuietly(): void
    {
        try {
            self::closeStaleResolved();
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * The person who asked replied to a resolved ticket: it was not solved after all.
     */
    public static function reopenIfResolved(Ticket $ticket, ?User $actor): bool
    {
        if ($ticket->status !== 'resolved') {
            return false;
        }

        $ticket->update([
            'status' => $ticket->assignee_id === null && $ticket->team_id === null ? 'unassigned' : 'open',
            'resolved_at' => null,
        ] + (TicketWorkflow::ready() ? ['resolved_by' => null] : []));

        app(AuditLog::class)->record(
            action: AuditLog::TICKET_REOPENED,
            actor: $actor,
            entityType: 'ticket',
            entityId: $ticket->reference,
            after: 'Reopened by a reply from the person who raised it',
        );

        return true;
    }

    /**
     * Email whoever raised the ticket. A client ticket goes to the client's portal
     * accounts, or their contact address if they have none. Mail trouble never
     * undoes the write that caused it.
     */
    public static function tellRequester(Ticket $ticket, string $event, ?User $actor = null): void
    {
        $ticket->loadMissing(['client', 'raiser.user']);

        $isClient = $ticket->type === 'client';
        $addresses = self::requesterAddresses($ticket)
            ->reject(fn (string $address) => $actor !== null && strcasecmp($address, (string) $actor->email) === 0)
            ->values();

        if ($addresses->isEmpty()) {
            return;
        }

        [$headline, $message] = match ($event) {
            self::REPLY => ['New reply on your ticket', 'There is a new reply on your ticket. Open it to read it and answer.'],
            self::RESOLVED => ['Your ticket is resolved', 'We have marked your ticket resolved. If it is not, reply within '
                .config('tickets.auto_close_after_days', 7).' days and it will reopen.'],
            self::CLOSED => ['Your ticket is closed', 'Your ticket has been closed. If you need more help, raise a new ticket.'],
            default => ['Your ticket is open again', 'Your ticket has been reopened and is back with our team.'],
        };

        $url = $isClient
            ? route('client.tickets.show', ['ticket' => $ticket->reference])
            : route('tickets.show', ['ticket' => $ticket->reference]);

        foreach ($addresses as $address) {
            try {
                Mail::to($address)->send(new TicketUpdateMail(
                    reference: $ticket->reference,
                    ticketSubject: $ticket->subject,
                    headline: $headline,
                    message: $message,
                    url: $url,
                ));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * @return Collection<int, string>
     */
    protected static function requesterAddresses(Ticket $ticket): Collection
    {
        $addresses = self::accountAddresses($ticket);

        // A ticket that came by email from somebody with no account here.
        return $addresses->isEmpty() && filled($ticket->requester_email)
            ? collect([$ticket->requester_email])
            : $addresses;
    }

    /**
     * @return Collection<int, string>
     */
    protected static function accountAddresses(Ticket $ticket): Collection
    {
        if ($ticket->type !== 'client') {
            return collect([$ticket->raiser?->user?->status === 'active' ? $ticket->raiser->user->email : null])
                ->filter()
                ->values();
        }

        if ($ticket->client === null) {
            return collect();
        }

        $accounts = $ticket->client->accounts()
            ->where('status', 'active')
            ->pluck('email')
            ->filter()
            ->values();

        return $accounts->isNotEmpty()
            ? $accounts->unique()->values()
            : collect([$ticket->client->contact_email])->filter()->values();
    }
}
