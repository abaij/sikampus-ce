<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Dikirim dari App\Livewire\Auth\ForgotPassword lewat callback Password::sendResetLink(),
 * BUKAN lewat notifikasi bawaan User::sendPasswordResetNotification() — supaya link di sini
 * selalu mengarah ke route Livewire 'reset-password', terpisah dari
 * ResetPassword::createUrlUsing() global (app/Providers/AppServiceProvider.php) yang tetap
 * mengarah ke FRONTEND_URL untuk alur Next.js/API.
 */
class ResetPasswordMandiri extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;

    public $resetUrl;

    public function __construct(User $user, string $resetUrl)
    {
        $this->user = $user;
        $this->resetUrl = $resetUrl;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset Password - SIAK',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reset-password-mandiri',
            with: [
                'user' => $this->user,
                'resetUrl' => $this->resetUrl,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Dipanggil otomatis oleh queue worker (Illuminate\Mail\SendQueuedMailable) setelah semua
     * percobaan kirim habis dan tetap gagal (mis. SMTP transport error) — lihat catatan channel
     * 'mail' di app/Providers/AppServiceProvider.php.
     */
    public function failed(Throwable $exception): void
    {
        Log::channel('mail')->error('Gagal mengirim email reset password', [
            'to' => $this->user->email,
            'user_id' => $this->user->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
