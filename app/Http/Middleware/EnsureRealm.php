<?php

namespace App\Http\Middleware;

use App\Support\Rbac\Rbac;
use App\Support\Realm;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Realm enforcement (foundation spec §3.1).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE FIRST OF TWO BARRIERS, AND THE ONE THAT RUNS BEFORE ANY DATA IS READ
 *
 * §3.1: "Every request re-checks the session's realm server-side, in
 * middleware, before any data is read. A client session hitting /employees is
 * refused. A staff session hitting /admin is refused."
 *
 * Applied to the route GROUP, never to individual routes, so a page added
 * tomorrow inherits the guard rather than needing somebody to remember it. That
 * is the entire reason routes/web.php is organised into three groups.
 *
 * It is independent of permissions. A client account holding `salary.view` — by
 * whatever mistake — still cannot open /salary, because this refuses the
 * request before the RBAC engine is ever consulted. Two barriers, and neither
 * relies on the other being right.
 * ═════════════════════════════════════════════════════════════════════════════
 *
 * WHY 404 AND NOT 403 FOR THE WRONG REALM
 *
 * A staff account asking for /admin gets "not found", not "forbidden".
 * "Forbidden" confirms the surface exists, which tells somebody probing that
 * there is an admin panel at that address and it is worth attacking. Within a
 * realm the answer is different — a 403 there says "you are in the right place
 * and lack a permission", which is useful and true.
 *
 * Not signed in at all is a redirect to the sign-in form, because that is a
 * state with an obvious remedy rather than a refusal.
 */
class EnsureRealm
{
    public function __construct(private Rbac $rbac)
    {
    }

    public function handle(Request $request, Closure $next, string $realm): Response
    {
        $user = $request->user();

        if ($user === null) {
            /*
             * Remembered so the sign-in form can send them back where they were
             * going. Only the path — a full URL from the request would let
             * somebody craft a link that bounces a signed-in user off-site.
             */
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('login');
        }

        /*
         * A session issued before the account was suspended must stop working
         * now, not when it expires (§4.2 step 4). Checked here rather than only
         * at sign-in for exactly that reason.
         */
        if (! $this->rbac->belongsToRealm($user, $realm)) {
            abort(404);
        }

        return $next($request);
    }

    /**
     * The middleware string for a realm — `realm:staff`.
     *
     * Used by routes/web.php so the three groups name their realm through the
     * Realm constants rather than repeating string literals that can drift.
     */
    public static function for(string $realm): string
    {
        return 'realm:'.$realm;
    }

    /**
     * @return list<string>
     */
    public static function realms(): array
    {
        return [Realm::STAFF, Realm::CLIENT, Realm::ADMIN];
    }
}
