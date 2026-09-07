<?php

namespace App\Http\Controllers\Client;

use App\Support\Demo\DemoClientPortal;
use App\Support\MeetingPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

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

    public function index(Request $request): Response
    {
        $client = $this->client($request);

        $meetings = DemoClientPortal::meetings($client);

        return response()->view('client.meetings.index', $this->shell($request, 'meetings') + [
            'meetings' => $this->paginate($meetings->sortByDesc('starts_at')->values(), $request),
            'stats' => DemoClientPortal::stats($client)['meetings'],
            'next' => DemoClientPortal::nextMeeting($client),
        ]);
    }

    /**
     * GET /client/meetings/request
     */
    public function create(Request $request): Response
    {
        return response()->view('client.meetings.create', $this->shell($request, 'meetings') + [
            // Their own projects only, and the write must re-check rather than
            // trusting what the select posts back.
            'projects' => DemoClientPortal::projects($this->client($request)),
            'zone' => MeetingPresenter::zone(),
        ]);
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
