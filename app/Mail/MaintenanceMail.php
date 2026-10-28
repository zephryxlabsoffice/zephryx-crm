<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;

/**
 * Tells everybody the site is closing, closed, back, or no longer closing.
 * See App\Support\Maintenance.
 */
class MaintenanceMail extends Mailable
{
    public const STARTED = 'started';

    public const SCHEDULED = 'scheduled';

    public const ENDED = 'ended';

    public const CANCELLED = 'cancelled';

    public function __construct(
        public string $kind,
        public ?string $startsAt,
        public ?string $endsAt,
        public ?string $note,
    ) {
    }

    public function envelope(): Envelope
    {
        $brand = config('zephryx.brand.name');

        return new Envelope(subject: match ($this->kind) {
            self::STARTED => $brand.' is under maintenance now',
            self::SCHEDULED => $brand.' maintenance on '.$this->formatted($this->startsAt, 'D j M'),
            self::ENDED => $brand.' is back',
            default => $brand.' maintenance is called off',
        });
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.maintenance',
            text: 'mail.text.maintenance',
            with: [
                'kind' => $this->kind,
                'from' => $this->formatted($this->startsAt),
                'to' => $this->formatted($this->endsAt),
                'note' => $this->note,
                'url' => url('/'),
            ],
        );
    }

    protected function formatted(?string $at, string $format = 'D j M Y, g:i A'): ?string
    {
        return $at === null ? null : Carbon::parse($at)->format($format);
    }
}
