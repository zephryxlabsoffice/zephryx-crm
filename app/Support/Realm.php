<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

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

    /**
     * Which realm's shell a request is rendering.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THIS IS FOR DRAWING, NOT FOR DECIDING (foundation spec §3.1)
     *
     * It reads the URL, and a URL is something anybody can type. That is fine
     * for its actual job — picking which sidebar to render — and catastrophic
     * for anything else, so nothing may use it to authorise a read.
     *
     * The realm that MATTERS comes from the session cookie (`zx_staff`,
     * `zx_client`, `zx_admin`) and is re-checked in middleware on every request
     * before any data is read. When that middleware lands, a client session on
     * `/employees` is refused whatever this method says about the path.
     *
     * Keeping the two apart is deliberate. A single "current realm" helper used
     * for both drawing and deciding is one refactor away from a path prefix
     * becoming an authorisation check.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public static function forRequest(Request $request): string
    {
        return $request->is('client', 'client/*') ? self::CLIENT
            : ($request->is('admin', 'admin/*') ? self::ADMIN : self::STAFF);
    }
}
