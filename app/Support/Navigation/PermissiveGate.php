<?php

namespace App\Support\Navigation;

use Illuminate\Contracts\Auth\Authenticatable;use RuntimeException;

/**
 * A placeholder gate that allows everything.
 *
 * The shell is being built before the RBAC engine exists (foundation spec §5),
 * and a nav filtered against nothing would render empty — which makes the
 * shell impossible to review. So during development every entry is allowed.
 *
 * It refuses to run in production. This is the whole point of the class: the
 * failure mode of forgetting to swap it is "every user sees every module",
 * which is exactly the leak §5 exists to prevent. Better a loud error at boot
 * than a silent one in front of staff.
 */
class PermissiveGate implements NavigationGate
{
    public function allows(?Authenticatable $user, string $permission): bool
    {
        if (app()->environment('production')) {
            throw new RuntimeException(
                'PermissiveGate is a development placeholder and must not run in production. '
                .'Bind a real App\Support\Navigation\NavigationGate backed by the RBAC engine '
                .'(foundation spec §5) before deploying.'
            );
        }

        return true;
    }
}
