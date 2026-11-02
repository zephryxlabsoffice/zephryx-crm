<?php

namespace App\Support\Auth;

use Illuminate\Http\Request;
use Locale;

/**
 * Turns a request into the words shown on My Devices and in the new-device email.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE NAME IS WHAT THE BROWSER SAYS, NOT WHAT THE MACHINE IS CALLED
 *
 * No website can read a computer's own name, so "Chrome on Windows" is as far
 * as this goes. Android phones are the exception, some still send their model.
 *
 * THE LOCATION COMES FROM CLOUDFLARE, NOT A LOOKUP SERVICE
 *
 * The site sits behind Cloudflare, which already knows where each visitor is.
 * That means no employee's address goes to a third party and sign-in does not
 * wait on anybody else's API. Country is always sent; city and region need
 * "Add visitor location headers" on under Rules > Settings > Managed Transforms.
 *
 * The headers count only from a proxy named in TRUSTED_PROXIES, as anybody can
 * send them directly. It is an estimate from the IP address, and a mobile
 * network can place somebody in another city.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class DeviceDescription
{
    public const UNKNOWN_NAME = 'Unknown device';

    /** Cloudflare's codes for "no country": unknown, and Tor. */
    protected const NOT_A_COUNTRY = ['XX', 'T1'];

    public static function name(?string $userAgent): string
    {
        $agent = (string) $userAgent;

        // Order matters: Edge and Opera also say Chrome, and Chrome also says Safari.
        $browser = match (true) {
            str_contains($agent, 'Edg') => 'Edge',
            str_contains($agent, 'OPR/') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'SamsungBrowser/') => 'Samsung Internet',
            str_contains($agent, 'Firefox/') || str_contains($agent, 'FxiOS/') => 'Firefox',
            str_contains($agent, 'Chrome/') || str_contains($agent, 'CriOS/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => null,
        };

        // Phones first: an iPhone also says "like Mac OS X", Android also says Linux.
        $system = match (true) {
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Android') => self::androidName($agent),
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'CrOS') => 'Chromebook',
            str_contains($agent, 'Macintosh') => 'Mac',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        return match (true) {
            $browser !== null && $system !== null => $browser.' on '.$system,
            $browser !== null || $system !== null => (string) ($browser ?? $system),
            default => self::UNKNOWN_NAME,
        };
    }

    public static function location(Request $request): ?string
    {
        if (! $request->isFromTrustedProxy()) {
            return null;
        }

        $country = strtoupper(self::header($request, 'CF-IPCountry'));

        $parts = array_filter([
            self::header($request, 'CF-IPCity'),
            self::header($request, 'CF-Region'),
            in_array($country, self::NOT_A_COUNTRY, true) ? '' : self::countryName($country),
        ], fn (string $part) => $part !== '');

        return $parts === [] ? null : mb_substr(implode(', ', array_unique($parts)), 0, 190);
    }

    /**
     * "Android (SM-S918B)" where the model is sent. Chrome now sends "K" in its
     * place to hide it, which is not worth showing.
     */
    protected static function androidName(string $agent): string
    {
        if (preg_match('/Android [\d.]+; ([^;)]+?)(?: Build\/[^;)]*)?[;)]/', $agent, $match) && trim($match[1]) !== 'K') {
            return 'Android ('.mb_substr(trim($match[1]), 0, 60).')';
        }

        return 'Android';
    }

    protected static function header(Request $request, string $name): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]/', '', (string) $request->header($name)));
    }

    /** The code itself where the server has no intl extension. */
    protected static function countryName(string $code): string
    {
        if ($code === '' || ! class_exists(Locale::class)) {
            return $code;
        }

        return Locale::getDisplayRegion('-'.$code, 'en') ?: $code;
    }
}
