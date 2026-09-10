<?php

namespace App\Http\Controllers\Client;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Support\Audit\AuditLog;
use App\Support\ClientPortal;
use App\Support\Notifier;
use App\Support\TicketDirectory;
use App\Support\TicketPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
 * The audience filter is not this controller's cleverness. TicketDirectory has no
 * `comments($ticket)` at all: `commentsFor` requires the audience as an
 * argument, so forgetting to pass one is a syntax error rather than a client
 * reading an internal note. This class reaches it through
 * ClientPortal::ticketComments, which applies ownership first and then
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

    public function __construct(protected AuditLog $audit, protected Notifier $notify)
    {
    }

    public function index(Request $request): Response
    {
        $client = $this->client($request);

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(TicketPresenter::statusOptions())],
            'priority' => ['nullable', Rule::in(TicketPresenter::priorityOptions())],
        ]);

        $tickets = ClientPortal::tickets($client);

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
            'stats' => ClientPortal::stats($client)['tickets'],
            'status' => $filters['status'] ?? null,
            'priority' => $filters['priority'] ?? null,
        ]);
    }

    public function show(Request $request, string $ticket): Response
    {
        $client = $this->client($request);

        $record = ClientPortal::ticket($client, $ticket);

        abort_if($record === null, 404);

        return response()->view('client.tickets.show', $this->shell($request, 'tickets') + [
            'ticket' => $record,
            // Client-audience comments only, pinned inside ClientPortal.
            'comments' => ClientPortal::ticketComments($client, $ticket),
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
            'projects' => ClientPortal::projects($client),
            'priorities' => TicketPresenter::priorityOptions(),
            // The client's own vocabulary, not the internal triage categories.
            'types' => TicketDirectory::AUDIENCE_CLIENT,
        ]);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE WRITES
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * POST /client/tickets
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THE CLIENT COMES FROM THE SESSION, AND THE FORM CANNOT SEND ONE
     *
     * There is no `client_id` in the validated set, so a posted one is dropped
     * before it can be read — not overwritten afterwards, which is the version
     * that breaks the day somebody reorders two lines. The same rule as the
     * check-in routes taking no employee.
     *
     * The project is validated against THIS CLIENT'S OWN. A select is a form
     * field and a form field is something anyone can type into, so filing a
     * ticket against somebody else's project has to be refused here rather than
     * prevented by the dropdown.
     *
     * IT ARRIVES UNASSIGNED AND UNPRIORITISED, like every other ticket. A
     * priority the person raising it sets makes every ticket high, which is the
     * same as none of them being — see the staff TicketController.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function store(Request $request): RedirectResponse
    {
        $client = $this->client($request);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:5000'],
            'project' => ['nullable', 'string', 'max:32'],
        ]);

        $project = ($data['project'] ?? null) === null
            ? null
            : ClientPortal::project($client, $data['project']);

        if (($data['project'] ?? null) !== null && $project === null) {
            // Not "that project does not exist" — the same message either way,
            // so the response cannot be used to find out which references are
            // real. Refused rather than silently filed against nothing.
            throw ValidationException::withMessages([
                'project' => 'Choose one of your own projects, or leave it blank.',
            ]);
        }

        $ticket = Ticket::create([
            'reference' => TicketDirectory::nextReference(),
            'type' => 'client',
            'subject' => $data['subject'],
            'description' => $data['description'],
            // The session. Never the form.
            'client_id' => $client->id,
            'project_id' => $project === null
                ? null
                : Project::where('reference', $project['id'])->value('id'),
            'raised_by' => null,
            'status' => 'unassigned',
        ]);

        $this->audit->record(
            action: AuditLog::TICKET_RAISED,
            actor: $request->user(),
            entityType: 'ticket',
            entityId: $ticket->reference,
            after: $client->name.' raised: '.$ticket->subject,
            request: $request,
        );

        return redirect()
            ->route('client.tickets.show', ['ticket' => $ticket->reference])
            ->with('status', 'Raised. Somebody will pick it up and you will see their reply here.')
            ->with('status_tone', 'success');
    }

    /**
     * POST /client/tickets/{ticket}/comment
     *
     * ─────────────────────────────────────────────────────────────────────────
     * A COMMENT FROM HERE IS PUBLIC BY CONSTRUCTION
     *
     * `visibility` is not in the validated set and is not read from the request
     * at all. The client wrote it; it cannot be an internal note, whatever the
     * payload says — and there is no branch here that could be made to say
     * otherwise, which is stronger than a check that forces the value.
     *
     * The author's name and role are copied onto the row, as they are on the
     * staff side, so a thread survives the removal of an account.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function comment(Request $request, string $ticket): RedirectResponse
    {
        $client = $this->client($request);

        $record = ClientPortal::ticket($client, $ticket);

        abort_if($record === null, 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        TicketComment::create([
            'ticket_id' => $record['model']->id,
            'author_id' => $request->user()->id,
            'author_label' => $client->name,
            'author_role' => 'Client',
            'body' => $data['body'],
            'visibility' => TicketComment::PUBLIC,
        ]);

        // The thread moving is what makes a ticket recently touched, and the
        // staff queue is ordered on it — a client's reply has to count.
        $record['model']->touch();

        $this->audit->record(
            action: AuditLog::TICKET_COMMENTED,
            actor: $request->user(),
            entityType: 'ticket',
            entityId: $record['model']->reference,
            after: $client->name.' replied on the ticket',
            request: $request,
        );

        $this->notify->ticketCommented(
            $record['model']->load(['assignee.user', 'raiser.user']),
            $record['model']->comments()->latest('id')->first(),
            // No actor: the client is not a staff account, so nobody is
            // excluded by the "nothing tells you what you just did" rule — and
            // the client does not have a bell to be excluded from.
            null,
        );

        return redirect()
            ->route('client.tickets.show', ['ticket' => $record['model']->reference])
            ->with('status', 'Reply posted.')
            ->with('status_tone', 'success');
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
