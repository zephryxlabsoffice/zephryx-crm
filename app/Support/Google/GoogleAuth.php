<?php

namespace App\Support\Google;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * A Google access token for a service account, built by hand.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * WHY THIS IS NOT `google/apiclient`
 *
 * Every version of the official SDK pins `guzzlehttp/guzzle` to `^7.4.5`. This
 * application is on Guzzle 8, required by `laravel/framework` 13's own HTTP
 * client, and there is no version of the SDK that accepts it — installing it
 * means downgrading Guzzle under the framework itself. What this application
 * actually needs from Google is small: one token exchange (the JWT-bearer
 * grant, RFC 7523) and a handful of Drive/Calendar REST calls. Hand-rolling
 * that costs less than vendoring an SDK built for the whole Google API surface
 * plus a Guzzle downgrade underneath it.
 *
 * THE JWT IS SIGNED WITH `openssl_sign`, NOT A JWT LIBRARY
 *
 * RS256 over a two-part payload is a few lines with the `openssl` extension,
 * which this application already requires (§11 — passwords, at-rest
 * encryption). Pulling in a JWT package for one call site would be the same
 * mistake as the SDK, at a smaller scale.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class GoogleAuth
{
    public function __construct(protected GoogleServiceAccountKey $key) {}

    /**
     * Exchange the service account's key for a bearer token good for the
     * given scopes.
     *
     * `$impersonate` is the Calendar case only — a mailbox this key has
     * domain-wide delegation to act as. Drive needs no impersonation at all:
     * the service account is a member of the Shared Drive in its own right
     * (plan doc, "Note the asymmetry").
     *
     * @param  list<string>  $scopes
     *
     * @throws RuntimeException if Google refuses the exchange
     */
    public function token(array $scopes, ?string $impersonate = null): string
    {
        $now = time();

        $claims = [
            'iss' => $this->key->clientEmail,
            'scope' => implode(' ', $scopes),
            'aud' => $this->key->tokenUri,
            'iat' => $now,
            // An hour, the maximum the token endpoint honours. Nothing here
            // holds one longer than a single request needs it.
            'exp' => $now + 3600,
        ];

        if ($impersonate !== null) {
            $claims['sub'] = $impersonate;
        }

        $response = Http::asForm()->post($this->key->tokenUri, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $this->sign($claims),
        ]);

        if ($response->failed() || ! is_string($response->json('access_token'))) {
            throw new RuntimeException(
                'Google refused the connection: '.($response->json('error_description') ?? $response->body())
            );
        }

        return $response->json('access_token');
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    protected function sign(array $claims): string
    {
        $signingInput = $this->base64UrlEncode(['alg' => 'RS256', 'typ' => 'JWT'])
            .'.'.$this->base64UrlEncode($claims);

        $signature = '';
        $signed = openssl_sign($signingInput, $signature, $this->key->privateKey, OPENSSL_ALGO_SHA256);

        if (! $signed) {
            throw new RuntimeException('Could not sign a request with this key — it may be malformed.');
        }

        return $signingInput.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function base64UrlEncode(array $data): string
    {
        return rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
    }
}
