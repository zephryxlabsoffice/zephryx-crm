<?php

namespace App\Support;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Browser push, with no text in the push itself.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE PUSH ONLY SAYS "LOOK"
 *
 * What is sent to Google, Mozilla, Microsoft or Apple is empty. When it arrives,
 * public/sw.js asks this site for the signed-in person's latest notification and
 * shows that. So no announcement passes through anybody else's servers, the
 * message encryption a payload would need (and the library for it) is not
 * required, and a device whose session has ended shows only a generic line.
 *
 * ONLY KNOWN PUSH SERVICES
 *
 * The endpoint comes from the browser, and the server then makes a request to it.
 * Without the allow-list below, anybody signed in could point this site at an
 * address of their choosing, including ones inside the hosting network.
 *
 * THE KEYS MAKE THEMSELVES
 *
 * Generated the first time they are needed and kept in `company_settings`, with
 * the private half encrypted, so there is nothing to set up on the server. Changing
 * them would cut off every device that has already subscribed.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class WebPush
{
    public const KEYS_SETTING = 'push.vapid';

    /** @var list<string> Exact hosts, or suffixes when they begin with a dot. */
    protected const PUSH_SERVICES = [
        'fcm.googleapis.com',
        '.push.services.mozilla.com',
        '.notify.windows.com',
        '.push.apple.com',
    ];

    /** How long a push service keeps trying a device that is switched off. */
    protected const TIME_TO_LIVE_SECONDS = 86400;

    public static function isAllowedEndpoint(string $endpoint): bool
    {
        $parts = parse_url($endpoint);

        if (($parts['scheme'] ?? null) !== 'https' || isset($parts['user']) || isset($parts['port'])) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');

        foreach (self::PUSH_SERVICES as $service) {
            if ($service[0] === '.' ? str_ends_with($host, $service) : $host === $service) {
                return true;
            }
        }

        return false;
    }

    /**
     * The key a browser needs in order to subscribe.
     */
    public function publicKey(): string
    {
        return $this->keys()['public'];
    }

    /**
     * Wake every device these people have turned on. Returns how many accepted.
     *
     * A failure is logged and skipped: one dead device must not stop the rest.
     *
     * @param  list<int>  $userIds
     */
    public function sendTo(array $userIds): int
    {
        if ($userIds === []) {
            return 0;
        }

        $accepted = 0;

        PushSubscription::query()->whereIn('user_id', $userIds)->each(function (PushSubscription $subscription) use (&$accepted) {
            try {
                $response = Http::timeout(5)
                    ->withHeaders([
                        'Authorization' => $this->authorizationFor($subscription->endpoint),
                        'TTL' => (string) self::TIME_TO_LIVE_SECONDS,
                        'Urgency' => 'high',
                    ])
                    ->withBody('', 'application/octet-stream')
                    ->post($subscription->endpoint);

                if ($response->successful()) {
                    $accepted++;
                } elseif (in_array($response->status(), [404, 410], true)) {
                    // The push service says this device has gone for good.
                    $subscription->delete();
                }
            } catch (Throwable $e) {
                report($e);
            }
        });

        return $accepted;
    }

    /**
     * The signed token that proves to the push service who is asking (RFC 8292).
     */
    public function authorizationFor(string $endpoint): string
    {
        $parts = parse_url($endpoint);

        $unsigned = $this->base64Url((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']))
            .'.'.$this->base64Url((string) json_encode([
                'aud' => $parts['scheme'].'://'.$parts['host'],
                'exp' => now()->addHours(12)->timestamp,
                'sub' => 'mailto:'.config('mail.from.address'),
            ]));

        $keys = $this->keys();

        if (! openssl_sign($unsigned, $derSignature, $keys['private'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign the push token.');
        }

        return 'vapid t='.$unsigned.'.'.$this->base64Url($this->rawSignature($derSignature)).', k='.$keys['public'];
    }

    /**
     * @return array{public: string, private: string}
     */
    protected function keys(): array
    {
        $stored = DB::table('company_settings')->where('key', self::KEYS_SETTING)->value('value');

        if ($stored === null) {
            // Two first requests at once: the loser's insert is ignored, and both read the winner's.
            $now = now();

            DB::table('company_settings')->insertOrIgnore([
                'key' => self::KEYS_SETTING,
                'value' => json_encode($this->generateKeys()),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $stored = DB::table('company_settings')->where('key', self::KEYS_SETTING)->value('value');
        }

        $keys = json_decode((string) $stored, true);

        return ['public' => $keys['public'], 'private' => Crypt::decryptString($keys['private'])];
    }

    /**
     * @return array{public: string, private: string}
     */
    protected function generateKeys(): array
    {
        /*
         * An empty config file, because PHP looks for an openssl.cnf that many
         * installs do not have, and curve keys need nothing from it.
         */
        $config = (string) tempnam(sys_get_temp_dir(), 'ossl');
        file_put_contents($config, "\n");

        try {
            $key = openssl_pkey_new([
                'curve_name' => 'prime256v1',
                'private_key_type' => OPENSSL_KEYTYPE_EC,
                'config' => $config,
            ]);

            if ($key === false || ! openssl_pkey_export($key, $privatePem, null, ['config' => $config])) {
                throw new RuntimeException('Could not generate the push keys.');
            }
        } finally {
            @unlink($config);
        }

        $curve = openssl_pkey_get_details($key)['ec'];

        return [
            'public' => $this->base64Url("\x04".$this->padded($curve['x']).$this->padded($curve['y'])),
            'private' => Crypt::encryptString($privatePem),
        ];
    }

    /**
     * OpenSSL signs in DER, and the token wants the two numbers side by side.
     */
    protected function rawSignature(string $der): string
    {
        $offset = 3;
        $rLength = ord($der[$offset]);
        $r = substr($der, $offset + 1, $rLength);

        $offset += $rLength + 2;
        $sLength = ord($der[$offset]);
        $s = substr($der, $offset + 1, $sLength);

        return $this->padded($r).$this->padded($s);
    }

    /**
     * Exactly 32 bytes: DER adds a leading zero to some numbers and drops others'.
     */
    protected function padded(string $number): string
    {
        return str_pad(ltrim($number, "\x00"), 32, "\x00", STR_PAD_LEFT);
    }

    protected function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
