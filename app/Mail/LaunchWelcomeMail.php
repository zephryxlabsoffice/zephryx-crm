<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;

/**
 * Launch day, to every active staff member. See App\Support\Launch.
 */
class LaunchWelcomeMail extends Mailable
{
    public function __construct(public string $launchOn)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: config('zephryx.brand.name').' '.config('zephryx.brand.suffix').' is live from today');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.launch-welcome',
            text: 'mail.text.launch-welcome',
            with: [
                'day' => Carbon::parse($this->launchOn)->format('l j F Y'),
                'url' => url('/login'),
            ],
        );
    }
}
