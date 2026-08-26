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
 * Leads and Calendar are the exception: §12 keeps them in the navigation but
 * has them return 404 until v2, so they route to `missing()` rather than here.
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
