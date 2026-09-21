<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\Team;
use App\Support\Audit\AuditLog;
use App\Support\Documents\DocumentStore;
use App\Support\Notifier;
use App\Support\Rbac\Rbac;
use App\Support\TaskDirectory;
use App\Support\TaskPresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Tasks — the managing face (`/tasks`), the personal face (`/tasks/mine`), the
 * team lead's queue (`/tasks/team`) and one task (`/tasks/{task}`).
 * Foundation spec §12.1.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THREE DIFFERENT ANSWERS TO "MAY THEY?"
 *
 * Creating and editing a task are permissions — they are authority over the
 * plan. Completing one is not: it is the assignee saying they have finished
 * their own work, so it is an ownership check (§2.6), and the same check lets
 * the lead of the team holding it close a task nobody picked up.
 *
 * Assigning sits between them: `tasks.assign` opens the route, and a Team Lead
 * holding it may only assign within a team they actually lead — the same shape
 * as team membership, and for the same reason.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class TaskController extends Controller
{
    protected const PER_PAGE = 8;

    public function __construct(
        protected Rbac $rbac,
        protected AuditLog $audit,
        protected Notifier $notify,
        protected DocumentStore $documents,
    ) {
    }

    /**
     * GET /tasks
     */
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        return response()->view('tasks.index', [
            'activeNav' => 'tasks',
            'tasks' => $this->paginate(TaskDirectory::query($filters)),
            'stats' => TaskDirectory::stats(),
            'upcoming' => TaskDirectory::upcoming(),
            'activity' => TaskDirectory::activity(),
            'projects' => $this->projectOptions(),
            'teams' => $this->teamOptions(),
            'mayCreate' => $this->rbac->can($request->user(), 'tasks.create'),
        ] + $filters);
    }

    /**
     * GET /tasks/mine — the viewer's own queue.
     *
     * Scoped by the session, with no parameter to change to somebody else's.
     */
    public function mine(Request $request): Response
    {
        $filters = $this->filters($request);
        $employee = $this->employeeFor($request);

        $query = TaskDirectory::query($filters)
            ->whereHas('assignees', fn (Builder $q) => $q->where('employees.id', $employee?->id ?? 0));

        return response()->view('tasks.mine', [
            'activeNav' => 'tasks',
            'tasks' => $this->paginate(clone $query),
            'stats' => TaskDirectory::stats(clone $query),
            'projects' => $this->projectOptions(),
            'teams' => $this->teamOptions(),
        ] + $filters);
    }

    /**
     * GET /tasks/team — tasks held by a team rather than a person.
     *
     * This is the Team Lead's queue: the point of it is deciding who picks each
     * one up, so the "nobody assigned yet" state is the normal case here rather
     * than an exception.
     */
    public function team(Request $request): Response
    {
        $filters = $this->filters($request);

        $query = TaskDirectory::query($filters)->unassigned()->whereNotNull('team_id');

        return response()->view('tasks.team', [
            'activeNav' => 'tasks',
            'tasks' => $this->paginate(clone $query),
            'stats' => TaskDirectory::stats(clone $query),
            'projects' => $this->projectOptions(),
            'teams' => $this->teamOptions(),
        ] + $filters);
    }

    /**
     * GET /tasks/{task}
     */
    public function show(Request $request, string $task): Response
    {
        $record = $this->find($task);

        return response()->view('tasks.show', [
            'activeNav' => 'tasks',
            'task' => TaskDirectory::row($record),
            'record' => $record,
            'timeline' => TaskDirectory::timeline($record->reference),
            'comments' => $record->comments->map(fn (TaskComment $c) => $c->toRecordArray())->all(),
            'attachments' => $record->attachments->map(fn (TaskAttachment $a) => [
                'id' => $a->id,
                'name' => $a->document_name,
                'kind' => TaskPresenter::fileKind($a->document_name),
                'size' => TaskPresenter::fileSize($a->document_bytes),
            ])->all(),
            'mayEdit' => $this->rbac->can($request->user(), 'tasks.edit'),
            'mayAssign' => $this->canAssign($request, $record),
            'mayComplete' => $this->canComplete($request, $record),
            // Loaded only for somebody who may actually assign: a dropdown of
            // every employee is a staff list, and there is no reason to put one
            // in the markup for a reader who cannot use it.
            'employeeChoices' => $this->canAssign($request, $record)
                ? $this->formOptions()['employeeChoices']
                : collect(),
        ]);
    }

    public function create(Request $request): Response
    {
        return response()->view('tasks.form', [
            'activeNav' => 'tasks',
            'task' => null,
            'reference' => $this->nextReference(),
        ] + $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $assignees = $data['assignee_ids'] ?? [];
        unset($data['assignee_ids']);

        $task = DB::transaction(function () use ($data, $assignees) {
            $task = Task::create($data + ['reference' => $this->nextReference()]);
            $task->assignees()->sync($assignees);

            return $task;
        });

        $this->audit->record(
            action: AuditLog::TASK_CREATED,
            actor: $request->user(),
            entityType: 'task',
            entityId: $task->reference,
            after: $task->name.' was created',
            request: $request,
        );

        if ($assignees !== []) {
            // Its own entry as well, so "who put this person on it" is a
            // question the timeline answers without reading a payload.
            $this->recordAssignment($request, $task);
        }

        return redirect()
            ->route('tasks.show', ['task' => $task->reference])
            ->with('status', $task->name.' was created.')
            ->with('status_tone', 'success');
    }

    public function edit(Request $request, string $task): Response
    {
        $record = $this->find($task);

        return response()->view('tasks.form', [
            'activeNav' => 'tasks',
            'task' => $record,
            'reference' => $record->reference,
        ] + $this->formOptions());
    }

    public function update(Request $request, string $task): RedirectResponse
    {
        $record = $this->find($task);
        $data = $this->validated($request, $record);
        $assignees = $data['assignee_ids'] ?? [];
        unset($data['assignee_ids']);

        $before = $this->describe($record);
        $assigneesBefore = $record->assignees->pluck('id')->sort()->values()->all();

        DB::transaction(function () use ($record, $data, $assignees) {
            $record->update($data + [
                // Completing through the edit form still stamps the date, so the
                // two routes cannot leave the record in different shapes.
                'completed_at' => $data['status'] === 'completed'
                    ? ($record->completed_at ?? now())
                    : $record->completed_at,
            ]);

            $record->assignees()->sync($assignees);
        });

        $record->refresh()->load(['project', 'team', 'assignees.user']);

        $this->audit->record(
            action: AuditLog::TASK_UPDATED,
            actor: $request->user(),
            entityType: 'task',
            entityId: $record->reference,
            before: $before,
            after: $this->describe($record),
            request: $request,
        );

        $assigneesAfter = $record->assignees->pluck('id')->sort()->values()->all();

        if ($assigneesBefore !== $assigneesAfter) {
            $this->recordAssignment($request, $record, $assigneesBefore);
        }

        return redirect()
            ->route('tasks.show', ['task' => $record->reference])
            ->with('status', 'Task updated.')
            ->with('status_tone', 'success');
    }

    /**
     * Put somebody on a task, or take them off it.
     *
     * The Team Lead's act, on their own team's queue. Separate from editing
     * because it is the one thing a lead does daily and the rest of the form —
     * the project, the deadline, the priority — is the plan, which is not
     * theirs to change.
     */
    public function assign(Request $request, string $task): RedirectResponse
    {
        $record = $this->find($task);

        abort_unless($this->canAssign($request, $record), 403);

        $data = $request->validate([
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => [Rule::exists('employees', 'id')],
        ]);

        $ids = collect($data['assignee_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();

        $employees = Employee::with('user')->whereIn('id', $ids)->get();

        if ($employees->contains(fn (Employee $e) => ! $e->user?->isActive())) {
            throw ValidationException::withMessages([
                'assignee_ids' => 'One of those records is closed. They cannot be given work.',
            ]);
        }

        $before = $record->assignees->pluck('id')->sort()->values()->all();

        $record->assignees()->sync($ids);
        $record->refresh()->load('assignees.user');

        $this->recordAssignment($request, $record, $before);

        return redirect()
            ->route('tasks.show', ['task' => $record->reference])
            ->with('status', $record->assignees->isEmpty()
                ? 'Taken off '.($record->team?->name ?? 'the task').'.'
                : 'Assignees updated.')
            ->with('status_tone', 'success');
    }

    /**
     * Mark a task finished, or reopen it.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * `completed_at` IS NOT CLEARED BY REOPENING
     *
     * A task that was delivered and then reopened is a different thing from one
     * that was never finished, and a report asking how long work takes needs to
     * be able to tell them apart. The status moves; the fact stays.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function complete(Request $request, string $task): RedirectResponse
    {
        $record = $this->find($task);

        abort_unless($this->canComplete($request, $record), 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(['completed', 'in_progress'])],
        ]);

        $before = $record->status;

        if ($before === $data['status']) {
            return redirect()
                ->route('tasks.show', ['task' => $record->reference])
                ->with('status', 'Nothing changed.')
                ->with('status_tone', 'info');
        }

        $record->update([
            'status' => $data['status'],
            'completed_at' => $data['status'] === 'completed'
                ? ($record->completed_at ?? now())
                : $record->completed_at,
        ]);

        $this->audit->record(
            action: $data['status'] === 'completed' ? AuditLog::TASK_COMPLETED : AuditLog::TASK_REOPENED,
            actor: $request->user(),
            entityType: 'task',
            entityId: $record->reference,
            before: $before,
            after: $data['status'] === 'completed'
                ? $record->name.' was completed'
                : $record->name.' was reopened',
            request: $request,
        );

        return redirect()
            ->route('tasks.show', ['task' => $record->reference])
            ->with('status', $data['status'] === 'completed' ? 'Marked completed.' : 'Reopened.')
            ->with('status_tone', $data['status'] === 'completed' ? 'success' : 'info');
    }

    /**
     * Leave a note on a task.
     *
     * No visibility split, unlike a ticket comment — a task has one
     * readership, so there is nothing to choose. The author's name is copied
     * at write time, same as a ticket thread, so it survives the removal of
     * an account.
     */
    public function comment(Request $request, string $task): RedirectResponse
    {
        $record = $this->find($task);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        TaskComment::create([
            'task_id' => $record->id,
            'author_id' => $request->user()->id,
            'author_label' => $request->user()->name,
            'body' => $data['body'],
        ]);

        $this->audit->record(
            action: AuditLog::TASK_COMMENTED,
            actor: $request->user(),
            entityType: 'task',
            entityId: $record->reference,
            after: 'Comment added',
            request: $request,
        );

        return redirect()
            ->route('tasks.show', ['task' => $record->reference])
            ->with('status', 'Comment added.')
            ->with('status_tone', 'success');
    }

    /**
     * Attach a file to a task.
     *
     * Add-only, like every other write this module makes: there is no route
     * that removes one, matching the "no delete anywhere" rule the rest of
     * this module (and Clients, Teams, Projects) already follows. Always
     * Google Drive — DocumentStore::put() is the only method that ever writes
     * an uploaded file, and it never writes to the local disk.
     */
    public function storeAttachment(Request $request, string $task): RedirectResponse
    {
        $record = $this->find($task);

        $data = $request->validate([
            'document' => [
                'required', 'file',
                'mimes:'.implode(',', DocumentStore::ALLOWED),
                'max:'.(int) (DocumentStore::MAX_BYTES / 1024),
            ],
        ]);

        try {
            $stored = $this->documents->put('tasks/'.$record->reference, $data['document']);
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'document' => 'Could not store the file: '.$e->getMessage(),
            ]);
        }

        TaskAttachment::create([
            'task_id' => $record->id,
            'uploaded_by' => $request->user()->id,
            'uploaded_by_label' => $request->user()->name,
            'document_path' => $stored['path'],
            'document_name' => $stored['name'],
            'document_bytes' => $stored['bytes'],
        ]);

        $this->audit->record(
            action: AuditLog::TASK_ATTACHMENT_ADDED,
            actor: $request->user(),
            entityType: 'task',
            entityId: $record->reference,
            after: $stored['name'].' attached',
            request: $request,
        );

        return redirect()
            ->route('tasks.show', ['task' => $record->reference])
            ->with('status', 'File attached.')
            ->with('status_tone', 'success');
    }

    /**
     * GET /tasks/{task}/attachments/{attachment}/view
     */
    public function viewAttachment(Request $request, string $task, int $attachment): StreamedResponse
    {
        $file = $this->attachmentFor($task, $attachment);

        return $this->documents->viewInline($file->document_path, $file->document_name);
    }

    /**
     * GET /tasks/{task}/attachments/{attachment}/download
     */
    public function downloadAttachment(Request $request, string $task, int $attachment): StreamedResponse
    {
        $file = $this->attachmentFor($task, $attachment);

        return $this->documents->download($file->document_path, $file->document_name);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE PIECES
       ══════════════════════════════════════════════════════════════════════ */

    protected function find(string $reference): Task
    {
        return Task::query()
            ->with([
                'project.client', 'team.lead.user', 'assignees.user', 'assignees.designation',
                'comments', 'attachments',
            ])
            ->where('reference', $reference)
            ->firstOrFail();
    }

    /**
     * A task's attachment, scoped to that task rather than fetched by id
     * alone — the same reason every ownership check in this application runs
     * inside the query: an attachment id from a different task's page must
     * 404 rather than resolve to a file that is not this task's to show.
     */
    protected function attachmentFor(string $task, int $attachment): TaskAttachment
    {
        $record = $this->find($task);

        return TaskAttachment::where('task_id', $record->id)
            ->where('id', $attachment)
            ->firstOrFail();
    }

    protected function employeeFor(Request $request): ?Employee
    {
        return Employee::where('user_id', $request->user()?->id)->first();
    }

    /**
     * Whether this person may put somebody on THIS task.
     *
     * `tasks.edit` is the wide answer. Otherwise `tasks.assign` plus leading
     * the team that holds it — the key opens the route, Team::isLedBy decides
     * which queue (§2.6).
     */
    protected function canAssign(Request $request, Task $task): bool
    {
        if ($this->rbac->can($request->user(), 'tasks.edit')) {
            return true;
        }

        return $this->rbac->can($request->user(), 'tasks.assign')
            && $task->team?->isLedBy($this->employeeFor($request)) === true;
    }

    /**
     * Whether this person may finish or reopen THIS task.
     *
     * The assignee, their team's lead, or anybody who may edit tasks at all.
     * Not a permission of its own: finishing your own work is not an authority.
     */
    protected function canComplete(Request $request, Task $task): bool
    {
        return $this->rbac->can($request->user(), 'tasks.edit')
            || $task->isOwnedBy($this->employeeFor($request));
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(Task::STATUSES)],
            'priority' => ['nullable', Rule::in(Task::PRIORITIES)],
            'project' => ['nullable', 'string', 'max:32'],
            'team' => ['nullable', 'string', 'max:32'],
        ]);

        $search = trim($validated['q'] ?? '');

        return [
            'search' => $search,
            'status' => $validated['status'] ?? null,
            'priority' => $validated['priority'] ?? null,
            'project' => $validated['project'] ?? null,
            'team' => $validated['team'] ?? null,
            'filtered' => $search !== ''
                || ($validated['status'] ?? null) !== null
                || ($validated['priority'] ?? null) !== null
                || ($validated['project'] ?? null) !== null
                || ($validated['team'] ?? null) !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Task $existing = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'project_id' => ['nullable', Rule::exists('projects', 'id')],
            'team_id' => ['nullable', Rule::exists('teams', 'id')],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => [Rule::exists('employees', 'id')],
            'status' => ['required', Rule::in(Task::STATUSES)],
            'priority' => ['required', Rule::in(Task::PRIORITIES)],
            'due_on' => ['required', 'date'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            // Open projects only. Attaching new work to a finished project is a
            // mistake the dropdown should not invite.
            'projectChoices' => Project::query()->open()->orderBy('name')->get(),
            'teamChoices' => Team::query()->active()->orderBy('name')->get(),
            'employeeChoices' => Employee::query()->with('user')->active()->get()
                ->sortBy(fn (Employee $e) => $e->user?->name)->values(),
        ];
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    protected function projectOptions(): array
    {
        return Project::query()
            ->orderBy('name')
            ->get(['reference', 'name'])
            ->map(fn (Project $p) => ['id' => $p->reference, 'name' => $p->name])
            ->all();
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    protected function teamOptions(): array
    {
        return Team::query()
            ->orderBy('name')
            ->get(['reference', 'name'])
            ->map(fn (Team $t) => ['id' => $t->reference, 'name' => $t->name])
            ->all();
    }

    /**
     * The next task reference. Owned by TaskDirectory now — see the note
     * there — so TicketController::convertToTask mints from the same place.
     */
    protected function nextReference(): string
    {
        return TaskDirectory::nextReference();
    }

    /**
     * The one place an assignment is recorded, which is why the notification is
     * here too.
     *
     * All three routes that can move a task onto somebody — create, edit and
     * assign — come through this method, and each of them already knows to call
     * it only when the roster actually changed. Notifying from the call sites
     * instead would mean three chances to forget, and the one that forgot would
     * be silent.
     *
     * `$beforeIds` is the roster before the write, so only the people newly
     * added are notified — a sync that adds one person to a task two others
     * are already on must not re-notify the two who were already there.
     *
     * @param  list<int>  $beforeIds
     */
    protected function recordAssignment(Request $request, Task $task, array $beforeIds = []): void
    {
        $task->loadMissing('assignees.user');

        $names = $task->assignees->pluck('user.name')->filter()->implode(', ');

        $this->audit->record(
            action: AuditLog::TASK_ASSIGNED,
            actor: $request->user(),
            entityType: 'task',
            entityId: $task->reference,
            after: $names !== ''
                ? $task->name.' assigned to '.$names
                : $task->name.' has nobody on it',
            request: $request,
        );

        // Unassigning is audited but notifies nobody: there is no reader, and
        // the person losing the task finds out by looking at their own list.
        $newlyAssigned = $task->assignees
            ->whereNotIn('id', $beforeIds)
            ->pluck('user')
            ->filter();

        $this->notify->taskAssigned($task, $newlyAssigned, $request->user());
    }

    protected function describe(Task $task): string
    {
        $names = $task->assignees->pluck('user.name')->filter()->implode(', ');

        return implode(' · ', array_filter([
            $task->name,
            $task->project?->name,
            $task->team?->name,
            $names !== '' ? $names : 'unassigned',
            str_replace('_', ' ', $task->status),
            'due '.$task->due_on->format('d M Y'),
        ]));
    }

    /**
     * @param  Builder<Task>  $query
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function paginate(Builder $query): LengthAwarePaginator
    {
        return $query->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Task $t) => TaskDirectory::row($t));
    }
}
