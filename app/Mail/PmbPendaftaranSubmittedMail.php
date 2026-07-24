<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PmbPendaftaranSubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{nama: string, kode?: string, jenjang?: string}>  $prodiPilihan
     * @param  array<int, array{nama: string, jumlah: float|int}>  $rincianBiaya
     */
    public function __construct(
        public string $namaCamaba,
        public string $noPendaftaran,
        public ?string $tanggalPendaftaran,
        public string $namaPeriode,
        public ?string $jalurMasuk,
        public ?string $jenisDaftar,
        public array $prodiPilihan,
        public string $noKuitansi,
        public array $rincianBiaya,
        public float $totalBiaya,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Konfirmasi pendaftaran PMB — '.$this->noPendaftaran,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pmb-pendaftaran-submitted',
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
