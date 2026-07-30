<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class VerifyEmail extends Component
{
    public string $status = 'error';

    public string $message = '';

    public function mount(): void
    {
        $token = request()->query('token');
        $email = request()->query('email');

        if (! $token || ! $email) {
            $this->message = 'Link verifikasi tidak valid.';

            return;
        }

        $verification = DB::table('email_verifications')
            ->where('email', $email)
            ->where('token', $token)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $verification) {
            $this->message = 'Token verifikasi tidak valid atau sudah kedaluwarsa.';

            return;
        }

        DB::transaction(function () use ($verification, $email): void {
            $user = User::where('email', $email)->first();
            $user?->update(['email_verified_at' => now()]);

            DB::table('email_verifications')
                ->where('id', $verification->id)
                ->update(['verified_at' => now()]);
        });

        $this->status = 'success';
        $this->message = 'Email berhasil diverifikasi. Anda dapat login sekarang.';
    }

    public function render()
    {
        return view('livewire.auth.verify-email')->extends('layouts.web');
    }
}
