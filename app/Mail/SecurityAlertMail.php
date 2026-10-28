<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * To the owner and System Administrators when an account is locked from a
 * "This wasn't me" link. See App\Support\Auth\SecurityLock.
 */
class SecurityAlertMail extends Mailable
{
    public function __construct(
        public string $name,
        public string $staffId,
        public string $address,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Security: '.$this->name.'\'s account was locked');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.security-alert',
            text: 'mail.text.security-alert',
            with: [
                'name' => $this->name,
                'staffId' => $this->staffId,
                'address' => $this->address,
                'url' => url('/admin/accounts/'.$this->staffId),
            ],
        );
    }
}
