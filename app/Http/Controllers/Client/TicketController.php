<?php

namespace App\Http\Controllers\Client;

use App\Support\Demo\DemoClientPortal;
use App\Support\Demo\DemoTickets;
use App\Support\TicketPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * The client's support tickets.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TWO FILTERS, AND NEITHER IS OPTIONAL
 *
 * Ownership decides whether there is a ticket to open at all. Audience decides
 * which comments on it exist. They are separate questions and both have to be
 * answered, because an internal note on a client's OWN ticket is still not
 * theirs to read — "spoke to Rahul, this is going to slip a week, don't tell
 * them yet" is written on the ticket the client raised.
 *
 * The audience filter is not this controller's cleverness. DemoTickets has no
 * `comments($ticket)` at all: `commentsFor` requires the audience as an
 * argument, so forgetting to pass one is a syntax error rather than a client
 * reading an internal note. This class reaches it through
 * DemoClientPortal::ticketComments, which applies ownership first and then
 * pins the audience to AUDIENCE_CLIENT — a caller here cannot ask for the
 * staff thread even by mistake.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Tickets a client can see are the ones the CLIENT raised. An internal ticket
 * against the same project belongs to us, and usually says why something is
 * late in words nobody wrote for them.
 */
class TicketController extends PortalController
{
    protected const PER_PAGE = 10;

    public function index(Request $request): Response
    {
        $client = $this->client($request);

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(TicketPresenter::statusOptions())],
            'priority' => ['nullable', Rule::in(TicketPresenter::priorityOptions())],
        ]);

        $tickets = DemoClientPortal::tickets($client);

        $matching = $tickets
            ->when(
                $filters['status'] ?? null,
                fn (Collection $rows, string $status) => $rows->where('status', $status)
            )
            ->when(
                $filters['priority'] ?? null,
                fn (Collection $rows, string $priority) => $rows->where('priority', $priority)
            )
            ->values();

        return response()->view('client.tickets.index', $this->shell($request, 'tickets') + [
            'tickets' => $this->paginate($matching, $request),
            'stats' => DemoClientPortal::stats($client)['tickets'],
            'status' => $filters['status'] ?? null,
            'priority' => $filters['priority'] ?? null,
        ]);
    }

    public function show(Request $request, string $ticket): Response
    {
        $client = $this->client($request);

        $record = DemoClientPortal::ticket($client, $ticket);

        abort_if($record === null, 404);

        return response()->view('client.tickets.show', $this->shell($request, 'tickets') + [
            'ticket' => $record,
            // Client-audience comments only, pinned inside DemoClientPortal.
            'comments' => DemoClientPortal::ticketComments($client, $ticket),
        ]);
    }

    /**
     * GET /client/tickets/raise
     */
    public function create(Request $request): Response
    {
        $client = $this->client($request);

        return response()->view('client.tickets.create', $this->shell($request, 'tickets') + [
            /*
             * Only their own projects in the dropdown, and the store route must
             * re-check it rather than trusting the posted value: a select is a
             * form field, and a form field is something anyone can type into.
             */
            'projects' => DemoClientPortal::projects($client),
            'priorities' => TicketPresenter::priorityOptions(),
            // The client's own vocabulary, not the internal triage categories.
            'types' => DemoTickets::AUDIENCE_CLIENT,
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function paginate(Collection $rows, Request $request): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            items: $rows->forPage($page, self::PER_PAGE)->values(),
            total: $rows->count(),
            perPage: self::PER_PAGE,
            currentPage: $page,
            options: ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
