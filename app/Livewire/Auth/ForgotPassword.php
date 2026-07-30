<?php

namespace App\Livewire\Auth;

use App\Mail\ResetPasswordMandiri;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Livewire\Component;

/**
 * Lupa password — versi Livewire dari app/Http/Controllers/AuthController@forgotPassword
 * (API, dipakai siak-frontend). Sama-sama memakai broker Password::sendResetLink() bawaan
 * Laravel, tapi lewat parameter $callback supaya link di email mengarah ke route Livewire
 * 'reset-password', bukan lewat User::sendPasswordResetNotification() default yang dikontrol
 * closure global ResetPassword::createUrlUsing() (lihat AppServiceProvider) — jalur Next.js
 * yang memanggil endpoint API sama sekali tidak tersentuh oleh perubahan ini.
 */
class ForgotPassword extends Component
{
    public string $email = '';

    public string $errorMessage = '';

    public string $successMessage = '';

    public function sendResetLink(): void
    {
        $this->errorMessage = '';
        $this->successMessage = '';

        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $status = Password::sendResetLink(
            ['email' => $this->email],
            function (User $user, string $token): void {
                $url = route('reset-password', ['token' => $token, 'email' => $user->email]);
                Mail::to($user->email)->send(new ResetPasswordMandiri($user, $url));
            }
        );

        if ($status !== Password::RESET_LINK_SENT) {
            $this->errorMessage = __($status);

            return;
        }

        $this->successMessage = 'Link reset password berhasil dikirim ke email Anda. Silakan cek inbox Anda.';
    }

    public function render()
    {
        return view('livewire.auth.forgot-password')->extends('layouts.web');
    }
}
