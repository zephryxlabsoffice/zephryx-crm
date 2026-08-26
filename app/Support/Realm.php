<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The three isolated realms of the application (foundation spec §3).
 *
 * This class answers one question only — "where does this account belong?" — and
 * is deliberately free of authorisation logic. Realm *enforcement* happens in
 * middleware on every request (§3.1); a redirect is a convenience, never a
 * security boundary.
 */
class Realm
{
    public const STAFF = 'staff';
    public const CLIENT = 'client';
    public const ADMIN = 'admin';

    /**
     * Landing path for an account after sign-in.
     *
     * Paths are literal rather than named routes because the realm dashboards
     * are built in later phases; an unknown account type falls back to the
     * staff dashboard rather than leaking an error page.
     */
    public static function dashboardFor(?Authenticatable $user): string
    {
        return match ($user?->account_type ?? null) {
            self::ADMIN  => '/admin/dashboard',
            self::CLIENT => '/client/dashboard',
            default      => '/dashboard',
        };
    }
}
