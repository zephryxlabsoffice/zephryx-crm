<?php

namespace App\Http\Controllers\Admin;

use App\Support\Diagnostics;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Admin > Diagnostics (owner, 2026-11-07): what went wrong for people, with the
 * codes they quote on tickets, and a download of it with the server's own log to
 * hand to whoever is fixing it. See App\Support\Diagnostics.
 */
class DiagnosticsController extends Controller
{
    public function index(): Response
    {
        return response()->view('admin.diagnostics.index', [
            'activeNav' => 'diagnostics',
            'ready' => Diagnostics::ready(),
            'events' => ! Diagnostics::ready() ? collect() : DB::table('diagnostic_events')
                ->leftJoin('users', 'users.id', '=', 'diagnostic_events.user_id')
                ->orderByDesc('diagnostic_events.id')
                ->limit(200)
                ->get(['diagnostic_events.*', 'users.name as user_name']),
            'keepDays' => Diagnostics::KEEP_DAYS,
        ]);
    }

    /**
     * GET /admin/diagnostics/download - the last week's events and the end of the
     * server's log, as one text file.
     */
    public function download(): Response
    {
        return response(Diagnostics::export(), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="zephryx-diagnostics-'.now()->format('Y-m-d-His').'.txt"',
        ]);
    }
}
