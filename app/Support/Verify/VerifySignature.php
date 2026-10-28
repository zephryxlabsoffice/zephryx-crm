<?php

namespace App\Support\Verify;

use Illuminate\Support\Carbon;

/**
 * The `signature` on a verify link (2026-10-29).
 *
 * Two kinds, both keyed off APP_KEY:
 *
 * - STATIC, for the QR printed on an ID card: /verify/ZEPH261001?signature=…
 *   It never expires, because the card does not change. It is tied to the
 *   Employee ID alone, not to the host or http/https, so a card keeps working
 *   whatever happens to the address in front of it.
 * - DYNAMIC, for a link the search box hands out: …?expires=…&signature=…,
 *   good for a few minutes. A searched link cannot be bookmarked into a QR.
 *
 * Without a valid signature the page says the link is not genuine: a card
 * with a hand-typed URL is exactly what this site exists to catch.
 *
 * APP_KEY must never change on the server - that would void every printed
 * card (it already must not, for the stored Google key).
 */
class VerifySignature
{
    protected const LENGTH = 32;

    public static function forCard(string $employeeId): string
    {
        return self::sign('card|'.self::normalise($employeeId));
    }

    /**
     * @return array{expires: int, signature: string}
     */
    public static function forSearch(string $employeeId, ?int $minutes = null): array
    {
        $expires = Carbon::now()->addMinutes($minutes ?? (int) config('verify.search_link_minutes', 15))->getTimestamp();

        return [
            'expires' => $expires,
            'signature' => self::sign('search|'.self::normalise($employeeId).'|'.$expires),
        ];
    }

    /**
     * Which kind a request carries: 'qr', 'search', or null when it is not
     * genuine (missing, altered, or a search link that has expired).
     */
    public static function check(string $employeeId, ?string $signature, mixed $expires): ?string
    {
        if (! is_string($signature) || $signature === '') {
            return null;
        }

        if ($expires === null || $expires === '') {
            return hash_equals(self::forCard($employeeId), $signature) ? 'qr' : null;
        }

        if (! ctype_digit((string) $expires) || (int) $expires < Carbon::now()->getTimestamp()) {
            return null;
        }

        $expected = self::sign('search|'.self::normalise($employeeId).'|'.$expires);

        return hash_equals($expected, $signature) ? 'search' : null;
    }

    protected static function sign(string $payload): string
    {
        return substr(hash_hmac('sha256', $payload, 'verify-id-card|'.config('app.key')), 0, self::LENGTH);
    }

    protected static function normalise(string $employeeId): string
    {
        return strtoupper(trim($employeeId));
    }
}
