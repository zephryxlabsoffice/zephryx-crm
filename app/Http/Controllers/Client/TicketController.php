<?php

namespace App\Http\Controllers\Client;

use App\Models\Client;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Support\Audit\AuditLog;
use App\Support\ClientPortal;
use App\Support\Diagnostics;
use App\Support\Documents\DocumentStore;
use App\Support\Notifier;
use App\Support\TicketDirectory;
use App\Support\TicketLifecycle;
use App\Support\TicketPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The client's support tickets.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TWO FILTERS, AND NEITHER IS OPTIONAL
 *
 * Ownership decides whether there is a ticket to open at all. Audience decides
 * which comments on it exist. They are separate questions and both have to be
 * answered, because an internal note on a client's OWN ticket is still not
 * theirs to read - "spoke to Rahul, this is going to slip a week, don't tell
 * them yet" is written on the ticket the client raised.
 *
 * The audience filter is not this controller's cleverness. TicketDirectory has no
 * `comments($ticket)` at all: `commentsFor` requires the audience as an
 * argument, so forgetting to pass one is a syntax error rather than a client
 * reading an internal note. This class reaches it through
 * ClientPortal::ticketComments, which applies ownership first and then
 * pins the audience to AUDIENCE_CLIENT - a caller here cannot ask for the
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

    protected const MAX_FILES_ON_RAISE = 5;

    public function __construct(
        protected AuditLog $audit,
        protected Notifier $notify,
        protected DocumentStore $documents,
    ) {}

    public function index(Request $request): Response
    {
        $client = $this->requireActiveClient($request);

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
        $client = $this->requireActiveClient($request);

        $record = ClientPortal::ticket($client, $ticket);

        abort_if($record === null, 404);

        return response()->view('client.tickets.show', $this->shell($request, 'tickets') + [
            'ticket' => $record,
            // Client-audience comments only, pinned inside ClientPortal.
            'comments' => ClientPortal::ticketComments($client, $ticket),
            // Only what this client uploaded. Staff files may be internal.
            'files' => $this->clientFiles($client, $record['model'])
                ->map(fn (TicketAttachment $a) => ['id' => $a->id, 'name' => $a->document_name])
                ->all(),
        ]);
    }

    /**
     * GET /client/tickets/raise
     */
    public function create(Request $request): Response
    {
        $client = $this->requireActiveClient($request);

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
     * before it can be read - not overwritten afterwards, which is the version
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
     * same as none of them being - see the staff TicketController.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function store(Request $request): RedirectResponse
    {
        $client = $this->requireActiveClient($request);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:5000'],
            'project' => ['nullable', 'string', 'max:32'],
            'issue_type' => ['nullable', Rule::in(['general', 'technical'])],
            'documents' => ['nullable', 'array', 'max:'.self::MAX_FILES_ON_RAISE],
            'documents.*' => [
                'file',
                'mimes:'.implode(',', DocumentStore::ALLOWED),
                'max:'.(int) (DocumentStore::MAX_BYTES / 1024),
            ],
        ], [
            'documents.max' => 'Attach up to '.self::MAX_FILES_ON_RAISE.' files.',
            'documents.*.mimes' => 'Attach a PDF, PNG or JPG.',
            'documents.*.max' => 'Each file can be up to '.(DocumentStore::MAX_BYTES / 1024 / 1024).' MB.',
        ]);

        /*
         * The client already knows it is technical, so it skips triage and goes
         * straight to whoever holds the technical designation (owner, 2026-11-03).
         */
        $technicalAgent = ($data['issue_type'] ?? null) === 'technical'
            ? TicketDirectory::technicalAgent()
            : null;

        $project = ($data['project'] ?? null) === null
            ? null
            : ClientPortal::project($client, $data['project']);

        if (($data['project'] ?? null) !== null && $project === null) {
            // Not "that project does not exist" - the same message either way,
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
            'status' => $technicalAgent === null ? 'unassigned' : 'open',
            'assignee_id' => $technicalAgent?->id,
            'department' => $technicalAgent?->department?->name,
        ]);

        $this->audit->record(
            action: AuditLog::TICKET_RAISED,
            actor: $request->user(),
            entityType: 'ticket',
            entityId: $ticket->reference,
            after: $client->name.' raised: '.$ticket->subject
                .($technicalAgent === null ? '' : ' (technical, sent to '.$technicalAgent->user?->name.')'),
            request: $request,
        );

        $notStored = $this->attachOnRaise($request, $client->name, $ticket, $data['documents'] ?? []);

        // Support and the project manager first - see Notifier::ticketRaised.
        // No actor: the client is not a staff account and has no bell to be
        // excluded from, the same reasoning as the comment route below.
        $this->notify->ticketRaised($ticket->load(['project.manager.user', 'assignee.user']), null);

        // What went wrong for them lately, for whoever picks it up (staff only).
        Diagnostics::attachTo($ticket, $request->user());

        return redirect()
            ->route('client.tickets.show', ['ticket' => $ticket->reference])
            ->with('status', ($technicalAgent === null
                ? 'Raised. Somebody will pick it up and you will see their reply here.'
                : 'Raised and sent to our technical team. You will see their reply here.')
                .($notStored === [] ? '' : ' These files could not be added: '.implode(', ', $notStored).'. Please send them as a reply.'))
            ->with('status_tone', $notStored === [] ? 'success' : 'warning');
    }

    /**
     * POST /client/tickets/{ticket}/comment
     *
     * ─────────────────────────────────────────────────────────────────────────
     * A COMMENT FROM HERE IS PUBLIC BY CONSTRUCTION
     *
     * `visibility` is not in the validated set and is not read from the request
     * at all. The client wrote it; it cannot be an internal note, whatever the
     * payload says - and there is no branch here that could be made to say
     * otherwise, which is stronger than a check that forces the value.
     *
     * The author's name and role are copied onto the row, as they are on the
     * staff side, so a thread survives the removal of an account.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function comment(Request $request, string $ticket): RedirectResponse
    {
        $client = $this->requireActiveClient($request);

        $record = ClientPortal::ticket($client, $ticket);

        abort_if($record === null, 404);

        // "Closed is final" (decided 2026-09-21) - same rule as the staff
        // side: a closed ticket takes no further reply, from either side.
        if ($record['model']->status === 'closed') {
            return redirect()
                ->route('client.tickets.show', ['ticket' => $record['model']->reference])
                ->with('status', 'This ticket is closed. Raise a new one and reference it if this continues.')
                ->with('status_tone', 'info');
        }

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
        // staff queue is ordered on it - a client's reply has to count.
        $record['model']->touch();

        // Answering a resolved ticket means it was not solved after all.
        if (TicketLifecycle::reopenIfResolved($record['model'], $request->user())) {
            TicketLifecycle::tellRequester($record['model'], TicketLifecycle::REOPENED);
        }

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
            // excluded by the "nothing tells you what you just did" rule - and
            // the client does not have a bell to be excluded from.
            null,
        );

        return redirect()
            ->route('client.tickets.show', ['ticket' => $record['model']->reference])
            ->with('status', 'Reply posted.')
            ->with('status_tone', 'success');
    }

    /**
     * GET /client/tickets/{ticket}/attachments/{attachment}/download - only a file
     * this client uploaded, on this client's own ticket. Anything else is a 404.
     */
    public function downloadAttachment(Request $request, string $ticket, int $attachment): StreamedResponse
    {
        $client = $this->requireActiveClient($request);
        $record = ClientPortal::ticket($client, $ticket);

        abort_if($record === null, 404);

        $file = $this->clientFiles($client, $record['model'])->firstWhere('id', $attachment);

        abort_if($file === null, 404);

        return $this->documents->download($file->document_path, $file->document_name);
    }

    /**
     * @return Collection<int, TicketAttachment>
     */
    protected function clientFiles(Client $client, Ticket $ticket): Collection
    {
        return TicketAttachment::query()
            ->where('ticket_id', $ticket->id)
            ->whereIn('uploaded_by', $client->accounts()->select('id'))
            ->orderBy('id')
            ->get();
    }

    /**
     * The files chosen on the raise form. The ticket is already saved, so a file the
     * store refuses is named back rather than losing the ticket.
     *
     * @param  list<UploadedFile>  $files
     * @return list<string>
     */
    protected function attachOnRaise(Request $request, string $clientName, Ticket $ticket, array $files): array
    {
        $notStored = [];

        foreach ($files as $file) {
            try {
                $stored = $this->documents->put('tickets/'.$ticket->reference, $file);
            } catch (Throwable $e) {
                report($e);
                $notStored[] = $file->getClientOriginalName();

                continue;
            }

            TicketAttachment::create([
                'ticket_id' => $ticket->id,
                'uploaded_by' => $request->user()->id,
                'uploaded_by_label' => $clientName,
                'document_path' => $stored['path'],
                'document_name' => $stored['name'],
                'document_bytes' => $stored['bytes'],
            ]);

            $this->audit->record(
                action: AuditLog::TICKET_ATTACHMENT_ADDED,
                actor: $request->user(),
                entityType: 'ticket',
                entityId: $ticket->reference,
                after: $stored['name'].' attached by '.$clientName,
                request: $request,
            );
        }

        return $notStored;
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
