<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * To somebody whose End of Day was marked urgent, who may already have gone home.
 * See App\Support\EodUrgent.
 */
class EodUrgentMail extends Mailable
{
    public function __construct(
        public string $name,
        public string $requesterName,
        public string $reportDay,
        public string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Urgent: your End of Day for '.$this->reportDay);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.eod-urgent',
            text: 'mail.text.eod-urgent',
            with: [
                'name' => $this->name,
                'requesterName' => $this->requesterName,
                'reportDay' => $this->reportDay,
                'url' => $this->url,
            ],
        );
    }
}
