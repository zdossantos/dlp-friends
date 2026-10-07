<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklyEmailRecapMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly int $conversations, public readonly ?int $matches) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('weekly-recap.subject'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.weekly-recap');
    }
}
