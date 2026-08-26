<?php

namespace App\Providers;

use App\Http\View\Composers\ShellComposer;
use App\Support\Navigation\NavigationGate;
use App\Support\Navigation\PermissiveGate;
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
         * Sidebar permission checks.
         *
         * PermissiveGate allows everything and throws in production. Swap this
         * binding for the RBAC engine (foundation spec §5) when it lands —
         * nothing in the views or config/navigation.php needs to change.
         */
        $this->app->bind(NavigationGate::class, PermissiveGate::class);
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
