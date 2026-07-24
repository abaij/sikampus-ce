<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

class PmbCamabaAdminContactMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $namaCamaba,
        public string $subjectLine,
        public string $bodyPlain,
        public string $namaAdmin,
        public string $emailAdmin,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[PMB] '.$this->subjectLine,
            replyTo: [
                new Address($this->emailAdmin, $this->namaAdmin),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pmb-camaba-admin-contact',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
