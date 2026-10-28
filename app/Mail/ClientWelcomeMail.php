<?php

namespace App\Mail;

use App\Support\Auth\PasswordResets;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A client's welcome to the portal, sent by "Give portal access"
 * (ClientController::invite). Its own mail rather than the staff invite,
 * which talks about staff IDs and records (owner, 2026-10-28).
 */
class ClientWelcomeMail extends Mailable
{
    public function __construct(
        public string $url,
        public string $name,
        public string $company,
        public string $clientId,
        public string $addedBy,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Welcome to the '.config('zephryx.brand.name').' client portal');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.client-welcome',
            text: 'mail.text.client-welcome',
            with: [
                'url' => $this->url,
                'name' => $this->name,
                'company' => $this->company,
                'clientId' => $this->clientId,
                'addedBy' => $this->addedBy,
                'minutes' => PasswordResets::EXPIRY_MINUTES,
                'portal' => url('/login'),
            ],
        );
    }
}
