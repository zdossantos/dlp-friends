<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $token,
        public readonly string $resetUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.password_reset.subject'));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.auth.reset-password',
            with: ['resetUrl' => $this->resetUrl],
        );
    }
}
