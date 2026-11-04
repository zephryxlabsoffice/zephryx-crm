<?php

namespace App\Mail;

use App\Models\Announcement;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * An announcement, for the staff it is addressed to, when "Also email it" is ticked.
 * Sent by App\Support\AnnouncementDelivery.
 */
class AnnouncementMail extends Mailable
{
    public function __construct(public Announcement $announcement) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->announcement->title);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.announcement',
            text: 'mail.text.announcement',
            with: [
                'title' => $this->announcement->title,
                'body' => $this->announcement->body,
                'author' => $this->announcement->author?->user?->name ?? 'the company',
                'url' => route('announcements.show', ['announcement' => $this->announcement->reference]),
            ],
        );
    }
}
