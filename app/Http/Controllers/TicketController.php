<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Support\Audit\AuditLog;
use App\Support\EmployeeDirectory;
use App\Support\Notifier;
use App\Support\Rbac\Rbac;
use App\Support\TicketDirectory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * Tickets — internal operations and client support.
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
 *    scopes every read to the signed-in client's own — a client opening
 *    somebody else's ticket is the worst failure this module can have.
 *
 * ESCALATION IS FLAT (decided 2026-08-27): one shared queue, no L1→L2→L3
 * ladder, and whoever holds `tickets.triage` works it.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class TicketController extends Controller
{
    protected const PER_PAGE = 8;

    public function __construct(
        protected Rbac $rbac,
        protected AuditLog $audit,
        protected Notifier $notify,
    ) {
    }

    /**
     * GET /tickets — every ticket, with a tab per queue.
     */
    public function index(Request $request): Response
    {
        $tab = $request->validate([
            'tab' => ['nullable', Rule::in(['all', 'unassigned', 'escalated', 'client', 'in_progress', 'resolved'])],
        ])['tab'] ?? 'all';

        $filters = $this->filters($request);

        $query = TicketDirectory::query($filters);

        $query = match ($tab) {
            'unassigned' => $query->unassigned(),
            'escalated' => $query->escalated(),
            'client' => $query->where('type', 'client'),
            'in_progress' => $query->where('status', 'in_progress'),
            'resolved' => $query->where('status', 'resolved'),
            default => $query,
        };

        $counts = TicketDirectory::stats();

        return response()->view('tickets.index', [
            'activeNav' => 'tickets',
            'tickets' => $this->paginate($query),
            'stats' => $counts,
            'tab' => $tab,
            'tabCounts' => [
                'all' => $counts['total'],
                'unassigned' => $counts['unassigned'],
                'escalated' => $counts['escalated'],
                'client' => Ticket::where('type', 'client')->count(),
                'in_progress' => $counts['in_progress'],
                'resolved' => $counts['resolved'],
            ],
        ] + $filters + $this->options());
    }

    /**
     * GET /tickets/mine — tickets I raised.
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
     * GET /tickets/assigned — tickets assigned to me.
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
     * GET /tickets/projects — tickets raised against projects I work on.
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
     * GET /tickets/escalated — the review queue.
     */
    public function escalated(Request $request): Response
    {
        $filters = $this->filters($request);
        $query = TicketDirectory::query($filters)->escalated();

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
            // Files wait on the payslip store being extended to threads; the
            // route and the check exist, the ticket-side upload does not yet.
            'attachments' => [],
            // Triage is offered when the ticket needs it — unassigned, or
            // escalated back for someone else to route.
            'needsTriage' => in_array($record['status'], ['unassigned', 'escalated'], true),
            'mayTriage' => $this->rbac->can($request->user(), 'tickets.triage'),
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
        ]);

        $ticket = Ticket::create([
            'reference' => TicketDirectory::nextReference(),
            'type' => $data['type'],
            'subject' => $data['subject'],
            'description' => $data['description'],
            'raised_by' => $data['type'] === 'internal' ? $employee?->id : null,
            'client_id' => $data['type'] === 'client' ? $data['client_id'] : null,
            'project_id' => $data['project_id'] ?? null,
            'status' => 'unassigned',
        ]);

        $this->audit->record(
            action: AuditLog::TICKET_RAISED,
            actor: $request->user(),
            entityType: 'ticket',
            entityId: $ticket->reference,
            after: $ticket->subject,
            request: $request,
        );

        return redirect()
            ->route('tickets.show', ['ticket' => $ticket->reference])
            ->with('status', 'Ticket raised. It is in the queue for triage.')
            ->with('status_tone', 'success');
    }

    /**
     * Reply on a ticket.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * PUBLIC BY DEFAULT, WHICH IS THE OPPOSITE OF A PROJECT UPDATE
     *
     * A ticket comment is a REPLY: the normal case is answering the person who
     * asked. A default that hid it would leave a client waiting for a response
     * that was written days ago — the failure here is silence, not disclosure,
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

        // Both staff readers, minus whoever wrote it. The note's text is never
        // carried — see Notifier::ticketCommented.
        $this->notify->ticketCommented($model->load(['assignee.user', 'raiser.user']), $comment, $request->user());

        return redirect()
            ->route('tickets.show', ['ticket' => $model->reference])
            ->with('status', $comment->isInternal() ? 'Internal note added.' : 'Reply posted.')
            ->with('status_tone', 'success');
    }

    /**
     * Triage: route it, prioritise it, and say who is picking it up.
     *
     * One act rather than four separate writes, because it is one decision —
     * somebody reads the ticket once and answers every question about it. Split
     * into four buttons it becomes four half-triaged tickets.
     */
    public function triage(Request $request, string $ticket): RedirectResponse
    {
        $record = $this->find($ticket);
        $model = $record['model'];

        $data = $request->validate([
            'assignee_id' => ['nullable', Rule::exists('employees', 'id')],
            'priority' => ['nullable', Rule::in(Ticket::PRIORITIES)],
            'category' => ['nullable', 'string', 'max:60'],
            'department' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', Rule::in(Ticket::STATUSES)],
        ]);

        $before = $this->describe($model);

        /*
         * Held separately from `$before`, which is a sentence for the audit log
         * and cannot be compared. Triage is one write covering four decisions,
         * so without this a correction to the category would notify the person
         * on the ticket again about work they have had for a week.
         */
        $assigneeBefore = $model->assignee_id;

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

        return redirect()
            ->route('tickets.show', ['ticket' => $model->reference])
            ->with('status', 'Ticket updated.')
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

        abort_if($found === null, 404);

        return $found;
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
        $query = $narrow(TicketDirectory::query($filters));

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
     * Options for the triage selects.
     *
     * Categories are the support team's own labels and nothing points at one,
     * so they are a config list rather than a table — see config/tickets.php.
     * Departments come from master data, because employees are already in them.
     *
     * @return array<string, mixed>
     */
    protected function options(): array
    {
        return [
            'categories' => (array) config('tickets.categories', []),
            'departments' => EmployeeDirectory::departmentsInUse(),
            'agents' => Employee::query()
                ->with('user')
                ->active()
                ->get()
                ->map(fn (Employee $e) => ['id' => $e->id, 'name' => (string) $e->user?->name])
                ->sortBy('name')
                ->values()
                ->all(),
        ];
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
