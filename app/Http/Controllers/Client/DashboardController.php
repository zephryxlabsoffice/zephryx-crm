<?php

namespace App\Http\Controllers\Client;

use App\Support\ClientPortal;
use App\Models\ProjectUpdate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The client's dashboard.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHAT CAME OFF THE HANDOVER'S VERSION
 *
 * FOUR NAVIGATION CARDS. The design opened with "My Projects — view your active
 * and completed projects", "Invoices — view payment status", and two more, each
 * duplicating a sidebar entry sitting six inches to the left. A row of cards
 * that only repeats the navigation costs the reader the top of the page and
 * tells them nothing. What replaced them is the two things a client actually
 * arrives to DO and cannot do from the sidebar in one click: raise a ticket and
 * request a meeting.
 *
 * "COMPLETED TASKS: 18". Two problems. It is a lifetime count, so it reads zero
 * for a client whose project started this month and grows meaninglessly for one
 * who has been with us three years. And tasks are internal work items — a
 * client's unit of progress is the project and the daily updates on it, which
 * is what the project pages show. Nothing here counts tasks.
 *
 * A FIFTH INVOICE TILE reading "Total Paid ₹18,75,000", which was a verbatim
 * repeat of the Paid tile's own subtitle. Four tiles, computed from the same
 * collections the tables list, so they cannot drift apart the way the
 * handover's did — its tiles claimed four pending invoices totalling ₹4,20,000
 * above a table showing three totalling ₹6,70,000.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class DashboardController extends PortalController
{
    public function __invoke(Request $request): Response
    {
        $client = $this->client($request);

        /*
         * The one route in this realm an Inactive client may still reach
         * without a 403 — because it is what SHOWS them they are inactive,
         * with the one thing still theirs (Invoices) one click away. See
         * PortalController::requireActiveClient for the rule everywhere
         * else.
         */
        if (! $client->isActive()) {
            return response()->view('client.ex-client', $this->shell($request, 'dashboard'));
        }

        $projects = ClientPortal::projects($client);

        return response()->view('client.dashboard', $this->shell($request, 'dashboard') + [
            'stats' => ClientPortal::stats($client),
            'projects' => $projects->take(3),
            // Newest first: what a client opens the portal to read.
            'tickets' => ClientPortal::tickets($client)->take(3),
            'meetings' => ClientPortal::meetings($client)->take(2),
            'nextMeeting' => ClientPortal::nextMeeting($client),
            /*
             * The most recent client-visible update across their projects.
             * Internal notes cannot reach this: ClientPortal::updates
             * filters on ownership and ProjectUpdate on visibility, and
             * neither is optional. See the head of ProjectUpdate.
             */
            'updates' => $projects
                ->flatMap(fn (array $p) => ClientPortal::updates($client, $p['id'])
                    ->map(fn (array $u) => $u + ['project_record' => $p]))
                ->sortByDesc('posted_at')
                ->take(3)
                ->values(),
            'visibility' => ProjectUpdate::CLIENT,
        ]);
    }
}
