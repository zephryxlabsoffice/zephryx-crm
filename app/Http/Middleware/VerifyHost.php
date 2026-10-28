<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * verify.zephryxlabs.in is served by this application but is not the CRM
 * (2026-10-29). On that host:
 *
 * - Only the verify site's own pages and the sign-in pages its admin needs
 *   answer. Everything else in the CRM is a 404 there, so the CRM is never
 *   reachable under the verify address.
 * - The session cookie is its own: the live server pins SESSION_DOMAIN to
 *   crm.zephryxlabs.in, which a browser would refuse to store for another
 *   host, so without this nobody could sign in to the verify dashboard.
 *
 * Global, and before the session starts, which is why it is not a route
 * middleware.
 */
class VerifyHost
{
    protected const OPEN = [
        '/', 'search', 'verify/*', 'registration/*',
        'admin', 'admin/qr/*', 'admin/settings', 'admin/registrations', 'admin/registrations/*',
        'login', 'login/*', 'logout', 'forgot-password', 'reset-password', 'reset-password/*',
        'account/on-hold', 'account/inactive', 'theme', 'up',
    ];

    public static function is(Request $request): bool
    {
        return strcasecmp($request->getHost(), (string) config('verify.domain')) === 0;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::is($request)) {
            return $next($request);
        }

        config([
            'session.domain' => null,
            'session.cookie' => config('session.cookie').'_verify',
        ]);

        // A plain 404: the session has not started, and the styled error page
        // needs one for its theme form.
        if (! $request->is(...self::OPEN)) {
            return response('Not Found', 404);
        }

        return $next($request);
    }
}
