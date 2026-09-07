<?php

namespace App\Support\Demo;

use App\Support\InvoicePresenter;
use App\Support\MeetingPresenter;
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
 * controller written in a hurry that calls `DemoInvoices::find($id)`, renders
 * it, and looks completely correct — because it IS completely correct, except
 * for the ownership check nobody remembered to add. The record comes back; the
 * page renders; a client reads another client's invoice.
 *
 * So the client controllers do not have access to a method that could do that.
 * Every read below takes `$client` as its FIRST argument, and the ones that
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
 * The same argument, in a smaller shape, is why DemoTickets::commentsFor takes
 * a required `$audience` and has no `comments($ticket)` beside it.
 * ═════════════════════════════════════════════════════════════════════════════
 *
 * Local + debug only, like every other Demo class, so a deployed portal renders
 * its empty states rather than invented projects and rupee figures.
 */
class DemoClientPortal
{
    public static function enabled(): bool
    {
        return app()->environment('local') && config('app.debug');
    }

    /**
     * The signed-in client.
     *
     * A client account is an ORGANISATION, not a person — several people at the
     * client may use it — so the identity is the company name, which is also
     * how projects, invoices, tickets and meetings reference them.
     *
     * TODO (backend phase): the client id on the session, never a name. Names
     * are not identifiers: two clients can share one, and renaming a company
     * must not orphan its invoices.
     */
    public static function viewer(): string
    {
        return 'DGL International School';
    }

    /**
     * The clients a reviewer may look at the portal as.
     *
     * Development only, and for one specific purpose: ownership rules are
     * invisible when there is only ever one client on screen. Switching between
     * two is how you notice that a page is showing somebody else's invoice.
     *
     * @return list<string>
     */
    public static function switchable(): array
    {
        if (! self::enabled()) {
            return [];
        }

        return DemoClients::all()->pluck('name')->all();
    }

    public static function knows(?string $client): bool
    {
        return $client !== null && in_array($client, self::switchable(), true);
    }

    /**
     * The client's own record.
     *
     * @return array<string, mixed>|null
     */
    public static function profile(string $client): ?array
    {
        return DemoClients::all()->firstWhere('name', $client);
    }

    /* ═══════════════════════════════════════════════════════════════════════
       PROJECTS
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function projects(string $client): Collection
    {
        return DemoProjects::all()
            ->where('client', $client)
            ->sortBy('due_in')
            ->values();
    }

    /**
     * One project, or null when it is not this client's.
     *
     * Note the shape: the ownership filter runs BEFORE the lookup, so a project
     * belonging to somebody else is not fetched and then rejected — it is never
     * a candidate. The controller turns null into a 404, which is the same
     * answer as for an id that does not exist, so the response cannot be used
     * to find out which project references are real.
     *
     * @return array<string, mixed>|null
     */
    public static function project(string $client, string $id): ?array
    {
        return self::projects($client)->firstWhere('id', $id);
    }

    /**
     * The daily updates on a project that this client may read.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function updates(string $client, string $project): Collection
    {
        // Ownership first, again: no project, no updates — not even if the
        // update ids were guessed.
        if (self::project($client, $project) === null) {
            return collect();
        }

        return DemoProjectUpdates::clientVisibleFor($project);
    }

    /* ═══════════════════════════════════════════════════════════════════════
       INVOICES
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function invoices(string $client): Collection
    {
        return DemoInvoices::forClient($client)
            /*
             * A draft invoice is not the client's to see. It is a document
             * somebody here is still writing, with figures that may change and
             * a number that is not committed to the sequence yet. It becomes
             * theirs when it is sent.
             */
            ->reject(fn (array $i) => InvoicePresenter::statusOf($i) === InvoicePresenter::DRAFT)
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function invoice(string $client, string $id): ?array
    {
        return self::invoices($client)->firstWhere('id', $id);
    }

    /* ═══════════════════════════════════════════════════════════════════════
       TICKETS
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * Tickets this client raised.
     *
     * Not "tickets about this client's projects" — an internal ticket raised by
     * an engineer against the same project is ours, and often says why
     * something is late in words nobody wrote for a client to read.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function tickets(string $client): Collection
    {
        return DemoTickets::all()
            ->where('client', $client)
            ->sortByDesc('updated_at')
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function ticket(string $client, string $id): ?array
    {
        return self::tickets($client)->firstWhere('id', $id);
    }

    /**
     * The thread on one of this client's tickets, as the client sees it.
     *
     * Two filters, and both are required. Ownership decides whether there is a
     * ticket at all; audience decides which comments on it exist. An internal
     * note on a client's own ticket is still not theirs to read.
     *
     * @return list<array<string, mixed>>
     */
    public static function ticketComments(string $client, string $id): array
    {
        $ticket = self::ticket($client, $id);

        if ($ticket === null) {
            return [];
        }

        return DemoTickets::commentsFor($ticket, DemoTickets::AUDIENCE_CLIENT);
    }

    /* ═══════════════════════════════════════════════════════════════════════
       MEETINGS
       ═══════════════════════════════════════════════════════════════════════ */

    /**
     * Meetings this client is actually on.
     *
     * Meetings carry a `clients` map keyed by name, so membership is the test —
     * a meeting about their project that they were not invited to is an
     * internal one, and listing it would tell them a conversation happened.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function meetings(string $client): Collection
    {
        return DemoMeetings::all()
            ->filter(fn (array $m) => array_key_exists($client, $m['clients'] ?? []))
            ->sortBy('starts_at')
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function meeting(string $client, string $id): ?array
    {
        return self::meetings($client)->firstWhere('id', $id);
    }

    /**
     * The next meeting this client can still attend.
     *
     * @return array<string, mixed>|null
     */
    public static function nextMeeting(string $client): ?array
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
    public static function stats(string $client): array
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
            'invoices' => DemoInvoices::stats($invoices),
            'tickets' => DemoTickets::stats($tickets),
            'meetings' => DemoMeetings::stats($meetings),
        ];
    }
}
