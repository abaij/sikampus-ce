<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Livewire\Component;

class ResetPassword extends Component
{
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public string $errorMessage = '';

    public string $successMessage = '';

    public bool $hasRequiredParams = false;

    public function mount(): void
    {
        $this->token = (string) request()->query('token', '');
        $this->email = (string) request()->query('email', '');
        $this->hasRequiredParams = $this->token !== '' && $this->email !== '';
    }

    public function resetPassword(): void
    {
        $this->errorMessage = '';

        $this->validate([
            'password' => ['required', 'string', 'min:8'],
            'passwordConfirmation' => ['required', 'string', 'same:password'],
        ], [
            'passwordConfirmation.same' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $status = Password::reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->passwordConfirmation,
                'token' => $this->token,
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->errorMessage = __($status);

            return;
        }

        $this->successMessage = 'Password berhasil diperbarui. Silakan masuk dengan password baru Anda.';
    }

    public function render()
    {
        return view('livewire.auth.reset-password')->extends('layouts.web');
    }
}
