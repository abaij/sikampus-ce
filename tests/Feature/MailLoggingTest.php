<?php

use App\Mail\ResetPasswordMandiri;
use App\Mail\VerifyEmailActivation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function mailLogPath(): string
{
    return storage_path('logs/mail-'.now()->format('Y-m-d').'.log');
}

function mailLogTail(int $chars = 4000): string
{
    $path = mailLogPath();

    if (! file_exists($path)) {
        return '';
    }

    $size = filesize($path);
    $offset = max(0, $size - $chars);

    return file_get_contents($path, false, null, $offset);
}

it('mencatat percobaan dan keberhasilan kirim email verifikasi aktivasi ke channel mail', function () {
    config(['queue.default' => 'sync', 'mail.default' => 'log']);

    $user = User::factory()->create(['email' => 'log-sukses@example.com']);

    Mail::to($user->email)->send(new VerifyEmailActivation($user, 'https://example.test/verify-email'));

    $log = mailLogTail();

    expect($log)
        ->toContain('Mengirim email')
        ->toContain('Email berhasil dikirim')
        ->toContain('log-sukses@example.com')
        ->toContain('Verifikasi Email - Aktivasi Akun SIAK');
});

it('mencatat kegagalan kirim email verifikasi aktivasi ke channel mail terpisah dari laravel.log', function () {
    $user = User::factory()->create(['email' => 'log-gagal@example.com']);

    (new VerifyEmailActivation($user, 'https://example.test/verify-email'))
        ->failed(new Exception('Connection could not be established with host smtp.test'));

    $log = mailLogTail();

    expect($log)
        ->toContain('Gagal mengirim email verifikasi aktivasi')
        ->toContain('log-gagal@example.com')
        ->toContain('Connection could not be established with host smtp.test');

    expect(mailLogPath())->not->toBe(storage_path('logs/laravel.log'));
});

it('mencatat kegagalan kirim email reset password ke channel mail', function () {
    $user = User::factory()->create(['email' => 'reset-gagal@example.com']);

    (new ResetPasswordMandiri($user, 'https://example.test/reset-password'))
        ->failed(new Exception('Connection refused'));

    $log = mailLogTail();

    expect($log)
        ->toContain('Gagal mengirim email reset password')
        ->toContain('reset-gagal@example.com')
        ->toContain('Connection refused');
});
