<?php

namespace App\Http\Controllers;

use App\Support\Demo\DemoEmployees;
use App\Support\Demo\DemoProjects;
use App\Support\Demo\DemoTickets;
use App\Support\TicketPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Tickets — internal operations and client support.
 *
 * Five lists: everything (`/tickets`), raised by me (`/tickets/mine`),
 * assigned to me (`/tickets/assigned`), on my projects
 * (`/tickets/projects`) and the review queue (`/tickets/escalated`). One
 * overview at `/tickets/{ticket}`, whose panels vary by the viewer's
 * relationship to the ticket rather than by being four separate templates.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FRONT END ONLY, and this module has two obligations the backend must honour:
 *
 * 1. INTERNAL NOTES. `DemoTickets::commentsFor()` takes the audience as a
 *    required argument. Every read here passes AUDIENCE_STAFF because this is
 *    the staff realm. When /client is built it passes AUDIENCE_CLIENT, and
 *    nothing else changes. Do not add a method that returns comments without
 *    an audience.
 *
 * 2. OWNERSHIP. Every client ticket carries a client. The client realm must
 *    scope every read to the signed-in client's own tickets and verify
 *    ownership on the detail route (§6) — a client opening
 *    /client/tickets/TKT-2026-151 and seeing another company's ticket is the
 *    worst failure this module can have.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class TicketController extends Controller
{
    protected const PER_PAGE = 8;

    /**
     * GET /tickets — every ticket, with a tab per queue.
     */
    public function index(Request $request): Response
    {
        $tab = $request->validate([
            'tab' => ['nullable', Rule::in(['all', 'unassigned', 'escalated', 'client', 'in_progress', 'resolved'])],
        ])['tab'] ?? 'all';

        $tickets = match ($tab) {
            'unassigned' => DemoTickets::unassigned(),
            'escalated' => DemoTickets::escalated(),
            'client' => DemoTickets::all()->where('type', 'client')->values(),
            'in_progress' => DemoTickets::all()->where('status', 'in_progress')->values(),
            'resolved' => DemoTickets::all()->where('status', 'resolved')->values(),
            default => DemoTickets::all(),
        };

        $counts = DemoTickets::stats();

        return response()->view('tickets.index', [
            'activeNav' => 'tickets',
            'tickets' => $this->paginate($this->matching($tickets, $this->filters($request)), $request),
            'stats' => $counts,
            'tab' => $tab,
            'tabCounts' => [
                'all' => $counts['total'],
                'unassigned' => $counts['unassigned'],
                'escalated' => $counts['escalated'],
                'client' => DemoTickets::all()->where('type', 'client')->count(),
                'in_progress' => $counts['in_progress'],
                'resolved' => $counts['resolved'],
            ],
        ] + $this->filters($request) + $this->options());
    }

    /**
     * GET /tickets/mine — tickets I raised.
     */
    public function mine(Request $request): Response
    {
        return $this->personalList($request, DemoTickets::raisedBy(), 'tickets.mine');
    }

    /**
     * GET /tickets/assigned — tickets assigned to me.
     */
    public function assigned(Request $request): Response
    {
        return $this->personalList($request, DemoTickets::assignedTo(), 'tickets.assigned');
    }

    /**
     * GET /tickets/projects — tickets raised against projects I work on.
     */
    public function projects(Request $request): Response
    {
        return $this->personalList($request, DemoTickets::onMyProjects(), 'tickets.projects');
    }

    /**
     * GET /tickets/escalated — the review queue.
     *
     * Escalation is flat: one shared queue rather than an L1→L2→L3 ladder
     * (decided 2026-08-27). Whoever holds the triage permission works it.
     */
    public function escalated(Request $request): Response
    {
        $queue = DemoTickets::escalated();

        return response()->view('tickets.escalated', [
            'activeNav' => 'tickets',
            'tickets' => $this->paginate($this->matching($queue, $this->filters($request)), $request),
            'stats' => DemoTickets::stats($queue),
        ] + $this->filters($request) + $this->options());
    }

    /**
     * GET /tickets/{ticket}
     */
    public function show(Request $request, string $ticket): Response
    {
        $record = DemoTickets::find($ticket);

        abort_if($record === null, 404);

        $decorated = $this->decorate($record);

        return response()->view('tickets.show', [
            'activeNav' => 'tickets',
            'ticket' => $decorated,
            // Staff realm, so the whole thread. The client realm will pass
            // AUDIENCE_CLIENT and get only public replies.
            'comments' => DemoTickets::commentsFor($record, DemoTickets::AUDIENCE_STAFF),
            'attachments' => DemoTickets::attachments($record),
            // Triage is offered when the ticket needs it — unassigned, or
            // escalated back for someone else to route.
            'needsTriage' => in_array($record['status'], ['unassigned', 'escalated'], true),
        ] + $this->options());
    }

    /**
     * The four personal lists differ only in which collection they show.
     *
     * @param  Collection<int, array<string, mixed>>  $tickets
     */
    protected function personalList(Request $request, Collection $tickets, string $view): Response
    {
        return response()->view($view, [
            'activeNav' => 'tickets',
            'tickets' => $this->paginate($this->matching($tickets, $this->filters($request)), $request),
            'stats' => DemoTickets::stats($tickets),
        ] + $this->filters($request) + $this->options());
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(TicketPresenter::statusOptions())],
            'type' => ['nullable', Rule::in(TicketPresenter::typeOptions())],
            'priority' => ['nullable', Rule::in(TicketPresenter::priorityOptions())],
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
     * @param  Collection<int, array<string, mixed>>  $tickets
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function matching(Collection $tickets, array $filters): Collection
    {
        return $tickets
            ->when($filters['search'] !== '', fn (Collection $rows) => $rows->filter(
                fn (array $t) => str_contains(
                    mb_strtolower($t['subject'].' '.$t['id'].' '.($t['client'] ?? '')),
                    mb_strtolower($filters['search'])
                )
            ))
            ->when($filters['status'], fn (Collection $rows) => $rows->where('status', $filters['status']))
            ->when($filters['type'], fn (Collection $rows) => $rows->where('type', $filters['type']))
            ->when($filters['priority'], fn (Collection $rows) => $rows->where('priority', $filters['priority']))
            ->map(fn (array $t) => $this->decorate($t))
            ->values();
    }

    /**
     * Attach the people and project a ticket refers to.
     *
     * @param  array<string, mixed>  $ticket
     * @return array<string, mixed>
     */
    protected function decorate(array $ticket): array
    {
        $employees = DemoEmployees::all()->keyBy('user_id');

        return $ticket + [
            'raiser_record' => $ticket['raised_by'] ? $employees->get($ticket['raised_by']) : null,
            'assignee_record' => $ticket['assignee'] ? $employees->get($ticket['assignee']) : null,
            'escalator_record' => $ticket['escalated_by'] ? $employees->get($ticket['escalated_by']) : null,
            'project_record' => $ticket['project'] ? DemoProjects::find($ticket['project']) : null,
        ];
    }

    /**
     * Options for the triage selects. Categories and departments are master
     * data (§8); hard-coded here only until that module exists.
     *
     * @return array<string, mixed>
     */
    protected function options(): array
    {
        return [
            'categories' => ['Access', 'Billing', 'Bug', 'Network', 'Performance', 'Reporting', 'Feature request'],
            'departments' => DemoEmployees::all()->pluck('department')->unique()->sort()->values()->all(),
            'agents' => DemoEmployees::all()
                ->map(fn (array $e) => ['id' => $e['user_id'], 'name' => $e['name']])
                ->sortBy('name')
                ->values()
                ->all(),
        ];
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
