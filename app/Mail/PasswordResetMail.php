<?php

namespace App\Mail;

use App\Support\Auth\PasswordResets;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The password reset link (foundation spec §4.6).
 *
 * This one does carry a link, because there is no other way to prove possession
 * of the mailbox - but it says plainly what to do if it was not requested, and
 * it does not say anything about the account beyond the address it was sent to.
 */
class PasswordResetMail extends Mailable
{
    public function __construct(
        public string $url,
        public string $name,
        public ?string $ip = null,
        // The red "This wasn't me" link; empty for a reset an admin sent.
        public string $notMeUrl = '',
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset your '.config('zephryx.brand.name').' password',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.password-reset',
            text: 'mail.text.password-reset',
            with: [
                'url' => $this->url,
                'name' => $this->name,
                'ip' => $this->ip,
                'notMeUrl' => $this->notMeUrl,
                'minutes' => PasswordResets::EXPIRY_MINUTES,
            ],
        );
    }
}
