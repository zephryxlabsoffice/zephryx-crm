<?php

namespace App\Http\Middleware;

use App\Support\Realm;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The verify dashboard is the CRM owner's Admin account and nobody else's
 * (owner, 2026-10-29): the same email, password and emailed code as the
 * Admin Panel, signed in on the verify host.
 */
class VerifyAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest('/login');
        }

        abort_unless($user->account_type === Realm::ADMIN && $user->isActive(), 404);

        return $next($request);
    }
}
