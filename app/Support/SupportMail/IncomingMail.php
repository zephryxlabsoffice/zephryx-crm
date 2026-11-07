<?php

namespace App\Support\SupportMail;

use Illuminate\Support\Str;

/**
 * One message from the support mailbox, read out of Gmail's structure.
 */
class IncomingMail
{
    /**
     * @param  array<string, string>  $headers  lower-case names
     * @param  list<string>  $labels
     * @param  list<array{filename: string, mimeType: string, attachmentId: ?string, data: ?string, size: int}>  $attachments
     */
    public function __construct(
        public string $id,
        public string $threadId,
        public array $headers,
        public array $labels,
        public string $fromEmail,
        public string $fromName,
        public string $subject,
        public string $body,
        public array $attachments,
    ) {}

    /**
     * @param  array<string, mixed>  $message  Gmail's format=full
     */
    public static function fromGmail(array $message): self
    {
        $payload = (array) ($message['payload'] ?? []);
        $headers = [];

        foreach ((array) ($payload['headers'] ?? []) as $header) {
            $headers[strtolower((string) ($header['name'] ?? ''))] = (string) ($header['value'] ?? '');
        }

        [$email, $name] = self::address($headers['from'] ?? '');

        $plain = null;
        $html = null;
        $attachments = [];
        self::walk($payload, $plain, $html, $attachments);

        $body = $plain ?? ($html !== null ? self::fromHtml($html) : '');

        return new self(
            id: (string) ($message['id'] ?? ''),
            threadId: (string) ($message['threadId'] ?? ''),
            headers: $headers,
            labels: array_values((array) ($message['labelIds'] ?? [])),
            fromEmail: $email,
            fromName: $name !== '' ? $name : $email,
            subject: trim($headers['subject'] ?? '') !== '' ? trim($headers['subject']) : '(no subject)',
            body: trim(str_replace("\r\n", "\n", $body)),
            attachments: $attachments,
        );
    }

    /**
     * An auto-reply, a bounce, a mailing list, or our own mail coming back. Never
     * made into a ticket and never answered, or two robots would answer each other
     * for ever.
     */
    public function isAutomated(string $ownAddress): bool
    {
        $auto = strtolower($this->headers['auto-submitted'] ?? 'no');
        $precedence = strtolower($this->headers['precedence'] ?? '');
        $local = Str::before($this->fromEmail, '@');

        return ($auto !== '' && $auto !== 'no')
            || in_array($precedence, ['bulk', 'junk', 'list', 'auto_reply'], true)
            || isset($this->headers['x-autoreply'])
            || isset($this->headers['x-autorespond'])
            || isset($this->headers['list-id'])
            || isset($this->headers['list-unsubscribe'])
            || trim($this->headers['return-path'] ?? 'x') === '<>'
            || in_array('SENT', $this->labels, true)
            || $this->fromEmail === ''
            || preg_match('/^(mailer-daemon|postmaster|no-?reply|do-?not-?reply)$/i', $local) === 1
            || strcasecmp($this->fromEmail, $ownAddress) === 0
            || strcasecmp($this->fromEmail, (string) config('mail.from.address')) === 0;
    }

    /**
     * The reply without the quoted message under it.
     */
    public function newText(): string
    {
        $lines = [];

        foreach (explode("\n", $this->body) as $line) {
            if (preg_match('/^On .+wrote:\s*$/', trim($line)) === 1
                || preg_match('/^-{2,}\s*Original Message\s*-{2,}/i', trim($line)) === 1
                || preg_match('/^From: .+/', trim($line)) === 1 && $lines !== []) {
                break;
            }

            if (str_starts_with(ltrim($line), '>')) {
                continue;
            }

            $lines[] = $line;
        }

        $text = trim(implode("\n", $lines));

        return $text !== '' ? $text : $this->body;
    }

    public function messageId(): ?string
    {
        return $this->headers['message-id'] ?? null;
    }

    /**
     * @return array{0: string, 1: string} email, name
     */
    protected static function address(string $from): array
    {
        if (preg_match('/^\s*"?([^"<]*)"?\s*<([^>]+)>/', $from, $m) === 1) {
            return [strtolower(trim($m[2])), trim($m[1])];
        }

        return [strtolower(trim($from)), ''];
    }

    /**
     * @param  array<string, mixed>  $part
     * @param  list<array{filename: string, mimeType: string, attachmentId: ?string, data: ?string, size: int}>  $attachments
     */
    protected static function walk(array $part, ?string &$plain, ?string &$html, array &$attachments): void
    {
        $mime = strtolower((string) ($part['mimeType'] ?? ''));
        $body = (array) ($part['body'] ?? []);
        $filename = (string) ($part['filename'] ?? '');

        if ($filename !== '') {
            $attachments[] = [
                'filename' => $filename,
                'mimeType' => $mime,
                'attachmentId' => isset($body['attachmentId']) ? (string) $body['attachmentId'] : null,
                'data' => isset($body['data']) ? (string) $body['data'] : null,
                'size' => (int) ($body['size'] ?? 0),
            ];
        } elseif ($mime === 'text/plain' && $plain === null && isset($body['data'])) {
            $plain = GmailClient::decode((string) $body['data']);
        } elseif ($mime === 'text/html' && $html === null && isset($body['data'])) {
            $html = GmailClient::decode((string) $body['data']);
        }

        foreach ((array) ($part['parts'] ?? []) as $child) {
            self::walk((array) $child, $plain, $html, $attachments);
        }
    }

    protected static function fromHtml(string $html): string
    {
        $html = preg_replace('/<(br|\/p|\/div|\/li|\/tr)[^>]*>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<(style|script)[^>]*>.*?<\/\1>/is', '', $html) ?? $html;

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));
    }
}
