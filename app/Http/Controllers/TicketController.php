<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Employee;
use App\Models\MasterDataItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Support\Audit\AuditLog;
use App\Support\Documents\DocumentStore;
use App\Support\Notifier;
use App\Support\Rbac\Rbac;
use App\Support\TaskDirectory;
use App\Support\TaskPresenter;
use App\Support\TicketDirectory;
use App\Support\TicketLifecycle;
use App\Support\TicketWorkflow;
use App\Support\WebPush;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Tickets - internal operations and client support.
 *
 * Five lists: everything (`/tickets`), raised by me (`/tickets/mine`),
 * assigned to me (`/tickets/assigned`), on my projects (`/tickets/projects`)
 * and the review queue (`/tickets/escalated`). One overview at
 * `/tickets/{ticket}`, whose panels vary by the viewer's relationship to the
 * ticket rather than by being four separate templates.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TWO OBLIGATIONS, AND THEY ARE KEPT BY THE SHAPE OF THE CALLS
 *
 * 1. INTERNAL NOTES. `TicketDirectory::commentsFor()` takes the audience as a
 *    required argument. Every read here passes AUDIENCE_STAFF because this is
 *    the staff realm; the client portal passes AUDIENCE_CLIENT and gets public
 *    replies only. There is no method that returns a thread without an
 *    audience, and adding one would be the bug.
 *
 * 2. OWNERSHIP. Every client ticket carries a client, and the client realm
 *    scopes every read to the signed-in client's own - a client opening
 *    somebody else's ticket is the worst failure this module can have.
 *
 * ESCALATION IS FLAT (decided 2026-08-27): one shared queue, no L1→L2→L3
 * ladder, and whoever holds `tickets.triage` works it.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class TicketController extends Controller
{
    protected const PER_PAGE = 8;

    protected const MAX_FILES_ON_RAISE = 5;

    public function __construct(
        protected Rbac $rbac,
        protected AuditLog $audit,
        protected Notifier $notify,
        protected DocumentStore $documents,
        protected WebPush $push,
    ) {}

    /**
     * GET /tickets - every ticket, with a tab per queue.
     */
    public function index(Request $request): Response
    {
        $tab = $request->validate([
            'tab' => ['nullable', Rule::in(['all', 'unassigned', 'escalated', 'client', 'in_progress', 'resolved'])],
        ])['tab'] ?? 'all';

        $filters = $this->filters($request);

        $query = TicketWorkflow::visibleTo(TicketDirectory::query($filters), $request->user());

        $query = match ($tab) {
            'unassigned' => $query->unassigned(),
            'escalated' => $query->escalated(),
            'client' => $query->where('type', 'client'),
            'in_progress' => $query->where('status', 'in_progress'),
            'resolved' => $query->where('status', 'resolved'),
            default => $query,
        };

        $visible = fn () => TicketWorkflow::visibleTo(Ticket::query(), $request->user());
        $counts = TicketDirectory::stats($visible());

        return response()->view('tickets.index', [
            'activeNav' => 'tickets',
            'tickets' => $this->paginate($query),
            'stats' => $counts,
            'tab' => $tab,
            'tabCounts' => [
                'all' => $counts['total'],
                'unassigned' => $counts['unassigned'],
                'escalated' => $counts['escalated'],
                'client' => $visible()->where('type', 'client')->count(),
                'in_progress' => $counts['in_progress'],
                'resolved' => $counts['resolved'],
            ],
        ] + $filters + $this->options());
    }

    /**
     * GET /tickets/mine - tickets I raised.
     */
    public function mine(Request $request): Response
    {
        $employee = $this->employeeFor($request);

        return $this->personalList(
            $request,
            fn (Builder $q) => $q->where('raised_by', $employee?->id ?? 0),
            'tickets.mine',
        );
    }

    /**
     * GET /tickets/assigned - tickets assigned to me.
     */
    public function assigned(Request $request): Response
    {
        $employee = $this->employeeFor($request);

        return $this->personalList(
            $request,
            fn (Builder $q) => $q->where('assignee_id', $employee?->id ?? 0),
            'tickets.assigned',
        );
    }

    /**
     * GET /tickets/projects - tickets raised against projects I work on.
     */
    public function projects(Request $request): Response
    {
        $employee = $this->employeeFor($request);

        return $this->personalList(
            $request,
            fn (Builder $q) => TicketDirectory::onProjectsOf($q, $employee),
            'tickets.projects',
        );
    }

    /**
     * GET /tickets/escalated - the review queue.
     */
    public function escalated(Request $request): Response
    {
        $filters = $this->filters($request);
        $query = TicketWorkflow::visibleTo(TicketDirectory::query($filters), $request->user())->escalated();

        return response()->view('tickets.escalated', [
            'activeNav' => 'tickets',
            'tickets' => $this->paginate(clone $query),
            'stats' => TicketDirectory::stats(clone $query),
        ] + $filters + $this->options());
    }

    /**
     * GET /tickets/{ticket}
     */
    public function show(Request $request, string $ticket): Response
    {
        $record = $this->find($ticket);

        return response()->view('tickets.show', [
            'activeNav' => 'tickets',
            'ticket' => $record,
            // Staff realm, so the whole thread. The client realm passes
            // AUDIENCE_CLIENT and gets only public replies.
            'comments' => TicketDirectory::commentsFor($record['model'], TicketDirectory::AUDIENCE_STAFF),
            'attachments' => $record['model']->attachments->map(fn (TicketAttachment $a) => [
                'id' => $a->id,
                'name' => $a->document_name,
                'kind' => TaskPresenter::fileKind($a->document_name),
                'size' => TaskPresenter::fileSize($a->document_bytes),
            ])->all(),
            // Triage is offered when the ticket needs it - unassigned, or
            // escalated back for someone else to route.
            // Nobody is assigned yet. An escalated ticket has somebody on it now.
            'needsTriage' => $record['model']->assignee_id === null && $record['status'] !== 'closed',
            'mayTriage' => TicketWorkflow::mayTriage($record['model'], $request->user()),
            'mayTake' => TicketWorkflow::mayTake($record['model'], $request->user()),
            'mayWorkOn' => $mayWorkOn = TicketWorkflow::mayWorkOn($record['model'], $request->user()),
            'isOwn' => TicketWorkflow::isOwn($record['model'], $viewer = $this->employeeFor($request)),
            'escalationTargets' => $mayWorkOn && $viewer !== null
                ? TicketWorkflow::escalationTargets($record['model'], $viewer)
                    ->map(fn (Employee $e) => ['id' => $e->id, 'name' => (string) $e->user?->name])->all()
                : [],
            'suggestedEscalation' => $mayWorkOn && $viewer !== null
                ? TicketWorkflow::suggestedEscalation($record['model'], $viewer)?->id
                : null,
            'mayConvertToTask' => $this->rbac->can($request->user(), 'tasks.create')
                && TicketWorkflow::mayDecide($record['model'], $request->user()),
            'agents' => TicketWorkflow::assignableFor($record['model'])
                ->map(fn (Employee $e) => ['id' => $e->id, 'name' => (string) $e->user?->name])
                ->all(),
        ] + $this->options());
    }

    public function create(Request $request): Response
    {
        return response()->view('tickets.form', [
            'activeNav' => 'tickets',
            'reference' => TicketDirectory::nextReference(),
            'projects' => Project::query()->open()->orderBy('name')->get(),
            'clients' => Client::query()->whereNot('status', 'inactive')->orderBy('name')->get(),
            'mayTriage' => $this->rbac->can($request->user(), 'tickets.triage'),
        ] + $this->options());
    }

    /**
     * Raise a ticket.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * IT ARRIVES UNASSIGNED AND UNPRIORITISED, AND THAT IS NOT AN OMISSION
     *
     * A priority nobody set is different from a low one, and the queue sorts on
     * the difference: an untriaged ticket is one nobody has looked at, not one
     * judged unimportant. Letting the person raising it set the priority makes
     * every ticket high, which is the same as none of them being.
     * ─────────────────────────────────────────────────────────────────────────
     * "A NEW TICKET CAN REFERENCE THE PREVIOUS ONE, AND CLOSES IT"
     *
     * `supersedes` is optional and takes a ticket REFERENCE, the same as
     * every other cross-record pointer a form here takes. Closing the old
     * ticket is a side effect of THIS write, not a second visit to
     * `triage()` - "closed is final" refuses moving a closed ticket any
     * further, and this is the one place that legitimately moves a ticket
     * TO closed from wherever it was, including already-closed (a no-op).
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function store(Request $request): RedirectResponse
    {
        $employee = $this->employeeFor($request);

        $data = $request->validate([
            'type' => ['required', Rule::in(Ticket::TYPES)],
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:5000'],
            'project_id' => ['nullable', Rule::exists('projects', 'id')],
            'client_id' => ['nullable', 'required_if:type,client', Rule::exists('clients', 'id')],
            'supersedes' => ['nullable', 'string', 'max:32', Rule::exists('tickets', 'reference')],
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

        $supersedes = ($data['supersedes'] ?? null) !== null
            ? Ticket::where('reference', $data['supersedes'])->first()
            : null;

        $ticket = Ticket::create([
            'reference' => TicketDirectory::nextReference(),
            'type' => $data['type'],
            'subject' => $data['subject'],
            'description' => $data['description'],
            'raised_by' => $data['type'] === 'internal' ? $employee?->id : null,
            'client_id' => $data['type'] === 'client' ? $data['client_id'] : null,
            'project_id' => $data['project_id'] ?? null,
            'status' => 'unassigned',
            'supersedes_ticket_id' => $supersedes?->id,
        ]);

        $this->audit->record(
            action: AuditLog::TICKET_RAISED,
            actor: $request->user(),
            entityType: 'ticket',
            entityId: $ticket->reference,
            after: $ticket->subject,
            request: $request,
        );

        $notStored = $this->attachOnRaise($request, $ticket, $data['documents'] ?? []);

        // Support and the project manager first - see Notifier::ticketRaised.
        // A no-op for an internal ticket.
        $this->notify->ticketRaised($ticket->load('project.manager.user'), $request->user());

        if ($supersedes !== null && $supersedes->status !== 'closed') {
            $supersedes->update([
                'status' => 'closed',
                'resolved_at' => $supersedes->resolved_at ?? now(),
            ]);

            $this->audit->record(
                action: AuditLog::TICKET_CLOSED,
                actor: $request->user(),
                entityType: 'ticket',
                entityId: $supersedes->reference,
                after: 'Closed - replaced by '.$ticket->reference,
                request: $request,
            );
        }

        return redirect()
            ->route('tickets.show', ['ticket' => $ticket->reference])
            ->with('status', $notStored === []
                ? 'Ticket raised. It is in the queue for triage.'
                : 'Ticket raised, but these files could not be stored: '.implode(', ', $notStored).'. Add them from the ticket page.')
            ->with('status_tone', $notStored === [] ? 'success' : 'warning');
    }

    /**
     * The files chosen on the raise form. The ticket is already saved, so a file the
     * store refuses is named back to the person rather than losing the ticket.
     *
     * @param  list<UploadedFile>  $files
     * @return list<string> the names that could not be stored
     */
    protected function attachOnRaise(Request $request, Ticket $ticket, array $files): array
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
                'uploaded_by_label' => $request->user()->name,
                'document_path' => $stored['path'],
                'document_name' => $stored['name'],
                'document_bytes' => $stored['bytes'],
            ]);

            $this->audit->record(
                action: AuditLog::TICKET_ATTACHMENT_ADDED,
                actor: $request->user(),
                entityType: 'ticket',
                entityId: $ticket->reference,
                after: $stored['name'].' attached',
                request: $request,
            );
        }

        return $notStored;
    }

    /**
     * Reply on a ticket.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * PUBLIC BY DEFAULT, WHICH IS THE OPPOSITE OF A PROJECT UPDATE
     *
     * A ticket comment is a REPLY: the normal case is answering the person who
     * asked. A default that hid it would leave a client waiting for a response
     * that was written days ago - the failure here is silence, not disclosure,
     * and the internal note is the exception somebody chooses.
     *
     * The author's name and role are copied onto the row, so a thread survives
     * the removal of an account.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function comment(Request $request, string $ticket): RedirectResponse
    {
        $record = $this->find($ticket);
        $model = $record['model'];

        // "Closed is final" (decided 2026-09-21): the same rule as triage -
        // nothing writes to a closed ticket, including a reply. A new ticket
        // referencing it is the only way the conversation continues.
        if ($model->status === 'closed') {
            return redirect()
                ->route('tickets.show', ['ticket' => $model->reference])
                ->with('status', 'This ticket is closed. Closed tickets are final - raise a new one and reference this if it continues.')
                ->with('status_tone', 'info');
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'visibility' => ['nullable', Rule::in([TicketComment::PUBLIC, TicketComment::INTERNAL])],
        ]);

        $comment = TicketComment::create([
            'ticket_id' => $model->id,
            'author_id' => $request->user()->id,
            'author_label' => $request->user()->name,
            'author_role' => $request->user()->display_role,
            'body' => $data['body'],
            'visibility' => $data['visibility'] ?? TicketComment::PUBLIC,
        ]);

        // The thread moving is what makes a ticket "recently touched", and the
        // queue is ordered on it.
        $model->touch();

        $this->audit->record(
            action: AuditLog::TICKET_COMMENTED,
            actor: $request->user(),
            entityType: 'ticket',
            entityId: $model->reference,
            after: $comment->isInternal() ? 'Internal note added' : 'Replied on the ticket',
            request: $request,
        );

        // The person who asked answering a resolved ticket: it was not solved.
        if (TicketWorkflow::isOwn($model, $this->employeeFor($request))
            && TicketLifecycle::reopenIfResolved($model, $request->user())) {
            TicketLifecycle::tellRequester($model, TicketLifecycle::REOPENED, $request->user());
        } elseif (! $comment->isInternal()) {
            TicketLifecycle::tellRequester($model, TicketLifecycle::REPLY, $request->user());
        }

        // Both staff readers, minus whoever wrote it. The note's text is never
        // carried - see Notifier::ticketCommented.
        $this->notify->ticketCommented($model->load(['assignee.user', 'raiser.user']), $comment, $request->user());

        return redirect()
            ->route('tickets.show', ['ticket' => $model->reference])
            ->with('status', $comment->isInternal() ? 'Internal note added.' : 'Reply posted.')
            ->with('status_tone', 'success');
    }

    /**
     * Attach a file to a ticket.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * STAFF-ONLY FOR NOW
     *
     * "Attachments allowed" (decided 2026-09-21) does not say who uploads
     * them. A client ticket's thread already carries files the far side
     * might send by email or describe over a call - this is where staff
     * attach them. Add-only, no delete route, matching every other module
     * this round - see TaskController::storeAttachment for the identical
     * reasoning.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function storeAttachment(Request $request, string $ticket): RedirectResponse
    {
        $record = $this->find($ticket);
        $model = $record['model'];

        $data = $request->validate([
            'document' => [
                'required', 'file',
                'mimes:'.implode(',', DocumentStore::ALLOWED),
                'max:'.(int) (DocumentStore::MAX_BYTES / 1024),
            ],
        ]);

        try {
            $stored = $this->documents->put('tickets/'.$model->reference, $data['document']);
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'document' => 'Could not store the file: '.$e->getMessage(),
            ]);
        }

        TicketAttachment::create([
            'ticket_id' => $model->id,
            'uploaded_by' => $request->user()->id,
            'uploaded_by_label' => $request->user()->name,
            'document_path' => $stored['path'],
            'document_name' => $stored['name'],
            'document_bytes' => $stored['bytes'],
        ]);

        $this->audit->record(
            action: AuditLog::TICKET_ATTACHMENT_ADDED,
            actor: $request->user(),
            entityType: 'ticket',
            entityId: $model->reference,
            after: $stored['name'].' attached',
            request: $request,
        );

        return redirect()
            ->route('tickets.show', ['ticket' => $model->reference])
            ->with('status', 'File attached.')
            ->with('status_tone', 'success');
    }

    /**
     * GET /tickets/{ticket}/attachments/{attachment}/view
     */
    public function viewAttachment(Request $request, string $ticket, int $attachment): StreamedResponse
    {
        $file = $this->attachmentFor($ticket, $attachment);

        return $this->documents->viewInline($file->document_path, $file->document_name);
    }

    /**
     * GET /tickets/{ticket}/attachments/{attachment}/download
     */
    public function downloadAttachment(Request $request, string $ticket, int $attachment): StreamedResponse
    {
        $file = $this->attachmentFor($ticket, $attachment);

        return $this->documents->download($file->document_path, $file->document_name);
    }

    /**
     * Triage: route it, prioritise it, and say who is picking it up.
     *
     * One act rather than four separate writes, because it is one decision -
     * somebody reads the ticket once and answers every question about it. Split
     * into four buttons it becomes four half-triaged tickets.
     */
    public function triage(Request $request, string $ticket): RedirectResponse
    {
        $record = $this->find($ticket);
        $model = $record['model'];

        /*
         * "Closed is final" (decided 2026-09-21). Not a status a ticket
         * moves out of by any route here - a new ticket referencing this one
         * is the only thing that reopens the conversation, and it does so as
         * ITS OWN ticket, never by un-closing this one.
         */
        if ($model->status === 'closed') {
            return redirect()
                ->route('tickets.show', ['ticket' => $model->reference])
                ->with('status', 'This ticket is closed. Closed tickets are final - raise a new one and reference this if it continues.')
                ->with('status_tone', 'info');
        }

        $data = $request->validate([
            // Only somebody the form offered: support, Technical Associates, and a
            // Manager on a ticket of their own. See TicketDirectory::assignableFor.
            'assignee_id' => ['nullable', Rule::in(TicketWorkflow::assignableFor($model)->pluck('id')->all())],
            'priority' => ['nullable', Rule::in(Ticket::PRIORITIES)],
            // Closed lists now (decided 2026-09-21) - both are master data,
            // and a category typed once as "Bug" and once as "bug" is a
            // filter that quietly misses half its rows.
            'category' => ['nullable', Rule::in($this->categoryNames())],
            'department' => ['nullable', Rule::in($this->departmentNames())],
            'status' => ['nullable', Rule::in(Ticket::STATUSES)],
        ], [
            'assignee_id.in' => 'Choose somebody from the list.',
        ]);

        abort_unless(TicketWorkflow::mayTriage($model, $request->user()), 403);

        $before = $this->describe($model);

        /*
         * Held separately from `$before`, which is a sentence for the audit log
         * and cannot be compared. Triage is one write covering four decisions,
         * so without this a correction to the category would notify the person
         * on the ticket again about work they have had for a week.
         */
        $assigneeBefore = $model->assignee_id;
        $statusBefore = $model->status;

        $status = $data['status'] ?? null;

        if ($status === null) {
            /*
             * No status chosen: assigning somebody opens the ticket, and
             * leaving it unassigned keeps it in the queue. Inferred rather than
             * asked, because "assign to Amit and leave it unassigned" is not a
             * state anybody means.
             */
            $status = ($data['assignee_id'] ?? null) !== null
                ? ($model->status === 'unassigned' ? 'open' : $model->status)
                : $model->status;
        }

        $model->update([
            'assignee_id' => $data['assignee_id'] ?? null,
            'priority' => $data['priority'] ?? $model->priority,
            'category' => $data['category'] ?? $model->category,
            'department' => $data['department'] ?? $model->department,
            'status' => $status,
            'resolved_at' => in_array($status, ['resolved', 'closed'], true)
                ? ($model->resolved_at ?? now())
                : $model->resolved_at,
            'escalated_by' => $status === 'escalated'
                ? ($this->employeeFor($request)?->id ?? $model->escalated_by)
                : $model->escalated_by,
            'escalated_at' => $status === 'escalated' ? ($model->escalated_at ?? now()) : $model->escalated_at,
        ]);

        $model->refresh()->load(['assignee.user', 'escalator.user']);

        $this->audit->record(
            action: AuditLog::TICKET_TRIAGED,
            actor: $request->user(),
            entityType: 'ticket',
            entityId: $model->reference,
            before: $before,
            after: $this->describe($model),
            request: $request,
        );

        $this->notify->ticketAssigned($model, $assigneeBefore, $request->user());

        if ($status !== ($statusBefore ?? null) && in_array($status, ['resolved', 'closed'], true)) {
            TicketLifecycle::tellRequester($model, $status === 'resolved' ? TicketLifecycle::RESOLVED : TicketLifecycle::CLOSED, $request->user());
        }

        return redirect()
            ->route('tickets.show', ['ticket' => $model->reference])
            ->with('status', 'Ticket updated.')
            ->with('status_tone', 'success');
    }

    /**
     * POST /tickets/{ticket}/take - a System Administrator, the CEO, or one of the
     * ticket's own Managers picks it up themselves (owner, 2026-11-06).
     */
    public function take(Request $request, string $ticket): RedirectResponse
    {
        $model = $this->find($ticket)['model'];

        abort_unless(TicketWorkflow::mayTake($model, $request->user()), 403);

        $before = $this->describe($model);
        $assigneeBefore = $model->assignee_id;

        $model->update([
            'assignee_id' => $this->employeeFor($request)->id,
            'status' => 'in_progress',
            'resolved_at' => null,
        ]);
        $model->refresh()->load(['assignee.user', 'escalator.user']);

        $this->audit->record(
            action: AuditLog::TICKET_TAKEN,
            actor: $request->user(),
            entityType: 'ticket',
            entityId: $model->reference,
            before: $before,
            after: $this->describe($model),
            request: $request,
        );

        if ($assigneeBefore !== null && $assigneeBefore !== $model->assignee_id) {
            $this->notify->ticketCommented($model->load('raiser.user'), $this->note($request, $model, $request->user()->name.' took this ticket.'), $request->user());
        }

        return redirect()
            ->route('tickets.show', ['ticket' => $model->reference])
            ->with('status', 'The ticket is yours now.')
            ->with('status_tone', 'success');
    }

    /**
     * POST /tickets/{ticket}/resolve - Support, or whoever has it.
     */
    public function resolve(Request $request, string $ticket): RedirectResponse
    {
        $model = $this->find($ticket)['model'];

        abort_unless(TicketWorkflow::mayWorkOn($model, $request->user()), 403);

        $before = $this->describe($model);

        $model->update(['status' => 'resolved', 'resolved_at' => now()]);
        $model->refresh()->load(['assignee.user', 'escalator.user']);

        $this->audit->record(
            action: AuditLog::TICKET_RESOLVED,
            actor: $request->user(),
            entityType: 'ticket',
            entityId: $model->reference,
            before: $before,
            after: $this->describe($model),
            request: $request,
        );

        TicketLifecycle::tellRequester($model, TicketLifecycle::RESOLVED, $request->user());

        return redirect()
            ->route('tickets.show', ['ticket' => $model->reference])
            ->with('status', 'Resolved. It closes by itself in '.config('tickets.auto_close_after_days', 7).' days unless they reply.')
            ->with('status_tone', 'success');
    }

    /**
     * POST /tickets/{ticket}/escalate - Support, or whoever has it, cannot solve it
     * and hands it on. The reason is kept as an internal note.
     */
    public function escalate(Request $request, string $ticket): RedirectResponse
    {
        $model = $this->find($ticket)['model'];
        $escalator = $this->employeeFor($request);

        abort_unless($escalator !== null && TicketWorkflow::mayWorkOn($model, $request->user()), 403);

        $data = $request->validate([
            'escalate_to' => ['required', Rule::in(TicketWorkflow::escalationTargets($model, $escalator)->pluck('id')->all())],
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'escalate_to.required' => 'Choose who it goes to.',
            'escalate_to.in' => 'Choose somebody from the list.',
            'reason.required' => 'Say why it needs escalating, so the next person does not start from nothing.',
        ]);

        $before = $this->describe($model);
        $assigneeBefore = $model->assignee_id;
        $target = Employee::with('user')->findOrFail((int) $data['escalate_to']);

        $model->update([
            'assignee_id' => $target->id,
            'status' => 'escalated',
            'escalated_by' => $escalator->id,
            'escalated_at' => now(),
        ]);
        $model->refresh()->load(['assignee.user', 'escalator.user']);

        $this->note($request, $model, 'Escalated to '.$target->user?->name.': '.$data['reason']);

        $this->audit->record(
            action: AuditLog::TICKET_ESCALATED,
            actor: $request->user(),
            entityType: 'ticket',
            entityId: $model->reference,
            before: $before,
            after: $this->describe($model),
            request: $request,
        );

        $this->notify->ticketAssigned($model, $assigneeBefore, $request->user());
        $this->push->sendTo([$target->user_id]);

        return redirect()
            ->route('tickets.show', ['ticket' => $model->reference])
            ->with('status', 'Escalated to '.$target->user?->name.'.')
            ->with('status_tone', 'success');
    }

    /**
     * An internal note written by the system on somebody's behalf.
     */
    protected function note(Request $request, Ticket $ticket, string $body): TicketComment
    {
        return TicketComment::create([
            'ticket_id' => $ticket->id,
            'author_id' => $request->user()->id,
            'author_label' => $request->user()->name,
            'author_role' => $request->user()->display_role,
            'body' => $body,
            'visibility' => TicketComment::INTERNAL,
        ]);
    }

    /**
     * Turn a ticket into a task.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * A LINK, NOT A LIFECYCLE DECISION
     *
     * This creates a task and points the ticket at it - nothing else. It does
     * not close the ticket, does not touch its status or its priority, and
     * does not decide what "closed is final" means for a converted one; those
     * are the Tickets module's own decisions, for the step that builds it.
     * What this method answers is only "may this become a task", which is
     * `tasks.create` - creating one from a ticket is still creating one.
     *
     * IDEMPOTENT, LIKE EmployeeController::convert
     *
     * A ticket already carrying `converted_task_id` refuses a second
     * conversion rather than quietly creating a duplicate task - the same
     * reason a double-submitted form must not issue somebody two staff ids.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function convertToTask(Request $request, string $ticket): RedirectResponse
    {
        $record = $this->find($ticket);
        $model = $record['model'];

        if ($model->converted_task_id !== null) {
            throw ValidationException::withMessages([
                'convert' => 'This ticket has already been converted to a task.',
            ]);
        }

        $data = $request->validate([
            'due_on' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $task = DB::transaction(function () use ($model, $data) {
            $task = Task::create([
                'reference' => TaskDirectory::nextReference(),
                'name' => $model->subject,
                'description' => $model->description,
                'project_id' => $model->project_id,
                'status' => 'pending',
                'priority' => $model->priority ?? 'medium',
                'due_on' => $data['due_on'],
            ]);

            $model->update(['converted_task_id' => $task->id]);

            return $task;
        });

        $this->audit->record(
            action: AuditLog::TICKET_CONVERTED_TO_TASK,
            actor: $request->user(),
            entityType: 'ticket',
            entityId: $model->reference,
            after: 'Converted to task '.$task->reference,
            request: $request,
        );

        $this->audit->record(
            action: AuditLog::TASK_CREATED_FROM_TICKET,
            actor: $request->user(),
            entityType: 'task',
            entityId: $task->reference,
            after: $task->name.' - created from ticket '.$model->reference,
            request: $request,
        );

        return redirect()
            ->route('tasks.show', ['task' => $task->reference])
            ->with('status', 'Ticket converted. Assign it like any other task.')
            ->with('status_tone', 'success');
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE PIECES
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * @return array<string, mixed>
     */
    protected function find(string $reference): array
    {
        $found = TicketDirectory::find($reference);

        // A ticket this person may not see answers the same as one that does not exist.
        abort_if($found === null || ! TicketWorkflow::canSee($found['model'], request()->user()), 404);

        return $found;
    }

    /**
     * A ticket's attachment, scoped to that ticket rather than fetched by id
     * alone - ownership in the query, the same rule every other read in this
     * application follows: an attachment id from a different ticket's page
     * must 404 rather than resolve to a file that is not this ticket's.
     */
    protected function attachmentFor(string $ticket, int $attachment): TicketAttachment
    {
        $record = $this->find($ticket);

        return TicketAttachment::where('ticket_id', $record['model']->id)
            ->where('id', $attachment)
            ->firstOrFail();
    }

    protected function employeeFor(Request $request): ?Employee
    {
        return Employee::where('user_id', $request->user()?->id)->first();
    }

    /**
     * The four personal lists differ only in how they narrow the query.
     *
     * @param  callable(Builder<Ticket>): Builder<Ticket>  $narrow
     */
    protected function personalList(Request $request, callable $narrow, string $view): Response
    {
        $filters = $this->filters($request);
        $query = $narrow(TicketWorkflow::visibleTo(TicketDirectory::query($filters), $request->user()));

        return response()->view($view, [
            'activeNav' => 'tickets',
            'tickets' => $this->paginate(clone $query),
            'stats' => TicketDirectory::stats(clone $query),
        ] + $filters + $this->options());
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(Ticket::STATUSES)],
            'type' => ['nullable', Rule::in(Ticket::TYPES)],
            'priority' => ['nullable', Rule::in(Ticket::PRIORITIES)],
        ]);

        $search = trim($validated['q'] ?? '');

        return [
            'search' => $search,
            'status' => $validated['status'] ?? null,
            'type' => $validated['type'] ?? null,
            'priority' => $validated['priority'] ?? null,
            'filtered' => $search !== ''
                || ($validated['status'] ?? null) !== null
                || ($validated['type'] ?? null) !== null
                || ($validated['priority'] ?? null) !== null,
        ];
    }

    /**
     * Options for the triage selects. Who a ticket can be given to depends on the
     * ticket, so `show()` adds that list itself.
     *
     * Both categories and departments are master data now (decided
     * 2026-09-21) - `MasterDataItem::TICKET_CATEGORIES` is its own list, and
     * departments reuse `MasterDataItem::DEPARTMENTS` directly (the full
     * active list a ticket might route to) rather than
     * `EmployeeDirectory::departmentsInUse()`, which only shows a department
     * once somebody is actually in it - not the question a ticket asks.
     *
     * @return array<string, mixed>
     */
    protected function options(): array
    {
        return [
            'categories' => $this->categoryNames(),
            'departments' => $this->departmentNames(),
        ];
    }

    /**
     * @return list<string>
     */
    protected function categoryNames(): array
    {
        return MasterDataItem::query()
            ->inList(MasterDataItem::TICKET_CATEGORIES)
            ->active()
            ->pluck('name')
            ->all();
    }

    /**
     * @return list<string>
     */
    protected function departmentNames(): array
    {
        return MasterDataItem::query()
            ->inList(MasterDataItem::DEPARTMENTS)
            ->active()
            ->pluck('name')
            ->all();
    }

    protected function describe(Ticket $ticket): string
    {
        return implode(' · ', array_filter([
            str_replace('_', ' ', $ticket->status),
            $ticket->priority ? $ticket->priority.' priority' : 'no priority',
            $ticket->category,
            $ticket->department,
            $ticket->assignee?->user?->name ? 'with '.$ticket->assignee->user->name : 'unassigned',
        ]));
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function paginate(Builder $query): LengthAwarePaginator
    {
        return $query->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Ticket $t) => TicketDirectory::row($t));
    }
}
