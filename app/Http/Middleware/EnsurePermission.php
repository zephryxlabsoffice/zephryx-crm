<?php

namespace App\Http\Middleware;

use App\Support\Rbac\Rbac;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The second barrier (foundation spec §5).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * REALM FIRST, THEN THIS
 *
 * EnsureRealm decides whether an account belongs in this part of the
 * application at all, before any data is read. This decides whether it may do
 * the particular thing — and it runs second, because "a client on /employees"
 * is refused for being in the wrong realm whatever permissions it holds (§3.1).
 *
 * WHY IT IS MIDDLEWARE AND NOT A CHECK IN EACH CONTROLLER
 *
 * A check inside a method is a check somebody can forget to write, and nothing
 * fails when they do — the route simply works for everybody. Declared on the
 * route, the guard is visible in the route file next to the thing it guards,
 * and a route added without one is conspicuous rather than silent.
 *
 * 403 AND NOT 404
 *
 * Within a realm the existence of a page is not the secret; the ability to use
 * it is. Somebody who cannot approve leave still knows leave approval exists —
 * it is in the handbook — so pretending the URL does not exist would only make
 * them think the application is broken. Cross-realm is the opposite case, and
 * EnsureRealm handles it differently for exactly that reason.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class EnsurePermission
{
    public function __construct(protected Rbac $rbac)
    {
    }

    /**
     * Usage: `->middleware('permission:employees.create')`.
     *
     * Several keys mean ANY of them is enough. That is the useful default: a
     * page reachable by either of two roles is common, and a page needing two
     * permissions at once is rare enough to be worth writing out explicitly in
     * the controller where the reason for it can be stated.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        foreach ($permissions as $permission) {
            if ($this->rbac->can($request->user(), $permission)) {
                return $next($request);
            }
        }

        abort(403);
    }
}
