<?php

namespace App\Support\Navigation;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Decides whether a viewer holds a permission key.
 *
 * This exists as a contract so the shell can be built before the RBAC engine
 * (foundation spec §5). When that engine lands, bind the real implementation
 * in AppServiceProvider and nothing in the views or the nav definition changes.
 */
interface NavigationGate
{
    public function allows(?Authenticatable $user, string $permission): bool;
}
