<?php

namespace App\Mail;

use App\Support\Auth\EmailChanges;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * One half of an email change (§4.1).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ONE CLASS, TWO AUDIENCES, AND THEY ARE NOT SENT THE SAME WORDS
 *
 * The mail to the OLD address is the security notice: somebody is moving your
 * sign-in address, here is where to, ignore this if it was not you. The mail to
 * the NEW address is a plain confirmation. Merging them into one wording gets
 * the old address a message that reads as routine, which is the one message on
 * this flow that must not.
 *
 * The old address is written out in the new address's mail and vice versa,
 * because "confirm this change" without saying which change is a link people
 * click on reflex.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class EmailChangeMail extends Mailable
{
    public function __construct(
        public string $half,
        public string $url,
        public string $name,
        public string $fromEmail,
        public string $toEmail,
        public ?string $ip = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->half === EmailChanges::OLD
                ? 'Confirm the change to your '.config('zephryx.brand.name').' sign-in address'
                : 'Confirm your new '.config('zephryx.brand.name').' sign-in address',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.email-change',
            with: [
                'half' => $this->half,
                'url' => $this->url,
                'name' => $this->name,
                'fromEmail' => $this->fromEmail,
                'toEmail' => $this->toEmail,
                'ip' => $this->ip,
                'hours' => EmailChanges::EXPIRY_HOURS,
            ],
        );
    }
}
