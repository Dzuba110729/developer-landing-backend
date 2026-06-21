<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserContactConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly array $contact,
        public readonly array $ai,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ваша заявка принята — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.user-confirmation',
            with: [
                'contact' => $this->contact,
                'ai' => $this->ai,
            ],
        );
    }
}
