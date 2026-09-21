<?php

namespace App\Http\Controllers\Client;

use App\Support\AnnouncementDirectory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The client board — read-only.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * A SEPARATE BOARD, NOT A FILTERED VIEW OF OURS
 *
 * The staff board mixes internal categories (policy, HR, training) with
 * things a client has no reason to see. This one shows only what an author
 * explicitly marked `for_clients` on the way in — see
 * Announcement::scopeForClientBoard. There is no route here that could show
 * anything else: AnnouncementDirectory::forClients()/findForClients() are the
 * only calls this controller makes, and neither takes an audience argument
 * that could be widened.
 *
 * No ownership check beyond `requireActiveClient` — an announcement is not
 * one client's record the way a project or an invoice is, so there is nothing
 * here for ClientPortal to scope by client. Every client reads the same
 * board, the same way every employee reads the same staff one.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class AnnouncementController extends PortalController
{
    public function index(Request $request): Response
    {
        $client = $this->requireActiveClient($request);

        return response()->view('client.announcements.index', $this->shell($request, 'announcements') + [
            'announcements' => AnnouncementDirectory::forClients(),
        ]);
    }

    public function show(Request $request, string $announcement): Response
    {
        $client = $this->requireActiveClient($request);

        $record = AnnouncementDirectory::findForClients($announcement);

        abort_if($record === null, 404);

        return response()->view('client.announcements.show', $this->shell($request, 'announcements') + [
            'announcement' => $record,
        ]);
    }
}
