<?php

namespace App\Http\Middleware;

use App\Support\Maintenance;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the site to everybody but the Admin Panel while maintenance is on.
 * See App\Support\Maintenance.
 *
 * Signing in stays open, because the admin signs in through the same form;
 * a staff or client account that signs in meanwhile lands on the maintenance
 * page rather than its dashboard.
 */
class BlockDuringMaintenance
{
    /** Reachable while closed: signing in and out, and the Admin Panel itself. */
    protected const OPEN = [
        'login', 'login/*', 'logout', 'forgot-password', 'reset-password', 'reset-password/*',
        'theme', 'up', 'admin', 'admin/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! Maintenance::isActive()
            || Maintenance::exempts($request->user())
            || $request->is(...self::OPEN)) {
            return $next($request);
        }

        $state = Maintenance::state();

        return response()->view('errors.maintenance', [
            'until' => $state['to'] ?? null,
            'note' => $state['note'] ?? null,
            'signedIn' => $request->user() !== null,
        ], 503)->header('Retry-After', '600');
    }
}
