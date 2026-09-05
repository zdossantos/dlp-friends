<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class MemberDeletedByAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $displayName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('administration.members.mail.subject'));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.admin.member-deleted',
            with: ['displayName' => $this->displayName],
        );
    }
}
