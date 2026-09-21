<?php

namespace App\Http\Controllers\Client;

use App\Models\Meeting;
use App\Models\MeetingAttendee;
use App\Models\Project;
use App\Support\Audit\AuditLog;
use App\Support\ClientPortal;
use App\Support\MeetingDirectory;
use App\Support\MeetingPresenter;
use App\Support\Meetings\MeetingProvider;
use App\Support\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The client's meetings, and the form that asks for one.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * REQUESTING IS NOT SCHEDULING
 *
 * A client cannot put an event in anybody's calendar. What they can do is ask,
 * which produces a record with no Google Calendar event behind it — a project
 * manager or the system admin creates the actual meeting, and that act is what
 * sends the invitations.
 *
 * That is why the reference's "Pending · Awaiting confirmation" state exists,
 * and it maps exactly onto MeetingPresenter::REQUESTED, which the staff
 * meetings module already renders in its own queue. One state, both sides.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * There is no route here that records an RSVP, and there is not going to be
 * one. Responses belong to Google Calendar: people accept or decline in their
 * own calendar and this application reads that back. A second accept button
 * here would be a second source of truth for the same fact.
 *
 * The meetings listed are the ones this client is ON. A meeting about their
 * project that they were not invited to is an internal one, and listing it
 * would tell them a conversation happened.
 */
class MeetingController extends PortalController
{
    protected const PER_PAGE = 10;

    public function __construct(
        protected AuditLog $audit,
        protected MeetingProvider $provider,
        protected Notifier $notify,
    ) {}

    public function index(Request $request): Response
    {
        $client = $this->requireActiveClient($request);

        $meetings = ClientPortal::meetings($client);

        return response()->view('client.meetings.index', $this->shell($request, 'meetings') + [
            'meetings' => $this->paginate($meetings->sortByDesc('starts_at')->values(), $request),
            'stats' => ClientPortal::stats($client)['meetings'],
            'next' => ClientPortal::nextMeeting($client),
        ]);
    }

    /**
     * GET /client/meetings/{meeting}
     */
    public function show(Request $request, string $meeting): Response
    {
        $client = $this->requireActiveClient($request);

        $record = ClientPortal::meeting($client, $meeting);

        abort_if($record === null, 404);

        return response()->view('client.meetings.show', $this->shell($request, 'meetings') + [
            'meeting' => $this->decorate($record),
        ]);
    }

    /**
     * Strip the organiser down to a name, the same redaction
     * Client\ProjectController applies to a project's manager.
     *
     * `organiser_record` off ClientPortal::meeting is a full EmployeeDirectory
     * row — email, department, designation, joining date. The client needs to
     * know who called the meeting, not read their personnel file, so this
     * REPLACES the key with a merge rather than adding to it: `$project +
     * [...]` would keep the left side's value for a key that already exists.
     *
     * @param  array<string, mixed>  $meeting
     * @return array<string, mixed>
     */
    protected function decorate(array $meeting): array
    {
        $organiser = $meeting['organiser_record'] ?? null;

        return array_merge($meeting, [
            'organiser_record' => $organiser === null ? null : [
                'name' => $organiser['name'],
            ],
        ]);
    }

    /**
     * GET /client/meetings/request
     */
    public function create(Request $request): Response
    {
        $client = $this->requireActiveClient($request);

        return response()->view('client.meetings.create', $this->shell($request, 'meetings') + [
            // Their own projects only, and the write must re-check rather than
            // trusting what the select posts back.
            'projects' => ClientPortal::projects($client),
            'zone' => MeetingPresenter::zone(),
        ]);
    }

