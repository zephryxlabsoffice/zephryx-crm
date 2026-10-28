<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A link proving an address reaches its owner. See
 * App\Support\Auth\EmailVerification.
 */
class EmailVerifyMail extends Mailable
{
    public function __construct(
        public string $url,
        public string $name,
        public string $address,
        public int $hours,
        public string $notMeUrl = '',
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Verify your email for '.config('zephryx.brand.name'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.email-verify',
            text: 'mail.text.email-verify',
            with: [
                'url' => $this->url,
                'name' => $this->name,
                'address' => $this->address,
                'hours' => $this->hours,
                'notMeUrl' => $this->notMeUrl,
            ],
        );
    }
}
