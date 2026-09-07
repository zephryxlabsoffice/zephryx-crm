<?php

use App\Http\Middleware\EnforceSessionLifetime;
use App\Http\Middleware\EnsureRealm;
use App\Http\Middleware\RestoreRememberedSession;
use App\Http\Middleware\SecurityHeaders;
use App\Support\Shell;
use App\Support\Theme;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Applied to every response so new surfaces inherit the hardening
        // headers rather than opting into them (foundation spec §6).
        $middleware->append(SecurityHeaders::class);

        /*
         * Realm enforcement (§3.1), applied to route GROUPS in routes/web.php
         * rather than to individual routes — a page added tomorrow inherits the
         * guard instead of needing somebody to remember it.
         */
        $middleware->alias(['realm' => EnsureRealm::class]);

        /*
         * §4.4 (absolute and idle session limits) and §4.5 (remember-me), on
         * every web request.
         *
         * Appended to the group rather than aliased, because both have to run
         * whether or not a route opted in: a session that has outlived its
         * ceiling must expire on the sign-in page too, and a remember-me cookie
         * has to be resolved before anything asks who the user is.
         *
         * Order matters. RestoreRememberedSession runs first — it may create
         * the session that EnforceSessionLifetime then measures.
         */
        $middleware->web(append: [
            RestoreRememberedSession::class,
            EnforceSessionLifetime::class,
        ]);

        // The theme cookie carries a display preference, not a secret, and is
        // never an input to an authorisation decision — App\Support\Theme
        // re-validates it against the allow-list on every read. Leaving it in
        // clear keeps it cheap and inspectable; everything else stays encrypted.
        $middleware->encryptCookies(except: [
            Theme::COOKIE,
            Shell::SIDEBAR_COOKIE,
            Shell::DENSITY_COOKIE,
        ]);

        // Login throttling and the audit log are keyed on the client IP (§4.2,
        // §6), so X-Forwarded-For is only honoured from proxies we name. Left
        // empty, the connecting address is used and cannot be spoofed.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: explode(',', $proxies));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
