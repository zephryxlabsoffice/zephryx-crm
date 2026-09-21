<?php

namespace App\Providers;

use App\Http\View\Composers\ShellComposer;
use App\Support\Admin\CompanySettings;
use App\Support\Meetings\GoogleMeetProvider;
use App\Support\Meetings\MeetingProvider;
use App\Support\Navigation\NavigationGate;
use App\Support\Navigation\RbacGate;
use App\Support\Rbac\Rbac;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
         * ─────────────────────────────────────────────────────────────────────
         * THE SWAP THIS BINDING WAS ALWAYS FOR (2026-09-07)
         *
         * This used to bind PermissiveGate, which allowed everything and threw
         * in production. The comment here promised that when the RBAC engine
         * (§5) landed, "nothing in the views or config/navigation.php needs to
         * change".
         *
         * That held. Three sidebars, fourteen dashboard widgets and every
         * client page have been filtering through the NavigationGate contract
         * since the shell was built, and the engine arriving changed this line
         * and nothing else.
         * ─────────────────────────────────────────────────────────────────────
         */
        $this->app->bind(NavigationGate::class, RbacGate::class);

        // One instance per request, so the permission union for a user is
        // resolved once however many times the page asks.
        $this->app->singleton(Rbac::class);

        /*
         * ─────────────────────────────────────────────────────────────────────
         * THE CONFERENCING SERVICE
         *
         * GoogleMeetProvider (built 2026-09-21) calls the real Calendar API,
         * reading the credential from App\Models\GoogleConnection at the
         * point of use rather than from this binding or from config — see
         * its own header comment. Before it was built, this bound to a
         * version where every method raised, on purpose: a stub returning a
         * plausible event id and a fabricated meet.google.com link would
         * have made the pages look finished and put somebody in a room that
         * does not exist.
         *
         * That is still exactly how an unconnected Google, or a real Google
         * outage, behaves now — MeetingController treats any throw from this
         * binding the same way regardless of cause: the meeting stays
         * requested and the page says the invite did not go out.
         * ─────────────────────────────────────────────────────────────────────
         */
        $this->app->bind(MeetingProvider::class, GoogleMeetProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * ─────────────────────────────────────────────────────────────────────
         * THE STORED SETTINGS, OVER THE SHIPPED DEFAULTS
         *
         * First, and before anything reads a policy value. Everything
         * downstream keeps calling `config()` and knows nothing about the
         * table — which is the only shape this could take safely, because
         * twenty-odd call sites read these values and a version where each had
         * to remember to ask a settings service is a version where one forgot.
         *
         * Silent when there is no table: this runs during `migrate` and on a
         * fresh checkout. See App\Support\Admin\CompanySettings.
         * ─────────────────────────────────────────────────────────────────────
         */
        CompanySettings::apply();

        // Bound to the layout, so no module has to remember to hand the shell
        // its own navigation, notifications or viewer details.
        View::composer('layouts.app', ShellComposer::class);
    }
}
