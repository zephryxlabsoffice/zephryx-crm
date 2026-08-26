<?php

namespace App\Http\Controllers;

use App\Support\Realm;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class LandingController extends Controller
{
    /**
     * GET / — the public landing page (foundation spec §9.1).
     *
     * An already-authenticated visitor never sees it; they are sent to their
     * own realm's dashboard instead.
     */
    public function __invoke(Request $request): Response|\Illuminate\Http\RedirectResponse
    {
        if ($request->user()) {
            return redirect(Realm::dashboardFor($request->user()));
        }

        return response()->view('landing.index');
    }
}
