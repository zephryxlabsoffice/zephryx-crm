<?php

namespace App\Support\SupportMail;

use App\Models\SupportMailbox;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Read-only Gmail for the support mailbox, through a one-time Google sign-in
 * (OAuth, owner, 2026-11-06). Hand-rolled REST, for the same reason as
 * App\Support\Google\GoogleAuth: the official SDK cannot sit on this Guzzle.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * WHY A SIGN-IN AND NOT THE SERVICE ACCOUNT
 *
 * The service account could be given Gmail through domain-wide delegation, but
 * that lets it read EVERY mailbox in the company. A sign-in as contact@ gives a
 * token for that one mailbox, and only to read it. Replies go out through the
 * SMTP relay like all other mail.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class GmailClient
{
    public const SCOPE = 'https://www.googleapis.com/auth/gmail.readonly';

    protected const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    protected const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    protected const API = 'https://gmail.googleapis.com/gmail/v1/users/me';

    protected ?string $accessToken = null;

    public function __construct(protected SupportMailbox $mailbox) {}

    /**
     * Where to send the admin to sign in.
     */
    public static function authorizationUrl(SupportMailbox $mailbox, string $redirectUri, string $state): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => $mailbox->client_id,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            // A refresh token, so the cron can read without anybody signed in.
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'false',
            'state' => $state,
        ] + (filled($mailbox->account_email) ? ['login_hint' => $mailbox->account_email] : []));
    }

    /**
     * Exchange the code Google sent back for a refresh token, and say whose it is.
     *
     * @return array{refresh_token: string, email: string}
     */
    public static function exchange(SupportMailbox $mailbox, string $code, string $redirectUri): array
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => $mailbox->client_id,
            'client_secret' => $mailbox->client_secret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if ($response->failed() || ! is_string($response->json('refresh_token'))) {
            throw new RuntimeException('Google refused the sign-in: '.($response->json('error_description') ?? $response->json('error') ?? $response->body()));
        }

        $profile = Http::withToken((string) $response->json('access_token'))->get(self::API.'/profile');

        if ($profile->failed()) {
            throw new RuntimeException('Signed in, but the mailbox could not be read: '.$profile->body());
        }

        return [
            'refresh_token' => (string) $response->json('refresh_token'),
            'email' => (string) $profile->json('emailAddress'),
        ];
    }

    /**
     * Message ids matching a Gmail search, newest first.
     *
     * @return list<array{id: string, threadId: string}>
     */
    public function search(string $query, int $max = 50): array
    {
        $response = $this->get('/messages', ['q' => $query, 'maxResults' => $max]);

        return array_values((array) ($response['messages'] ?? []));
    }

    /**
     * @return array<string, mixed>
     */
    public function message(string $id): array
    {
        return $this->get('/messages/'.$id, ['format' => 'full']);
    }

    public function attachment(string $messageId, string $attachmentId): string
    {
        $data = $this->get('/messages/'.$messageId.'/attachments/'.$attachmentId);

        return self::decode((string) ($data['data'] ?? ''));
    }

    public static function decode(string $base64url): string
    {
        return (string) base64_decode(strtr($base64url, '-_', '+/'), false);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function get(string $path, array $query = []): array
    {
        $response = Http::withToken($this->token())->timeout(30)->get(self::API.$path, $query);

        if ($response->failed()) {
            throw new RuntimeException('Gmail answered '.$response->status().': '.mb_substr($response->body(), 0, 300));
        }

        return (array) $response->json();
    }

    protected function token(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => $this->mailbox->client_id,
            'client_secret' => $this->mailbox->client_secret,
            'refresh_token' => $this->mailbox->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed() || ! is_string($response->json('access_token'))) {
            throw new RuntimeException('The support mailbox sign-in no longer works ('
                .($response->json('error_description') ?? $response->json('error') ?? $response->status())
                .'). Connect it again in Admin > Integrations.');
        }

        return $this->accessToken = $response->json('access_token');
    }
}