    /**
     * POST /client/meetings/request — ask for a meeting.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THIS PRODUCES A RECORD, NOT AN EVENT
     *
     * No organiser, no Google event, no invitations: MeetingPresenter reads a
     * meeting with no `event_id` as REQUESTED, and it appears in the staff
     * queue for somebody to create. A client putting an event straight into
     * everybody's calendar is the thing this shape exists to prevent.
     *
     * The project is re-checked against the signed-in client rather than
     * trusted from the select — §6, and the reason this method resolves the
     * client from the session and then filters on it.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function store(Request $request): RedirectResponse
    {
        $client = $this->requireActiveClient($request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'agenda' => ['nullable', 'string', 'max:2000'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
            'duration' => ['nullable', 'integer', 'min:15', 'max:240'],
            'project_id' => ['nullable', Rule::exists('projects', 'id')],
        ]);

        if (($data['project_id'] ?? null) !== null) {
            $project = Project::find($data['project_id']);

            if ($project === null || $project->client_id !== $client->id) {
                // Somebody else's project. Refused rather than silently
                // dropped, because a request attached to the wrong project
                // reaches the wrong team.
                throw ValidationException::withMessages([
                    'project_id' => 'That project is not one of yours.',
                ]);
            }
        }

        // Typed in the display zone, stored UTC — the same conversion the staff
        // side does, in the same one place.
        $start = Carbon::parse($data['date'].' '.$data['time'], MeetingPresenter::zone())->utc();
        $minutes = (int) ($data['duration'] ?? config('meetings.default_duration', 30));

        $meeting = Meeting::create([
            'reference' => MeetingDirectory::nextReference(),
            'title' => $data['title'],
            'agenda' => $data['agenda'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            // No organiser and no event: this is a request.
            'organiser_id' => null,
            'requested_by_client_id' => $client->id,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes($minutes),
        ]);

        // The person asking is on their own request, so they see it and its
        // link once somebody creates the event.
        MeetingAttendee::firstOrCreate(
            ['meeting_id' => $meeting->id, 'user_id' => $request->user()->id],
            ['response' => MeetingPresenter::AWAITING],
        );

        app(AuditLog::class)->record(
            action: AuditLog::MEETING_REQUESTED,
            actor: $request->user(),
            entityType: 'meeting',
            entityId: $meeting->reference,
            after: $client->name.' asked for “'.$meeting->title.'”',
            request: $request,
        );

        return redirect()
            ->route('client.meetings.index')
            ->with('status', 'Request sent. Somebody will confirm a time and send the invitation.')
            ->with('status_tone', 'success');
    }

    /**
     * POST /client/meetings/{meeting}/cancel
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THE SAME ORDER AS THE STAFF SIDE, FOR THE SAME REASON
     *
     * Google is told first. A meeting called off here but still live on the
     * calendar is worse than one left alone — the client believes it is off
     * and whoever is on the other end still turns up. If the provider throws,
     * the meeting stays scheduled and the client is told why, exactly as
     * MeetingController::cancel does for staff.
     *
     * Ownership runs through ClientPortal::meetingModel, which applies the
     * same "requested by them or on their own project" filter as every other
     * read in this realm — there is no meeting id a client can hand this
     * route that resolves to somebody else's meeting.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function cancel(Request $request, string $meeting): RedirectResponse
    {
        $client = $this->requireActiveClient($request);

        $model = ClientPortal::meetingModel($client, $meeting);

        abort_if($model === null, 404);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if ($model->cancelled_at !== null) {
            return redirect()
                ->route('client.meetings.show', ['meeting' => $model->reference])
                ->with('status', 'This meeting was already cancelled.')
                ->with('status_tone', 'info');
        }

        if ($model->event_id !== null) {
            try {
                $this->provider->cancel($model->toRecordArray(null));
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
                        .'Everybody still has the invite — try again, or contact us to cancel it.',
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
            after: 'Cancelled by '.$client->name.': '.$data['reason'],
            request: $request,
        );

        $this->notify->meetingCancelled($model, $data['reason'], $request->user());

        return redirect()
            ->route('client.meetings.index')
            ->with('status', 'Meeting cancelled and the invite withdrawn.')
            ->with('status_tone', 'info');
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
