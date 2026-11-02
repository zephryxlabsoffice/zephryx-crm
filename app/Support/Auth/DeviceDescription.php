<?php

namespace App\Support\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Locale;
use Throwable;

/**
 * Turns a request into the words shown on My Devices and in the new-device email.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE NAME IS WHAT THE BROWSER SAYS, NOT WHAT THE MACHINE IS CALLED
 *
 * No website can read a computer's own name, so "Chrome on Windows" is as far
 * as this goes. Android phones are the exception, some still send their model.
 *
 * THE LOCATION IS CLOUDFLARE'S IF IT SENT ONE, OTHERWISE AN IP LOOKUP
 *
 * Cloudflare's headers cost nothing, but count only from a proxy named in
 * TRUSTED_PROXIES, as anybody can send them directly. Failing that, the address
 * is sent to db-ip.com (owner, 2026-11-02): free, no account, 1,000 lookups a
 * day. Each address is looked up once and remembered, so this code runs on every
 * page without slowing them, and a failed lookup just reads "Location unknown".
 *
 * Either way it is an estimate from the IP address, and a mobile network can
 * place somebody in another city.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class DeviceDescription
{
    public const UNKNOWN_NAME = 'Unknown device';

    /** Cloudflare's codes for "no country": unknown, and Tor. */
    protected const NOT_A_COUNTRY = ['XX', 'T1'];

    protected const LOOKUP_URL = 'https://api.db-ip.com/v2/free/';

    /** An address rarely moves; a failure is worth trying again soon. */
    protected const LOOKUP_REMEMBER_DAYS = 30;

    protected const LOOKUP_RETRY_HOURS = 1;

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
        return self::fromCloudflare($request) ?? self::lookUp($request->ip());
    }

    protected static function lookUp(?string $ip): ?string
    {
        // Private and reserved addresses are on no map, and include this PC's.
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        $key = 'device-location:'.$ip;
        $remembered = Cache::get($key);

        if ($remembered !== null) {
            return $remembered === '' ? null : $remembered;
        }

        $location = self::askLookupService($ip);

        Cache::put(
            $key,
            $location ?? '',
            $location === null ? now()->addHours(self::LOOKUP_RETRY_HOURS) : now()->addDays(self::LOOKUP_REMEMBER_DAYS),
        );

        return $location;
    }

    /** Null on any failure. A location is never worth breaking a page for. */
    protected static function askLookupService(string $ip): ?string
    {
        try {
            $found = Http::connectTimeout(2)->timeout(3)->acceptJson()->get(self::LOOKUP_URL.$ip)->json();
        } catch (Throwable) {
            return null;
        }

        return self::joinPlace([
            (string) ($found['city'] ?? ''),
            (string) ($found['stateProv'] ?? ''),
            (string) ($found['countryName'] ?? ''),
        ]);
    }

    protected static function fromCloudflare(Request $request): ?string
    {
        if (! $request->isFromTrustedProxy()) {
            return null;
        }

        $country = strtoupper(self::header($request, 'CF-IPCountry'));

        return self::joinPlace([
            self::header($request, 'CF-IPCity'),
            self::header($request, 'CF-Region'),
            in_array($country, self::NOT_A_COUNTRY, true) ? '' : self::countryName($country),
        ]);
    }

    /**
     * @param  list<string>  $parts
     */
    protected static function joinPlace(array $parts): ?string
    {
        $parts = array_filter($parts, fn (string $part) => $part !== '');

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
