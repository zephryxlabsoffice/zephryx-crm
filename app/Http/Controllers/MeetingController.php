<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingAttendee;
use App\Models\Project;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\MeetingDirectory;
use App\Support\MeetingPresenter;
use App\Support\Meetings\MeetingProvider;
use App\Support\Notifier;
use App\Support\Rbac\Rbac;
use App\Support\Realm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Meetings — organising Google Meets. This server hosts none of them.
 *
 * Three pages: the list (`/meetings`), one meeting (`/meetings/{meeting}`) and
 * the scheduling form (`/meetings/schedule`).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE CRM ORGANISES; GOOGLE HOSTS
 *
 * Decided 2026-08-28. Every meeting is a Google Calendar event with a Meet
 * conference. This application holds who is meeting whom about what, and a
 * reference to that event. It never hosts, records or proxies a call, and it
 * never constructs a Meet URL — the link comes back from Google or there is no
 * link.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * A FAILED CREATE LEAVES THE MEETING REQUESTED, AND SAYS SO
 *
 * The provider is not implemented yet and every method on it throws. That is
 * deliberate (see GoogleMeetProvider), and this controller treats a throw the
 * way it will treat a Google outage: the record stays, the event id stays null,
 * the status reads "requested", and the person is told the invite did not go
 * out. What it must never do is swallow the failure and show a scheduled
 * meeting with no way to join.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class MeetingController extends Controller
{
    protected const PER_PAGE = 10;

    public function __construct(
        protected Rbac $rbac,
        protected AuditLog $audit,
        protected MeetingProvider $provider,
        protected Notifier $notify,
    ) {
    }

    /**
     * GET /meetings
     */
    public function index(Request $request): Response
    {
        $tab = $request->validate([
            'tab' => ['nullable', Rule::in(array_merge(['all'], MeetingPresenter::statusOptions()))],
        ])['tab'] ?? MeetingPresenter::SCHEDULED;

        $filters = $this->filters($request);
        $viewer = $request->user();

        $all = MeetingDirectory::rows(MeetingDirectory::query(), $viewer);
        $meetings = $tab === 'all' ? $all : $all->where('status', $tab)->values();

        return response()->view('meetings.index', [
            'activeNav' => 'meetings',
            'meetings' => $this->paginate(
                $this->matching(MeetingDirectory::rows(MeetingDirectory::query($filters), $viewer, $tab === 'all' ? null : $tab)),
                $request,
            ),
            'stats' => MeetingDirectory::stats($all),
            'tab' => $tab,
            'tabCounts' => [
                'scheduled' => $all->where('status', MeetingPresenter::SCHEDULED)->count(),
                'requested' => $all->where('status', MeetingPresenter::REQUESTED)->count(),
                'ended' => $all->where('status', MeetingPresenter::ENDED)->count(),
                'cancelled' => $all->where('status', MeetingPresenter::CANCELLED)->count(),
                'all' => $all->count(),
            ],
            'next' => MeetingDirectory::nextFor($viewer),
            'projects' => $this->projectOptions(),
            'maySchedule' => $this->rbac->can($viewer, 'meetings.schedule'),
        ] + $filters);
    }

    /**
     * GET /meetings/{meeting}
     */
    public function show(Request $request, string $meeting): Response
    {
        $record = $this->find($request, $meeting);
        $model = $record['model'];

        $viewer = $request->user();
        $employee = $this->employeeFor($request);

        return response()->view('meetings.show', [
            'activeNav' => 'meetings',
            'meeting' => $record,
            'isAttendee' => $model->isAttendedBy($viewer),
            'isOrganiser' => $employee !== null && $model->organiser_id === $employee->id,
            'maySchedule' => $this->rbac->can($viewer, 'meetings.schedule'),
        ]);
    }

    /**
     * GET /meetings/schedule
     */
    public function create(Request $request): Response
    {
        return response()->view('meetings.schedule', [
            'activeNav' => 'meetings',
            'employees' => Employee::query()
                ->with('user')
                ->active()
                ->get()
                ->map(fn (Employee $e) => [
                    'id' => $e->user?->id,
                    'name' => (string) $e->user?->name,
                    'detail' => (string) $e->designation?->name,
                ])
                ->sortBy('name')
                ->values()
                ->all(),
            'clients' => Client::query()->whereNot('status', 'completed')->orderBy('name')->get(['id', 'name']),
            'projects' => $this->projectOptions(),
            'defaultDuration' => (int) config('meetings.default_duration', 30),
            'zone' => MeetingPresenter::zone(),
        ]);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE WRITES
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * Schedule a meeting: write the record, then ask Google for the event.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THE RECORD IS SAVED BEFORE THE NETWORK CALL, ON PURPOSE
     *
     * If Google is asked first and this application then fails to save, an event
     * exists that nothing here knows about — invites are out for a meeting with
     * no record. The other way round, a failed create leaves a requested
     * meeting somebody can retry, which is a state the pages already draw.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $organiser = $this->employeeFor($request);

        $meeting = DB::transaction(function () use ($data, $organiser) {
            $meeting = Meeting::create([
                'reference' => MeetingDirectory::nextReference(),
                'title' => $data['title'],
                'agenda' => $data['agenda'] ?? null,
                'project_id' => $data['project_id'] ?? null,
                'organiser_id' => $organiser?->id,
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
            ]);

            $this->syncAttendees($meeting, $data['attendees'] ?? [], $organiser);

            return $meeting;
        });

        $this->audit->record(
            action: AuditLog::MEETING_SCHEDULED,
            actor: $request->user(),
            entityType: 'meeting',
            entityId: $meeting->reference,
            after: $meeting->title.' · '.MeetingPresenter::when($meeting->fresh()->toRecordArray($request->user())),
            request: $request,
        );

        /*
         * Before the provider call, and not inside its try. Google may be down;
         * the attendees are told either way, and this is the only thing that
         * tells them anything when it is.
         */
        $this->notify->meetingScheduled($meeting->fresh(), $request->user());

        return $this->createEvent($request, $meeting->fresh(['attendees.user', 'organiser.user']));
    }

    /**
     * Create the Google event for a meeting that has none.
     *
     * The retry for a failed create, and the act that turns a client's request
     * into a real meeting. Idempotent: a meeting that already has an event id
     * is left alone rather than given a second event and a second set of
     * invites.
     */
    public function createEvent(Request $request, Meeting|string $meeting): RedirectResponse
    {
        $model = $meeting instanceof Meeting
            ? $meeting
            : $this->find($request, $meeting)['model'];

        if ($model->event_id !== null) {
            return redirect()
                ->route('meetings.show', ['meeting' => $model->reference])
                ->with('status', 'This meeting is already on the calendar.')
                ->with('status_tone', 'info');
        }

        try {
            $event = $this->provider->create($model->toRecordArray($request->user()));

            $model->update([
                'event_id' => $event['event_id'],
                // Whatever came back. Never assembled here — see the head of
                // MeetingProvider.
                'join_url' => $event['join_url'],
                'html_link' => $event['html_link'],
            ]);

            $this->audit->record(
                action: AuditLog::MEETING_EVENT_CREATED,
                actor: $request->user(),
                entityType: 'meeting',
                entityId: $model->reference,
                after: 'Calendar event created',
                request: $request,
            );

            return redirect()
                ->route('meetings.show', ['meeting' => $model->reference])
                ->with('status', 'Invites are out.')
                ->with('status_tone', 'success');
        } catch (Throwable $e) {
            /*
             * The failure is a STATE, not something to swallow. The meeting
             * stays requested, the person is told, and nothing on screen
             * pretends an invite went out.
             */
            $this->audit->record(
                action: AuditLog::MEETING_EVENT_FAILED,
                actor: $request->user(),
                entityType: 'meeting',
                entityId: $model->reference,
                after: 'Calendar event not created: '.$e->getMessage(),
                request: $request,
            );

            return redirect()
                ->route('meetings.show', ['meeting' => $model->reference])
                ->with('status', 'The meeting is saved, but the calendar invite did not go out. Nobody has been invited yet.')
                ->with('status_tone', 'warning');
        }
    }

    /**
     * Call it off.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * GOOGLE IS TOLD FIRST, AND A FAILURE THERE STOPS THE CANCELLATION HERE
     *
     * A meeting called off in this application but still live on Google is
     * worse than one not cancelled at all: the organiser believes it is off and
     * the attendees turn up. So the local record is only marked cancelled once
     * the provider has confirmed — and if it throws, the meeting stays
     * scheduled and says why.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function cancel(Request $request, string $meeting): RedirectResponse
    {
        $record = $this->find($request, $meeting);
        $model = $record['model'];

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if ($model->cancelled_at !== null) {
            return redirect()
                ->route('meetings.show', ['meeting' => $model->reference])
                ->with('status', 'This meeting was already cancelled.')
                ->with('status_tone', 'info');
        }

        if ($model->event_id !== null) {
            try {
                $this->provider->cancel($model->toRecordArray($request->user()));
            } catch (Throwable $e) {
                $this->audit->record(
                    action: AuditLog::MEETING_CANCEL_FAILED,
                    actor: $request->user(),
                    entityType: 'meeting',
                    entityId: $model->reference,
                    after: 'Calendar cancellation failed: '.$e->getMessage(),
                    request: $request,
                );

                throw ValidationException::withMessages([
                    'reason' => 'The calendar could not be updated, so the meeting has NOT been cancelled. '
                        .'Everybody still has the invite — try again, or cancel it in Google Calendar.',
                ]);
            }
        }

        $model->update([
            'cancelled_at' => now(),
            'cancellation_reason' => $data['reason'],
        ]);

        $this->audit->record(
            action: AuditLog::MEETING_CANCELLED,
            actor: $request->user(),
            entityType: 'meeting',
            entityId: $model->reference,
            after: 'Cancelled: '.$data['reason'],
            request: $request,
        );

        /*
         * After the provider succeeded, which is the opposite of scheduling
         * above and for the same reason as the ordering there: a failed cancel
         * throws before this line, so nobody is ever told a meeting is off
         * while the invite is still live in their calendar.
         */
        $this->notify->meetingCancelled($model, $data['reason'], $request->user());

        return redirect()
            ->route('meetings.show', ['meeting' => $model->reference])
            ->with('status', 'Meeting cancelled and the invite withdrawn.')
            ->with('status_tone', 'info');
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE PIECES
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * @return array<string, mixed>
     */
    protected function find(Request $request, string $reference): array
    {
        $found = MeetingDirectory::find($reference, $request->user());

        abort_if($found === null, 404);

        return $found;
    }

    protected function employeeFor(Request $request): ?Employee
    {
        return Employee::where('user_id', $request->user()?->id)->first();
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'agenda' => ['nullable', 'string', 'max:2000'],
            'project_id' => ['nullable', Rule::exists('projects', 'id')],
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
            'duration' => ['required', 'integer', 'min:5', 'max:480'],
            'attendees' => ['nullable', 'array'],
            'attendees.*' => [Rule::exists('users', 'id')],
        ]);

        /*
         * Typed in the display zone, stored in UTC. The conversion happens once,
         * here, because a meeting is the one record where getting a timezone
         * wrong means people miss it — see App\Support\MeetingPresenter.
         */
        $start = Carbon::parse($data['date'].' '.$data['time'], MeetingPresenter::zone())->utc();

        return $data + [
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes((int) $data['duration']),
        ];
    }

    /**
     * @param  list<int|string>  $userIds
     */
    protected function syncAttendees(Meeting $meeting, array $userIds, ?Employee $organiser): void
    {
        $ids = collect($userIds)->map(fn ($id) => (int) $id);

        /*
         * The organiser is always on the invite. They called the meeting, and
         * an organiser who cannot see the join link is somebody locked out of
         * their own meeting — see Meeting::isAttendedBy, which is the other
         * half of the same rule.
         */
        if ($organiser?->user_id !== null) {
            $ids = $ids->push($organiser->user_id);
        }

        foreach ($ids->unique()->filter() as $userId) {
            MeetingAttendee::firstOrCreate(
                ['meeting_id' => $meeting->id, 'user_id' => $userId],
                // `awaiting`, because nobody has replied yet — and this
                // application never sets a response to anything else. That is
                // Google's to report.
                ['response' => MeetingPresenter::AWAITING],
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'project' => ['nullable', 'string', 'max:32'],
        ]);

        $search = trim($validated['q'] ?? '');

        return [
            'search' => $search,
            'project' => $validated['project'] ?? null,
            'filtered' => $search !== '' || ($validated['project'] ?? null) !== null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $meetings
     * @return Collection<int, array<string, mixed>>
     */
    protected function matching(Collection $meetings): Collection
    {
        return $meetings->values();
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    protected function projectOptions(): array
    {
        return Project::query()
            ->orderBy('name')
            ->get(['id', 'reference', 'name'])
            ->map(fn (Project $p) => ['id' => $p->reference, 'key' => $p->id, 'name' => $p->name])
            ->all();
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
