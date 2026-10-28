<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * To the address that was just replaced, with the red "This wasn't me"
 * button. See App\Support\Auth\EmailVerification::warnOldAddress.
 */
class EmailChangedNoticeMail extends Mailable
{
    public function __construct(
        public string $name,
        public string $oldAddress,
        public string $newAddress,
        public string $notMeUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your '.config('zephryx.brand.name').' email was changed');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.email-changed-notice',
            text: 'mail.text.email-changed-notice',
            with: [
                'name' => $this->name,
                'oldAddress' => $this->oldAddress,
                'newAddress' => $this->newAddress,
                'notMeUrl' => $this->notMeUrl,
            ],
        );
    }
}
