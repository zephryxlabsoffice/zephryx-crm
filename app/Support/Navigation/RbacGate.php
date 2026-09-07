<?php

namespace App\Support\Navigation;

use App\Support\Rbac\Rbac;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The real navigation gate, backed by the RBAC engine (foundation spec §5).
 *
 * This is the class PermissiveGate was standing in for. Every sidebar entry,
 * every dashboard widget and every client page has been filtering through the
 * NavigationGate contract since the shell was built precisely so that this
 * swap would be a one-line binding change and nothing else — and it was.
 *
 * It is a thin adapter rather than a second implementation. The union across
 * roles and the implicit Employee base are resolved in one place, App\Support\
 * Rbac\Rbac, so a page cannot be shown by one set of rules and its route
 * guarded by another.
 */
class RbacGate implements NavigationGate
{
    public function __construct(private Rbac $rbac)
    {
    }

    public function allows(?Authenticatable $user, string $permission): bool
    {
        return $this->rbac->can($user, $permission);
    }
}
