<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * The answer to an email sent to support@ (owner, 2026-11-06), from support@.
 *
 * A known sender (a client or an employee) is told the ticket's reference and
 * where to follow it. An unknown sender gets the owner's automated reply: no
 * ticket number, and a nudge to write from their registered email.
 *
 * The subject keeps theirs and carries the reference, so their reply to it
 * finds the ticket again. Auto-Submitted stops another robot answering it.
 */
class SupportAckMail extends Mailable
{
    public function __construct(
        public bool $known,
        public string $fromAddress,
        public string $originalSubject,
        public ?string $reference,
        public ?string $url,
        public ?string $inReplyTo = null,
    ) {}

    public function envelope(): Envelope
    {
        $subject = 'Re: '.preg_replace('/^(re|fwd?):\s*/i', '', $this->originalSubject);

        return new Envelope(
            from: new Address($this->fromAddress, config('app.name').' Support'),
            replyTo: [new Address($this->fromAddress)],
            subject: $this->reference === null ? $subject : $subject.' ['.$this->reference.']',
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            references: $this->inReplyTo === null ? [] : [trim($this->inReplyTo, '<>')],
            text: array_filter([
                'Auto-Submitted' => 'auto-replied',
                'In-Reply-To' => $this->inReplyTo,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.support-ack',
            text: 'mail.text.support-ack',
            with: [
                'known' => $this->known,
                'reference' => $this->reference,
                'url' => $this->url,
                'site' => preg_replace('#^https?://#', '', rtrim((string) config('app.url'), '/')),
            ],
        );
    }
}
