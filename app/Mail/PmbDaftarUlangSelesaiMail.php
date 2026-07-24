<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PmbDaftarUlangSelesaiMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $namaCamaba,
        public string $noPendaftaran,
        public string $namaProdi,
        public ?string $kodeProdi,
        public ?string $nim,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Daftar ulang PMB selesai — '.$this->noPendaftaran,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pmb-daftar-ulang-selesai',
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
