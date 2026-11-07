<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * To whoever raised a ticket, when it moves. Never carries the reply's text: the
 * link goes to the ticket, where the ordinary visibility rules apply. See
 * App\Support\TicketLifecycle.
 */
class TicketUpdateMail extends Mailable
{
    public function __construct(
        public string $reference,
        public string $ticketSubject,
        public string $headline,
        public string $message,
        public string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->headline.': '.$this->reference);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.ticket-update',
            text: 'mail.text.ticket-update',
            with: [
                'reference' => $this->reference,
                'ticketSubject' => $this->ticketSubject,
                'headline' => $this->headline,
                'body' => $this->message,
                'url' => $this->url,
            ],
        );
    }
}
