<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OwnerContactNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly array $contact,
        public readonly array $ai,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Новая заявка с формы обратной связи — '.$this->contact['name'],
            replyTo: [$this->contact['email']],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.owner-notification',
            with: [
                'contact' => $this->contact,
                'ai' => $this->ai,
            ],
        );
    }
}
