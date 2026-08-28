<?php

namespace App\Http\Controllers;

use App\Support\Demo\DemoClients;
use App\Support\Demo\DemoEmployees;
use App\Support\Demo\DemoMeetings;
use App\Support\Demo\DemoProjects;
use App\Support\MeetingPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

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
 * conference, created on the company Workspace account. This application holds
 * who is meeting whom about what, and a reference to that event. It never
 * hosts, records or proxies a call, and it never constructs a Meet URL — the
 * link comes back from Google or there is no link.
 *
 * Attendee responses belong to Google Calendar. They are read back and shown;
 * nothing here sets one.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHAT THE BACKEND OWES
 *
 * 1. ONLY A PROJECT MANAGER OR THE SYSTEM ADMIN CREATES A MEETING
 *    (decided 2026-08-28). Clients may REQUEST one — that is a different act,
 *    and it produces a record with no Google event until somebody creates it.
 *
 * 2. THE JOIN LINK IS FOR ATTENDEES. A Meet link is effectively a password.
 *    It is withheld in PHP, not hidden with CSS: whatever the browser receives
 *    has already been read by whoever is at the browser.
 *
 * 3. CANCELLING MUST REACH GOOGLE. A meeting called off here but still live
 *    there is worse than not cancelling — the organiser believes it is off and
 *    the attendees turn up.
 *
 * 4. CREATING MUST BE IDEMPOTENT. Twice must not mean two events and two sets
 *    of invites.
 *
 * 5. TIMES ARE UTC IN STORE, IST ON SCREEN. Nothing renders a raw stored value;
 *    everything goes through App\Support\MeetingPresenter.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class MeetingController extends Controller
{
    protected const PER_PAGE = 10;

    /**
     * GET /meetings
     */
    public function index(Request $request): Response
    {
        $tab = $request->validate([
            'tab' => ['nullable', Rule::in(array_merge(['all'], MeetingPresenter::statusOptions()))],
        ])['tab'] ?? MeetingPresenter::SCHEDULED;

        $all = DemoMeetings::all();
        $meetings = $tab === 'all' ? $all : $all->where('status', $tab)->values();

        $filters = $this->filters($request);
        $counts = DemoMeetings::stats($all);
        $viewer = DemoMeetings::VIEWER;

        return response()->view('meetings.index', [
            'activeNav' => 'meetings',
            'meetings' => $this->paginate($this->matching($meetings, $filters), $request),
            'stats' => $counts,
            'tab' => $tab,
            'tabCounts' => [
                'scheduled' => $counts['scheduled'],
                'requested' => $counts['requested'],
                'ended' => $counts['ended'],
                'cancelled' => $counts['cancelled'],
                'all' => $counts['total'],
            ],
            'next' => $this->decorateForViewer(DemoMeetings::nextFor($viewer), $viewer),
            'projects' => DemoProjects::all()->map(fn (array $p) => ['id' => $p['id'], 'name' => $p['name']])->values()->all(),
        ] + $filters);
    }

    /**
     * GET /meetings/{meeting}
     */
    public function show(string $meeting): Response
    {
        $record = DemoMeetings::find($meeting);

        abort_if($record === null, 404);

        $viewer = DemoMeetings::VIEWER;

        return response()->view('meetings.show', [
            'activeNav' => 'meetings',
            'meeting' => $this->decorateForViewer($record, $viewer),
            'isAttendee' => DemoMeetings::isAttendee($record, $viewer),
            'isOrganiser' => $record['organiser'] === $viewer,
        ]);
    }

    /**
     * GET /meetings/schedule
     */
    public function create(): Response
    {
        return response()->view('meetings.schedule', [
            'activeNav' => 'meetings',
            'employees' => DemoEmployees::all()
                ->where('status', '!=', 'inactive')
                ->map(fn (array $e) => ['id' => $e['user_id'], 'name' => $e['name'], 'detail' => $e['designation']])
                ->values()
                ->all(),
            'clients' => DemoClients::all()->pluck('name')->all(),
            'projects' => DemoProjects::all()->map(fn (array $p) => ['id' => $p['id'], 'name' => $p['name']])->values()->all(),
            'defaultDuration' => (int) config('meetings.default_duration', 30),
            'zone' => MeetingPresenter::zone(),
        ]);
    }

    /**
     * Strip the join link unless this viewer is on the invite.
     *
     * Withheld here, in PHP. Not rendered-and-hidden, not passed in a data
     * attribute: whatever reaches the browser has already been read by whoever
     * is sitting at it.
     *
     * @param  array<string, mixed>|null  $meeting
     * @return array<string, mixed>|null
     */
    protected function decorateForViewer(?array $meeting, string $viewer): ?array
    {
        if ($meeting === null) {
            return null;
        }

        if (! DemoMeetings::isAttendee($meeting, $viewer)) {
            $meeting['join_url'] = null;
        }

        return $meeting;
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
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected function matching(Collection $meetings, array $filters): Collection
    {
        $viewer = DemoMeetings::VIEWER;

        return $meetings
            ->when($filters['search'] !== '', fn (Collection $rows) => $rows->filter(
                fn (array $m) => str_contains(
                    mb_strtolower($m['title'].' '.$m['id'].' '.($m['project_record']['name'] ?? '')),
                    mb_strtolower($filters['search'])
                )
            ))
            ->when($filters['project'], fn (Collection $rows) => $rows->where('project', $filters['project']))
            // Soonest first for anything still to come; most recent first for
            // what has passed. One list, sorted the way it is read.
            ->sortBy('starts_at')
            ->map(fn (array $m) => $this->decorateForViewer($m, $viewer))
            ->values();
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
