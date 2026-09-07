<?php

namespace App\Providers;

use App\Http\View\Composers\ShellComposer;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Bound to the layout, so no module has to remember to hand the shell
        // its own navigation, notifications or viewer details.
        View::composer('layouts.app', ShellComposer::class);
    }
}
