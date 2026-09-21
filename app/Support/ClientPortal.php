<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Everything the client portal is allowed to read, and nothing else.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THERE IS NO all(). THAT IS THE ENTIRE POINT OF THIS CLASS.
 *
 * Foundation spec §6 names the failure this exists to prevent, and names it as
 * the single most common real-world leak in applications of this shape:
 *
 *     "A client requesting invoice 47 must be verified as the owner of invoice
 *      47. This is enforced at the query layer, not in the view."
 *
 * The usual way that rule gets broken is not malice or ignorance. It is a
 * controller written in a hurry that calls `InvoiceDirectory::find($id)`,
 * renders it, and looks completely correct — because it IS completely correct,
 * except for the ownership check nobody remembered to add. The record comes
 * back; the page renders; a client reads another client's invoice.
 *
 * So the client controllers do not have access to a method that could do that.
 * Every read below takes a `Client` as its FIRST argument, and the ones that
 * fetch a single record return null when it belongs to somebody else — which
 * the controllers turn into a 404, identical to the response for a record that
 * does not exist. Forgetting the check is not possible here, because there is
 * no call that omits it.
 *
 * Two rules for anyone extending this class:
 *
 *   1. NEVER add a method that does not take `$client`. If a page seems to need
 *      one, the page is wrong.
 *   2. NEVER return a record without filtering on ownership first, even when
 *      the caller "obviously" already checked. The next caller will not have.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * IT TAKES THE RECORD, NOT THE NAME — AND THAT IS THE CHANGE
 *
 * The demo version was keyed on the company NAME, because the fixture had
 * nothing else. A name is not an identifier: two companies can share one, and
 * renaming a company would have detached every invoice from its own portal.
 *
 * `Client` is the argument now, the filters are foreign keys, and the name is
 * back to being only a heading.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class ClientPortal
{
    /* ═══════════════════════════════════════════════════════════════════════
       PROJECTS
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function projects(Client $client): Collection
    {
        return Project::query()
            ->with(['client', 'manager.user', 'manager.designation'])
            ->where('client_id', $client->id)
            ->orderBy('deadline')
            ->get()
            ->map(fn (Project $p) => ProjectDirectory::row($p));
    }

    /**
     * One project, or null when it is not this client's.
     *
     * Note the shape: the ownership filter is part of the query, so a project
     * belonging to somebody else is not fetched and then rejected — it is never
     * a candidate. The controller turns null into a 404, the same answer as for
     * an id that does not exist, so the response cannot be used to find out
     * which project references are real.
     *
     * @return array<string, mixed>|null
     */
    public static function project(Client $client, string $reference): ?array
    {
        $project = Project::query()
            ->with(['client', 'manager.user', 'manager.designation'])
            ->where('client_id', $client->id)
            ->where('reference', $reference)
            ->first();

        return $project === null ? null : ProjectDirectory::row($project);
    }

    /**
     * The daily updates on a project that this client may read.
     *
     * Two filters, and both are required. Ownership decides whether there is a
     * project at all; visibility decides which updates on it exist. An internal
     * note on a client's own project is still not theirs to read.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function updates(Client $client, string $projectReference): Collection
    {
        $project = Project::query()
            ->where('client_id', $client->id)
            ->where('reference', $projectReference)
            ->first();

        if ($project === null) {
            // Ownership first, again: no project, no updates — not even if the
            // update ids were guessed.
            return collect();
        }

        return $project->updates()
            ->clientVisible()
            ->with(['author.user', 'author.designation'])
            ->orderByDesc('posted_at')
            ->get()
            ->map(fn (ProjectUpdate $u) => $u->toRecordArray());
    }

    /* ═══════════════════════════════════════════════════════════════════════
       INVOICES
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function invoices(Client $client): Collection
    {
        return Invoice::query()
            ->with(['client', 'project', 'payments'])
            ->where('client_id', $client->id)
            /*
             * A draft invoice is not the client's to see. It is a document
             * somebody here is still writing, with figures that may change and
             * a number not committed to the sequence yet. It becomes theirs
             * when it is sent — which is a column, so this is a query and not a
             * filter somebody could forget to apply.
             */
            ->whereNotNull('sent_at')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Invoice $i) => $i->toRecordArray());
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function invoice(Client $client, string $number): ?array
    {
        $invoice = Invoice::query()
            ->with(['client', 'project', 'payments'])
            ->where('client_id', $client->id)
            ->whereNotNull('sent_at')
            ->where('number', $number)
            ->first();

        return $invoice === null ? null : $invoice->toRecordArray() + ['model' => $invoice];
    }

    /* ═══════════════════════════════════════════════════════════════════════
       TICKETS
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * Tickets raised against this client.
     *
     * Not "tickets about this client's projects" — an internal ticket raised by
     * an engineer against the same project is ours, and often says why
     * something is late in words nobody wrote for a client to read. The filter
     * is `client_id`, which internal tickets do not carry.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function tickets(Client $client): Collection
    {
        return self::ticketQuery($client)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Ticket $t) => TicketDirectory::row($t));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function ticket(Client $client, string $reference): ?array
    {
        $ticket = self::ticketQuery($client)->where('reference', $reference)->first();

        return $ticket === null ? null : TicketDirectory::row($ticket) + ['model' => $ticket];
    }

    /**
     * The thread on one of this client's tickets, as the client sees it.
     *
     * Ownership decides whether there is a ticket; audience decides which
     * comments on it exist. An internal note on a client's own ticket is still
     * not theirs to read, and `commentsFor` takes the audience as a required
     * argument for exactly that reason.
     *
     * @return list<array<string, mixed>>
     */
    public static function ticketComments(Client $client, string $reference): array
    {
        $ticket = self::ticketQuery($client)->where('reference', $reference)->first();

        if ($ticket === null) {
            return [];
        }

        return TicketDirectory::commentsFor($ticket, TicketDirectory::AUDIENCE_CLIENT);
    }

    /**
     * @return Builder<Ticket>
     */
    protected static function ticketQuery(Client $client)
    {
        return Ticket::query()
            ->with(['client', 'project', 'assignee.user', 'raiser.user'])
            ->where('client_id', $client->id);
    }

    /* ═══════════════════════════════════════════════════════════════════════
       MEETINGS
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * Meetings this client is actually on.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * TWO WAYS TO BE ON ONE, AND BOTH ARE OWNERSHIP
     *
     * A client is on a meeting either because they requested it — the record
     * carries `requested_by_client_id` — or because it is about one of their
     * projects. Neither alone is enough: a request that has not been scheduled
     * yet has no project, and a scheduled meeting about their project is theirs
     * to see even though a client account is never an attendee row (attendees
     * are staff users).
     *
     * What is deliberately NOT here is every meeting mentioning their name. An
     * internal meeting about a client is an internal meeting.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function meetings(Client $client): Collection
    {
        return Meeting::query()
            ->with(['project.client', 'organiser.user', 'attendees.user'])
            ->where(fn ($q) => $q
                ->where('requested_by_client_id', $client->id)
                ->orWhereHas('project', fn ($p) => $p->where('client_id', $client->id)))
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Meeting $m) => $m->toRecordArray(null));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function meeting(Client $client, string $reference): ?array
    {
        return self::meetings($client)->firstWhere('id', $reference);
    }

    /**
     * The next meeting this client can still attend.
     *
     * @return array<string, mixed>|null
     */
    public static function nextMeeting(Client $client): ?array
    {
        return self::meetings($client)
            ->first(fn (array $m) => MeetingPresenter::statusOf($m) === MeetingPresenter::SCHEDULED);
    }

    /* ═══════════════════════════════════════════════════════════════════════
       SUMMARY
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * The figures behind the portal's tiles, every one of them scoped.
     *
     * Computed from the same collections the pages list, so a tile and the
     * table under it cannot disagree — the designer's handover had five invoice
     * tiles whose totals contradicted the rows beneath them.
     *
     * @return array<string, mixed>
     */
    public static function stats(Client $client): array
    {
        $projects = self::projects($client);
        $invoices = self::invoices($client);
        $tickets = self::tickets($client);
        $meetings = self::meetings($client);

        return [
            'projects' => [
                'active' => $projects->whereIn('status', ['in_progress', 'planning', 'review'])->count(),
                'completed' => $projects->where('status', 'completed')->count(),
                'review' => $projects->where('status', 'review')->count(),
                'total' => $projects->count(),
            ],
            'invoices' => InvoiceDirectory::stats($invoices),
            /*
             * A scoped QUERY, not the collection above. TicketDirectory::stats
             * counts in SQL, and handing it an unscoped builder is exactly the
             * mistake this class exists to make impossible — so the scope goes
             * through `ticketQuery`, which cannot be built without a client.
             */
            'tickets' => TicketDirectory::stats(self::ticketQuery($client)),
            'meetings' => MeetingDirectory::stats($meetings),
        ];
    }
}
