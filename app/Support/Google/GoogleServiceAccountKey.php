<?php

namespace App\Support\Google;

use InvalidArgumentException;

/**
 * A parsed, validated service-account key — the JSON file Google Cloud hands
 * out when the key is created, pasted whole into the Admin Panel.
 *
 * Parsing lives here rather than in the controller so the same validation
 * covers every caller: the connect form, and later the test-connection call
 * that reads the stored key back out of App\Models\GoogleConnection.
 */
final class GoogleServiceAccountKey
{
    private function __construct(
        public readonly string $raw,
        public readonly string $clientEmail,
        public readonly string $privateKey,
        public readonly string $privateKeyId,
        public readonly string $tokenUri,
    ) {}

    /**
     * @throws InvalidArgumentException if this is not a usable service-account key
     */
    public static function parse(string $json): self
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('That is not valid JSON.');
        }

        if (($decoded['type'] ?? null) !== 'service_account') {
            throw new InvalidArgumentException('That key is not a service-account key — Google Cloud Console → '
                .'IAM & Admin → Service Accounts → Keys → Add key → JSON.');
        }

        foreach (['client_email', 'private_key', 'private_key_id'] as $field) {
            if (empty($decoded[$field])) {
                throw new InvalidArgumentException('The key is missing "'.$field.'".');
            }
        }

        return new self(
            raw: $json,
            clientEmail: $decoded['client_email'],
            privateKey: $decoded['private_key'],
            privateKeyId: $decoded['private_key_id'],
            tokenUri: $decoded['token_uri'] ?? 'https://oauth2.googleapis.com/token',
        );
    }

    /**
     * A hash of the key material, for telling one key from another on the
     * screen without ever showing the key itself. Changes on rotation, which
     * is the property the connection screen's "Connected" card needs.
     */
    public function fingerprint(): string
    {
        return implode(':', str_split(substr(hash('sha256', $this->privateKey), 0, 32), 4));
    }
}
