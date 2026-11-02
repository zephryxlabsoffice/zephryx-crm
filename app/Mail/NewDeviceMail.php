<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * To the account, when it signs in on a device it has not used before, with the
 * red "This wasn't me" button. See App\Support\Auth\UserDevices::announce.
 */
class NewDeviceMail extends Mailable
{
    public function __construct(
        public string $name,
        public string $deviceName,
        public string $location,
        public ?string $ip,
        public string $signedInAt,
        public string $devicesUrl,
        public string $notMeUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New device signed in to your '.config('zephryx.brand.name').' account');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.new-device',
            text: 'mail.text.new-device',
            with: [
                'name' => $this->name,
                'deviceName' => $this->deviceName,
                'location' => $this->location,
                'ip' => $this->ip,
                'signedInAt' => $this->signedInAt,
                'devicesUrl' => $this->devicesUrl,
                'notMeUrl' => $this->notMeUrl,
            ],
        );
    }
}
