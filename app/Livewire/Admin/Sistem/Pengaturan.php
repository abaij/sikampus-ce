<?php

namespace App\Livewire\Admin\Sistem;

use App\Models\Setting;
use Livewire\Component;

class Pengaturan extends Component
{
    public string $host = '';

    public string $port = '';

    public string $username = '';

    public string $password = '';

    public string $encryption = '';

    public string $fromAddress = '';

    public string $fromName = '';

    /**
     * Password tidak pernah dikirim balik ke browser — properti ini cuma menandai apakah baris
     * app_mail_password sudah ada isinya, supaya form tahu field password boleh dibiarkan kosong
     * saat simpan (artinya "jangan diubah"), bukan berarti "kosongkan password".
     */
    public bool $hasStoredPassword = false;

    public function mount(): void
    {
        $settings = Setting::where('key', 'like', 'app_mail_%')->pluck('value', 'key');

        $this->host = (string) ($settings['app_mail_host'] ?? '');
        $this->port = (string) ($settings['app_mail_port'] ?? '');
        $this->username = (string) ($settings['app_mail_username'] ?? '');
        $this->encryption = (string) ($settings['app_mail_encryption'] ?? '');
        $this->fromAddress = (string) ($settings['app_mail_from_address'] ?? '');
        $this->fromName = (string) ($settings['app_mail_from_name'] ?? '');
        $this->hasStoredPassword = (string) ($settings['app_mail_password'] ?? '') !== '';
    }

    protected function rules(): array
    {
        return [
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'encryption' => ['nullable', 'in:,smtps'],
            'fromAddress' => ['required', 'email', 'max:255'],
            'fromName' => ['required', 'string', 'max:255'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $values = [
            'app_mail_host' => $validated['host'],
            'app_mail_port' => (string) $validated['port'],
            'app_mail_username' => $validated['username'],
            'app_mail_encryption' => $validated['encryption'] ?? '',
            'app_mail_from_address' => $validated['fromAddress'],
            'app_mail_from_name' => $validated['fromName'],
        ];

        if (($validated['password'] ?? '') !== '') {
            $values['app_mail_password'] = $validated['password'];
        }

        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        if (($validated['password'] ?? '') !== '') {
            $this->hasStoredPassword = true;
        }
        $this->password = '';

        session()->flash('status', 'Pengaturan SMTP berhasil disimpan.');
    }

    public function render()
    {
        return view('livewire.admin.sistem.pengaturan')->extends('layouts.web');
    }
}
