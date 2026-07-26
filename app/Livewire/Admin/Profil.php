<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Profil extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public string $city = '';

    public string $state = '';

    public string $zip = '';

    public string $country = '';

    public string $current_password = '';

    public string $new_password = '';

    public string $new_password_confirmation = '';

    public function mount(): void
    {
        $user = Auth::user();

        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) $user->phone;
        $this->address = (string) $user->address;
        $this->city = (string) $user->city;
        $this->state = (string) $user->state;
        $this->zip = (string) $user->zip;
        $this->country = (string) $user->country;
    }

    public function saveProfil(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'zip' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
        ], [], [
            'name' => 'nama',
        ]);

        foreach (['phone', 'address', 'city', 'state', 'zip', 'country'] as $field) {
            if ($validated[$field] === '') {
                $validated[$field] = null;
            }
        }

        $user->update($validated);

        session()->flash('status', 'Profil berhasil diperbarui.');
    }

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
        return view('livewire.admin.profil')->extends('layouts.web');
    }
}
