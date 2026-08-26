<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * Per-viewer preferences for the app shell.
 *
 * Both are resolved server-side and rendered onto <html>, for the same reason
 * the theme is (spec §7): reading them in JavaScript after first paint means
 * every navigation shows the default state for a frame and then jumps.
 *
 * Like the theme cookie these hold no secret and gate nothing, so they are
 * validated on read rather than trusted, and excluded from cookie encryption.
 */
class Shell
{
    public const SIDEBAR_COOKIE = 'zx_sidebar';
    public const DENSITY_COOKIE = 'zx_density';

    /** @var list<string> */
    public const SIDEBAR_STATES = ['expanded', 'collapsed'];

    /** @var list<string> */
    public const DENSITIES = ['comfortable', 'compact'];

    public static function sidebarState(Request $request): string
    {
        $value = $request->cookie(self::SIDEBAR_COOKIE);

        return in_array($value, self::SIDEBAR_STATES, true) ? $value : 'expanded';
    }

    public static function density(Request $request): string
    {
        $value = $request->cookie(self::DENSITY_COOKIE);

        return in_array($value, self::DENSITIES, true) ? $value : 'comfortable';
    }

    public static function sidebarCookie(string $state): SymfonyCookie
    {
        return self::cookie(self::SIDEBAR_COOKIE, $state, self::SIDEBAR_STATES, 'expanded');
    }

    public static function densityCookie(string $density): SymfonyCookie
    {
        return self::cookie(self::DENSITY_COOKIE, $density, self::DENSITIES, 'comfortable');
    }

    /**
     * Initials for the avatar. Two letters at most: three is unreadable at
     * 40px, and a single letter makes everyone called S look identical.
     */
    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return '—';
        }

        $first = mb_substr($words[0], 0, 1);
        $last = count($words) > 1 ? mb_substr($words[count($words) - 1], 0, 1) : '';

        return mb_strtoupper($first.$last);
    }

    /**
     * @param  list<string>  $allowed
     */
    protected static function cookie(string $name, string $value, array $allowed, string $fallback): SymfonyCookie
    {
        return Cookie::make(
            name: $name,
            value: in_array($value, $allowed, true) ? $value : $fallback,
            minutes: 365 * 24 * 60,
            path: '/',
            domain: null,
            secure: null,
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }
}
