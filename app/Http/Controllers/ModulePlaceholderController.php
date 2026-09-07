<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Stands in for a module that has not been built yet.
 *
 * Every navigation entry resolves to a real route from day one, so the shell
 * can be reviewed whole and the navigation's shape never shifts as modules
 * land one at a time (foundation spec §12). Each module replaces its own route
 * when it is built; this controller shrinks as they do.
 *
 * Leads, Calendar and Reports are the exception: §12 keeps them in the
 * navigation but has them return 404 until v2, so they route to `missing()`
 * rather than here.
 *
 * The difference between the two is worth keeping straight. This controller
 * says "being built" — it is for a module whose page is coming shortly, and it
 * invites somebody to check back. `missing()` says "planned, not next", which is
 * the honest answer for a module nobody is working on. Pointing the second kind
 * at a placeholder is how a roadmap turns into a set of pages that never change.
 */
class ModulePlaceholderController extends Controller
{
    public function __invoke(string $module = ''): Response
    {
        $entry = collect(config('navigation'))->firstWhere('key', $module);

        return response()->view('modules.placeholder', [
            'activeNav' => $module,
            'moduleLabel' => $entry['label'] ?? 'This module',
        ]);
    }

    /**
     * Deferred to v2 — the navigation entry exists, the page does not.
     */
    public function missing(): never
    {
        abort(404);
    }
}
