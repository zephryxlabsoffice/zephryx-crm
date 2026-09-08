<?php

namespace App\Mail;

use App\Support\Auth\PasswordResets;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * "Your account is ready — set your password."
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHY THIS IS NOT PasswordResetMail WITH A DIFFERENT SUBJECT
 *
 * It carries the same kind of link and the same expiry, so reusing the reset
 * mail would have worked. It would also have told somebody who has never had a
 * password that theirs is being RESET — which reads either as a mistake or as
 * somebody having got into their account, and the one thing an unexpected
 * security email must not do is make the recipient guess.
 *
 * It says who added them, because an account appearing unannounced is worth
 * being able to check with a person by name.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class AccountInviteMail extends Mailable
{
    public function __construct(
        public string $url,
        public string $name,
        public string $staffId,
        public string $addedBy,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your '.config('zephryx.brand.name').' account is ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.account-invite',
            with: [
                'url' => $this->url,
                'name' => $this->name,
                'staffId' => $this->staffId,
                'addedBy' => $this->addedBy,
                'minutes' => PasswordResets::EXPIRY_MINUTES,
            ],
        );
    }
}
