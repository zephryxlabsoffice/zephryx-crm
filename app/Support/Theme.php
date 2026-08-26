<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * Resolves and persists the light/dark preference (foundation spec §7).
 *
 * Pre-login the preference lives in a cookie. Once the Employees module lands,
 * an authenticated user's `theme_preference` column takes precedence — see
 * `forRequest()`, which is the single place that ordering is expressed.
 */
class Theme
{
    /**
     * Not read from config: bootstrap/app.php needs this name while the
     * middleware stack is being assembled, which is before config exists.
     */
    public const COOKIE = 'zx_theme';

    /**
     * The theme to render for this request. Never returns an unvalidated value:
     * anything unrecognised falls back to the configured default, so a tampered
     * cookie cannot inject an attribute value into the <html> tag.
     */
    public static function forRequest(Request $request): string
    {
        $user = $request->user();

        if ($user && self::isValid($user->theme_preference ?? null)) {
            return $user->theme_preference;
        }

        return self::sanitise($request->cookie(self::cookieName()));
    }

    /**
     * Coerce any input to a theme we are willing to render.
     */
    public static function sanitise(mixed $theme): string
    {
        return self::isValid($theme) ? $theme : self::default();
    }

    public static function isValid(mixed $theme): bool
    {
        return is_string($theme) && in_array($theme, self::available(), true);
    }

    /**
     * A long-lived cookie carrying the preference. It holds no secret and is
     * not used for any authorisation decision, but it is still HttpOnly and
     * SameSite=Lax so it behaves like every other cookie we set.
     */
    public static function cookie(string $theme): SymfonyCookie
    {
        return Cookie::make(
            name: self::cookieName(),
            value: self::sanitise($theme),
            minutes: (int) config('zephryx.theme.cookie_days') * 24 * 60,
            path: '/',
            domain: null,
            secure: null,
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }

    public static function default(): string
    {
        return (string) config('zephryx.theme.default', 'dark');
    }

    /** @return list<string> */
    public static function available(): array
    {
        return (array) config('zephryx.theme.available', ['dark', 'light']);
    }

    public static function cookieName(): string
    {
        return self::COOKIE;
    }
}
