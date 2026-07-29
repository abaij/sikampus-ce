<?php

namespace App\Livewire\Mahasiswa;

use App\Models\Mahasiswa;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Profil extends Component
{
    public int $mahasiswaId;

    public string $nim = '';

    public string $nama = '';

    public string $email = '';

    public string $handphone = '';

    public string $no_wa = '';

    public string $alamat = '';

    public string $kode_pos = '';

    public string $current_password = '';

    public string $new_password = '';

    public string $new_password_confirmation = '';

    public function mount(): void
    {
        $mahasiswa = Mahasiswa::where('id_user', Auth::id())->firstOrFail();

        $this->mahasiswaId = $mahasiswa->id;
        $this->nim = (string) $mahasiswa->nim;
        $this->nama = $mahasiswa->nama;
        $this->email = (string) $mahasiswa->email;
        $this->handphone = (string) $mahasiswa->handphone;
        $this->no_wa = (string) $mahasiswa->no_wa;
        $this->alamat = (string) $mahasiswa->alamat;
        $this->kode_pos = (string) $mahasiswa->kode_pos;
    }

    /**
     * Subset dari MahasiswaController::updateMyProfile — hanya field kontak yang boleh
     * diubah lewat self-service; NIM sengaja hanya ditampilkan (read-only).
     */
    public function saveProfil(): void
    {
        $mahasiswa = Mahasiswa::findOrFail($this->mahasiswaId);

        $validated = $this->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('mahasiswa', 'email')->ignore($mahasiswa->id)],
            'handphone' => ['nullable', 'string', 'max:20'],
            'no_wa' => ['nullable', 'string', 'max:20'],
            'alamat' => ['nullable', 'string'],
            'kode_pos' => ['nullable', 'string', 'max:10'],
        ]);

        foreach (['email', 'handphone', 'no_wa', 'alamat', 'kode_pos'] as $field) {
            if ($validated[$field] === '') {
                $validated[$field] = null;
            }
        }

        $mahasiswa->update($validated);

        session()->flash('status', 'Profil berhasil diperbarui.');
    }

    /**
     * Sama dengan MahasiswaController::updateMyPassword.
     */
    public function savePassword(): void
    {
        $user = Auth::user();

        $this->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8'],
            'new_password_confirmation' => ['required', 'string', 'same:new_password'],
        ], [], [
            'current_password' => 'password saat ini',
            'new_password' => 'password baru',
            'new_password_confirmation' => 'konfirmasi password baru',
        ]);

        if (! Hash::check($this->current_password, $user->password)) {
            $this->addError('current_password', 'Password saat ini tidak sesuai.');

            return;
        }

        $user->update(['password' => Hash::make($this->new_password)]);

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);

        session()->flash('status', 'Password berhasil diubah.');
    }

    public function render()
    {
        return view('livewire.mahasiswa.profil')->extends('layouts.mahasiswa');
    }
}
