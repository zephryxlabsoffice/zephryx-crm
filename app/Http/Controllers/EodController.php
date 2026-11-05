<?php

namespace App\Http\Controllers;

use App\Mail\EodUrgentMail;
use App\Models\Employee;
use App\Models\EodItem;
use App\Models\EodReport;
use App\Models\EodUrgentRequest;
use App\Models\Project;
use App\Models\Task;
use App\Support\AttendancePolicy;
use App\Support\Audit\AuditLog;
use App\Support\EodPolicy;
use App\Support\EodUrgent;
use App\Support\Notifier;
use App\Support\Rbac\Rbac;
use App\Support\WebPush;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The end of day report: how work is reported done (owner, 2026-11-04).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * ONE REPORT PER PERSON PER DAY, AND IT MOVES THE TASKS
 *
 * Ticking an assigned task here is what updates that task, so nobody has to open
 * each one. Work nobody assigned goes in as a line of its own and never becomes a
 * task. A day can be filed late, and opening a day already filed edits it.
 *
 * AN OLDER DAY NEVER OVERWRITES A NEWER ONE
 *
 * Filing Monday on Wednesday must not reopen a task Tuesday's report finished, so
 * a report only moves a task if no later report mentions it. Nor may it undo what
 * somebody did to the task since that day: an earlier day can still say a task was
 * finished, but it cannot put one back to In Progress. Removing a line does not put
 * a task back either: that is a decision for somebody who can edit it.
 *
 * A task finished after that day is still offered, or the day it was being worked on
 * could never be reported.
 *
 * WHO IT GOES TO
 *
 * The person's reporting manager, the managers of the projects in the report, and
 * the leads of their teams and of the teams holding those tasks. Each person once.
 * The list is kept on the report, so moving teams later does not change who could
 * read what was already sent.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class EodController extends Controller
{
    /** @var list<string> Pending is what a task is before anybody has worked on it. */
    public const STATUSES = ['completed', 'in_progress', 'review', 'blocked'];

    /** The choice for work that belongs to no project. */
    public const NO_PROJECT = 'none';

    public function __construct(
        protected Rbac $rbac,
        protected AuditLog $audit,
        protected Notifier $notify,
        protected WebPush $push,
    ) {}

    /**
     * GET /tasks/eod - the form, for today or the date asked for.
     */
    public function form(Request $request): Response
    {
        $employee = $this->employeeOrRefuse($request);

        if (! EodPolicy::isLive()) {
            return $this->notLiveYet();
        }

        $date = $request->validate([
            'date' => $this->dateRules(nullable: true),
        ])['date'] ?? now()->toDateString();
        $date = Carbon::parse($date)->toDateString();

        $report = EodReport::query()
            ->with('items')
            ->where('employee_id', $employee->id)
            ->whereDate('report_date', $date)
            ->first();

        return response()->view('tasks.eod.form', [
            'activeNav' => 'tasks',
            'date' => $date,
            'earliestDate' => EodPolicy::startsOn()?->toDateString(),
            'report' => $report,
            'urgentRequest' => $report === null ? EodUrgent::forDay($employee->id, $date) : null,
            'assignedTasks' => $this->assignedTasks($employee, $date, $report),
            'extraItems' => $report?->items->whereNull('task_id')->values() ?? collect(),
            'projectChoices' => Project::query()->open()->orderBy('name')->get(['id', 'name', 'reference']),
            'statuses' => self::STATUSES,
            'alwaysSentTo' => $this->recipientsFor($employee, collect(), []),
            'recentReports' => EodReport::query()
                ->withCount('items')
                ->where('employee_id', $employee->id)
                ->latest('report_date')
                ->limit(10)
                ->get(),
        ]);
    }

    /**
     * POST /tasks/eod - file the report, or correct one already filed.
     */
    public function save(Request $request): RedirectResponse
    {
        $employee = $this->employeeOrRefuse($request);

        abort_unless(EodPolicy::isLive(), 403);

        $data = $this->validated($request);
        $date = Carbon::parse($data['report_date'])->toDateString();

        $existing = EodReport::query()->with('items')
            ->where('employee_id', $employee->id)
            ->whereDate('report_date', $date)
            ->first();

        $ticked = collect($data['assigned'] ?? [])->filter(fn (array $row) => ! empty($row['checked']));
        $tasks = $this->tasksTheyMayReport($employee, $existing, $ticked->keys()->all());

        if ($ticked->count() !== $tasks->count()) {
            throw ValidationException::withMessages(['assigned' => 'One of those tasks is not assigned to you.']);
        }

        $extras = collect($data['extra'] ?? []);

        if ($ticked->isEmpty() && $extras->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Tick a task or add one you worked on, so there is something to report.']);
        }

        $projectIds = $extras->pluck('project')->reject(fn ($project) => $project === self::NO_PROJECT)
            ->map(fn ($project) => (int) $project)
            ->merge($tasks->pluck('project_id')->filter())
            ->unique()->values()->all();

        $recipients = $this->recipientsFor($employee, $tasks, $projectIds);

        $urgentRequest = $existing === null ? EodUrgent::forDay($employee->id, $date) : null;

        if ($urgentRequest !== null) {
            $recipients = $recipients->merge(
                Employee::query()->active()->with('user')
                    ->where('user_id', $urgentRequest->requested_by)
                    ->whereKeyNot($employee->id)
                    ->get()
            )->unique('id')->values();
        }

        $report = DB::transaction(function () use ($employee, $date, $existing, $ticked, $tasks, $extras, $recipients, $urgentRequest) {
            $report = $existing ?? EodReport::create([
                'employee_id' => $employee->id,
                'report_date' => $date,
                'submitted_at' => now(),
            ]);

            $report->items()->delete();

            foreach ($tasks as $task) {
                $row = $ticked[$task->id];

                $report->items()->create([
                    'task_id' => $task->id,
                    'project_id' => $task->project_id,
                    'title' => $task->name,
                    'status' => $row['status'],
                    'comment' => $row['comment'] ?? null,
                ]);
            }

            foreach ($extras as $row) {
                $report->items()->create([
                    'project_id' => $row['project'] === self::NO_PROJECT ? null : (int) $row['project'],
                    'title' => $row['title'],
                    'status' => $row['status'],
                    'comment' => $row['comment'] ?? null,
                ]);
            }

            $report->recipients()->syncWithoutDetaching($recipients->pluck('id')->all());

            $urgentRequest?->update(['fulfilled_at' => now()]);

            return $report;
        });

        foreach ($tasks as $task) {
            $this->moveTask($request, $task, $ticked[$task->id]['status'], $report);
        }

        $this->audit->record(
            action: $existing === null ? AuditLog::EOD_FILED : AuditLog::EOD_UPDATED,
            actor: $request->user(),
            entityType: 'eod',
            entityId: (string) $report->id,
            after: $report->report_date->format('D d M Y').' · '
                .($ticked->count() + $extras->count()).' items · sent to '.$recipients->count(),
            request: $request,
        );

        $this->notify->eodFiled(
            $report,
            $employee,
            $report->recipients()->with('user')->get()->reject(fn (Employee $person) => $person->user_id === $urgentRequest?->requested_by),
            $existing !== null,
            $request->user(),
        );

        if ($urgentRequest !== null) {
            $this->notify->eodUrgentFiled($report, $employee, $urgentRequest->requester, $request->user());
        }

        return redirect()
            ->route('tasks.eod.show', ['eod' => $report->id])
            ->with('status', $existing === null ? 'Your report was sent.' : 'Your report was updated.')
            ->with('status_tone', 'success');
    }

    /**
     * GET /tasks/eod/team - reports sent to the viewer.
     */
    public function team(Request $request): Response
    {
        if (! EodPolicy::isLive()) {
            return $this->notLiveYet();
        }

        $date = $request->validate([
            'date' => $this->dateRules(nullable: true),
        ])['date'] ?? now()->toDateString();
        $date = Carbon::parse($date)->toDateString();

        $viewerId = $this->employeeFor($request)?->id;

        return response()->view('tasks.eod.team', [
            'activeNav' => 'tasks',
            'date' => $date,
            'reports' => $this->visibleTo($request)
                ->with(['employee.user', 'employee.department', 'items'])
                ->whereDate('report_date', $date)
                ->when($viewerId !== null, fn (Builder $q) => $q->where('employee_id', '!=', $viewerId))
                ->get()
                ->sortBy(fn (EodReport $report) => $report->employee->user?->name)
                ->values(),
            'notFiled' => $this->notFiledFor($request, $date),
            'urgentRequests' => EodUrgent::onDay($date),
            'isWorkingDay' => AttendancePolicy::isWorkingDay($date),
            'earliestDate' => EodPolicy::startsOn()?->toDateString(),
        ]);
    }

    /**
     * POST /tasks/eod/urgent - a manager or team lead needs this report now.
     *
     * Only for somebody on the viewer's own Not filed list for that day. Anybody else
     * gets the same answer as somebody who has just filed, and nothing is written.
     */
    public function markUrgent(Request $request): RedirectResponse
    {
        abort_unless(EodPolicy::isLive() && EodUrgent::isReady(), 403);

        $data = $request->validate([
            'employee' => ['required', 'integer'],
            'date' => $this->dateRules(),
        ]);
        $date = Carbon::parse($data['date'])->toDateString();
        $back = redirect()->route('tasks.eod.team', ['date' => $date]);

        $employee = $this->notFiledFor($request, $date)->firstWhere('id', (int) $data['employee']);

        if ($employee === null) {
            return $back
                ->with('status', 'Nothing was marked: there is no report waiting from this person for that day.')
                ->with('status_tone', 'info');
        }

        try {
            $urgentRequest = EodUrgent::forDay($employee->id, $date) ?? EodUrgentRequest::create([
                'employee_id' => $employee->id,
                'report_date' => $date,
                'requested_by' => $request->user()->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            $urgentRequest = EodUrgent::forDay($employee->id, $date);
        }

        if (! $urgentRequest->wasRecentlyCreated) {
            return $back
                ->with('status', $urgentRequest->requesterName().' has already marked this report urgent.')
                ->with('status_tone', 'info');
        }

        $this->notify->eodUrgent($employee->user, $request->user(), Carbon::parse($date));
        $this->push->sendTo([$employee->user_id]);
        $this->emailUrgent($employee, $request->user()->name, Carbon::parse($date));

        $this->audit->record(
            action: AuditLog::EOD_URGENT,
            actor: $request->user(),
            entityType: 'eod',
            entityId: (string) $urgentRequest->id,
            after: ($employee->user?->name ?? 'Somebody').' · '.Carbon::parse($date)->format('D d M Y'),
            request: $request,
        );

        return $back
            ->with('status', ($employee->user?->name ?? 'They').' has been told this report is urgent.')
            ->with('status_tone', 'success');
    }

    /**
     * A mail server being down must not undo the bell and notice already written.
     */
    protected function emailUrgent(Employee $employee, string $requesterName, Carbon $date): void
    {
        if (blank($employee->user?->email)) {
            return;
        }

        try {
            Mail::to($employee->user->email)->send(new EodUrgentMail(
                name: $employee->user->name,
                requesterName: $requesterName,
                reportDay: $date->format('D j M'),
                url: route('tasks.eod', ['date' => $date->toDateString()]),
            ));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Who the viewer is waiting on: those who report to them, the members of teams they
     * lead, and the people on projects they manage while that permission is held; or
     * everybody for a holder of the view-all permission.
     *
     * @return Collection<int, Employee>
     */
    protected function notFiledFor(Request $request, string $date): Collection
    {
        $viewerId = $this->employeeFor($request)?->id;
        $onTheirProjects = $viewerId !== null && $this->rbac->can($request->user(), EodUrgent::PROJECT_PERMISSION)
            ? EodUrgent::peopleOnProjectsManagedBy($viewerId)
            : [];

        return EodPolicy::notFiledOn($date)
            ->with(['user', 'department'])
            ->when($viewerId !== null, fn (Builder $q) => $q->whereKeyNot($viewerId))
            ->when(
                ! $this->rbac->can($request->user(), EodReport::VIEW_ALL_PERMISSION),
                fn (Builder $q) => $q->where(fn (Builder $mine) => $mine
                    ->where('reports_to', $viewerId ?? 0)
                    ->orWhereHas('teams', fn (Builder $team) => $team->where('lead_id', $viewerId ?? 0))
                    ->orWhereIn('id', $onTheirProjects)),
            )
            ->get()
            ->sortBy(fn (Employee $employee) => $employee->user?->name)
            ->values();
    }

    protected function notLiveYet(): Response
    {
        return response()->view('tasks.eod.not-live', [
            'activeNav' => 'tasks',
            'startsOn' => EodPolicy::startsOn(),
        ]);
    }

    /**
     * Not in the future, and not before End of Day began.
     *
     * @return list<string>
     */
    protected function dateRules(bool $nullable = false): array
    {
        $start = EodPolicy::startsOn();

        return array_values(array_filter([
            $nullable ? 'nullable' : 'required',
            'date',
            'before_or_equal:today',
            $start === null ? null : 'after_or_equal:'.$start->toDateString(),
        ]));
    }

    /**
     * GET /tasks/eod/{eod} - one report, to its author, whoever it went to, or a
     * holder of the permission. Anybody else gets a 404, not a confirmation it exists.
     */
    public function show(Request $request, int $eod): Response
    {
        $report = $this->visibleTo($request)
            ->with(['employee.user', 'items.task', 'items.project', 'recipients.user'])
            ->find($eod);

        abort_if($report === null, 404);

        return response()->view('tasks.eod.show', [
            'activeNav' => 'tasks',
            'report' => $report,
            'isMine' => $report->employee_id === $this->employeeFor($request)?->id,
            'urgentRequest' => EodUrgent::forDay($report->employee_id, $report->report_date->toDateString()),
        ]);
    }

    /**
     * @return Builder<EodReport>
     */
    protected function visibleTo(Request $request): Builder
    {
        $query = EodReport::query();

        if ($this->rbac->can($request->user(), EodReport::VIEW_ALL_PERMISSION)) {
            return $query;
        }

        $viewerId = $this->employeeFor($request)?->id ?? 0;

        return $query->where(fn (Builder $q) => $q
            ->where('employee_id', $viewerId)
            ->orWhereHas('recipients', fn (Builder $r) => $r->where('employees.id', $viewerId)));
    }

    /**
     * Their tasks that have started by that date and were still open on it, plus
     * anything already in this report, so a task finished in it stays on the page.
     *
     * @return Collection<int, Task>
     */
    protected function assignedTasks(Employee $employee, string $date, ?EodReport $report): Collection
    {
        $alreadyReported = $report?->items->pluck('task_id')->filter()->all() ?? [];

        return Task::query()
            ->with('project')
            ->whereHas('assignees', fn (Builder $q) => $q->where('employees.id', $employee->id))
            ->where(fn (Builder $q) => $q
                ->whereIn('id', $alreadyReported)
                ->orWhere(fn (Builder $open) => $open
                    ->where(fn (Builder $stillOpen) => $stillOpen
                        ->open()
                        ->orWhere('completed_at', '>=', Carbon::parse($date)->startOfDay()))
                    ->where(fn (Builder $started) => $started
                        ->whereNull('starts_on')
                        ->orWhereDate('starts_on', '<=', $date))))
            ->orderBy('due_on')
            ->get();
    }

    /**
     * @param  list<int>  $taskIds
     * @return Collection<int, Task>
     */
    protected function tasksTheyMayReport(Employee $employee, ?EodReport $existing, array $taskIds): Collection
    {
        $alreadyReported = $existing?->items->pluck('task_id')->filter()->all() ?? [];

        return Task::query()
            ->whereIn('id', $taskIds)
            ->where(fn (Builder $q) => $q
                ->whereIn('id', $alreadyReported)
                ->orWhereHas('assignees', fn (Builder $a) => $a->where('employees.id', $employee->id)))
            ->get()
            ->keyBy('id');
    }

    /**
     * Reporting manager, project managers, and team leads, each once and never the author.
     *
     * @param  Collection<int, Task>  $tasks
     * @param  list<int>  $projectIds
     * @return Collection<int, Employee>
     */
    protected function recipientsFor(Employee $employee, Collection $tasks, array $projectIds): Collection
    {
        $ids = collect([$employee->reports_to])
            ->merge(Project::query()->whereIn('id', $projectIds)->pluck('manager_id'))
            ->merge($employee->teams()->pluck('lead_id'))
            ->merge(DB::table('teams')->whereIn('id', $tasks->pluck('team_id')->filter())->pluck('lead_id'))
            ->filter()
            ->unique()
            ->reject(fn ($id) => (int) $id === $employee->id);

        return Employee::query()->active()->with('user')->whereIn('id', $ids)->get();
    }

    protected function moveTask(Request $request, Task $task, string $status, EodReport $report): void
    {
        $aLaterReportSpeaksForIt = EodItem::query()
            ->where('task_id', $task->id)
            ->whereHas('report', fn (Builder $q) => $q->whereDate('report_date', '>', $report->report_date))
            ->exists();

        if ($aLaterReportSpeaksForIt || $task->status === $status) {
            return;
        }

        $changedSinceThatDay = $task->updated_at?->gt($report->report_date->copy()->endOfDay()) ?? false;

        if ($status !== 'completed' && $changedSinceThatDay) {
            return;
        }

        $before = $task->status;

        $task->update([
            'status' => $status,
            // The day the work was done, which a late report can put in the past.
            'completed_at' => $status === 'completed'
                ? ($task->completed_at ?? $report->report_date->copy()->endOfDay()->min(now()))
                : $task->completed_at,
        ]);

        $this->audit->record(
            action: $status === 'completed' ? AuditLog::TASK_COMPLETED : AuditLog::TASK_UPDATED,
            actor: $request->user(),
            entityType: 'task',
            entityId: $task->reference,
            before: $before,
            after: $task->name.' is '.$status.', from the report for '.$report->report_date->format('d M Y'),
            request: $request,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'report_date' => $this->dateRules(),
            'assigned' => ['nullable', 'array'],
            'assigned.*.checked' => ['nullable', 'boolean'],
            'assigned.*.status' => ['required_with:assigned.*.checked', Rule::in(self::STATUSES)],
            'assigned.*.comment' => ['nullable', 'string', 'max:1000'],
            'extra' => ['nullable', 'array', 'max:50'],
            'extra.*.project' => ['required', function (string $attribute, mixed $value, \Closure $fail) {
                if ($value !== self::NO_PROJECT && ! Project::whereKey($value)->exists()) {
                    $fail('Choose a project from the list.');
                }
            }],
            'extra.*.title' => ['required', 'string', 'max:160'],
            'extra.*.status' => ['required', Rule::in(self::STATUSES)],
            'extra.*.comment' => ['nullable', 'string', 'max:1000'],
        ], [
            'extra.*.project.required' => 'Choose a project, or "No project".',
            'extra.*.title.required' => 'Write what the task was.',
        ]);
    }

    protected function employeeFor(Request $request): ?Employee
    {
        return Employee::where('user_id', $request->user()?->id)->first();
    }

    /**
     * Only somebody with an employee record has days to report. A company account
     * has none.
     */
    protected function employeeOrRefuse(Request $request): Employee
    {
        $employee = $this->employeeFor($request);

        abort_if($employee === null, 403);

        return $employee;
    }
}
