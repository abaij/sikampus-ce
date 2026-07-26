<?php

namespace App\Livewire\Dosen;

use App\Models\Dosen;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Profil extends Component
{
    public int $dosenId;

    public string $kode_dosen = '';

    public string $nip = '';

    public string $nidn = '';

    public string $nama = '';

    public string $email = '';

    public string $gelar_depan = '';

    public string $gelar_belakang = '';

    public string $no_hp = '';

    public string $alamat = '';

    public string $kode_pos = '';

    public string $current_password = '';

    public string $new_password = '';

    public string $new_password_confirmation = '';

    public function mount(): void
    {
        $dosen = Dosen::where('id_user', Auth::id())->firstOrFail();

        $this->dosenId = $dosen->id;
        $this->kode_dosen = (string) $dosen->kode_dosen;
        $this->nip = (string) $dosen->nip;
        $this->nidn = (string) $dosen->nidn;
        $this->nama = $dosen->nama;
        $this->email = (string) $dosen->email;
        $this->gelar_depan = (string) $dosen->gelar_depan;
        $this->gelar_belakang = (string) $dosen->gelar_belakang;
        $this->no_hp = (string) $dosen->no_hp;
        $this->alamat = (string) $dosen->alamat;
        $this->kode_pos = (string) $dosen->kode_pos;
    }

    /**
     * Subset dari DosenController::updateMyProfile — hanya field kontak & gelar yang boleh
     * diubah lewat self-service; kode_dosen/nip/nidn sengaja hanya ditampilkan (read-only).
     */
    public function saveProfil(): void
    {
        $dosen = Dosen::findOrFail($this->dosenId);

        $validated = $this->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('dosen', 'email')->ignore($dosen->id)],
            'gelar_depan' => ['nullable', 'string', 'max:50'],
            'gelar_belakang' => ['nullable', 'string', 'max:50'],
            'no_hp' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string'],
            'kode_pos' => ['nullable', 'string', 'max:20'],
        ]);

        foreach (['email', 'gelar_depan', 'gelar_belakang', 'no_hp', 'alamat', 'kode_pos'] as $field) {
            if ($validated[$field] === '') {
                $validated[$field] = null;
            }
        }

        $dosen->update($validated);

        session()->flash('status', 'Profil berhasil diperbarui.');
    }

    /**
     * Sama dengan DosenController::updateMyPassword.
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
        return view('livewire.dosen.profil')->extends('layouts.web');
    }
}
