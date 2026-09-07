<?php

namespace App\Mail;

use App\Support\Auth\OneTimeCode;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The sign-in code (foundation spec §4.3).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * NO LINK IN THIS EMAIL, AND THAT IS DELIBERATE
 *
 * A "click here to sign in" link in a code email trains people to click links
 * in emails about signing in, which is the exact reflex every phishing attempt
 * relies on. The code is typed into a page the person navigated to themselves.
 *
 * It also names the address and the time it was requested, so somebody who did
 * not request it can recognise that immediately rather than assuming it is
 * noise.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class OneTimeCodeMail extends Mailable
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $ip = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            // The code is not in the subject. Subjects show in notification
            // previews on a locked screen, which is not where a second factor
            // should be readable.
            subject: config('zephryx.brand.name').' sign-in code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.one-time-code',
            with: [
                'code' => $this->code,
                'name' => $this->name,
                'ip' => $this->ip,
                'minutes' => OneTimeCode::EXPIRY_MINUTES,
            ],
        );
    }
}
