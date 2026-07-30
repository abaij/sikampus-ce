<?php

namespace App\Livewire\Admin\Sistem;

use App\Services\EnvFileWriter;
use Illuminate\Support\Facades\Artisan;
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
     * Password tidak pernah dikirim balik ke browser — properti ini cuma menandai apakah .env
     * sudah punya MAIL_PASSWORD, supaya form tahu field password boleh dibiarkan kosong saat
     * simpan (artinya "jangan diubah"), bukan berarti "kosongkan password".
     */
    public bool $hasStoredPassword = false;

    public string $formError = '';

    public function mount(): void
    {
        $env = app(EnvFileWriter::class);

        $this->host = $env->get('MAIL_HOST');
        $this->port = $env->get('MAIL_PORT');
        $this->username = $env->get('MAIL_USERNAME');
        $this->encryption = $env->get('MAIL_SCHEME');
        $this->fromAddress = $env->get('MAIL_FROM_ADDRESS');
        $this->fromName = $env->get('MAIL_FROM_NAME');
        $this->hasStoredPassword = $env->get('MAIL_PASSWORD') !== '';
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
        $this->formError = '';

        $env = app(EnvFileWriter::class);

        if (! $env->isWritable()) {
            $this->formError = $env->exists()
                ? 'File .env tidak dapat ditulis. Periksa izin berkas di server.'
                : 'Direktori proyek tidak dapat ditulis; tidak dapat membuat .env.';

            return;
        }

        $validated = $this->validate();

        $values = [
            'MAIL_MAILER' => 'smtp',
            'MAIL_HOST' => $validated['host'],
            'MAIL_PORT' => (string) $validated['port'],
            'MAIL_USERNAME' => $validated['username'],
            'MAIL_SCHEME' => $validated['encryption'] ?? '',
            'MAIL_FROM_ADDRESS' => $validated['fromAddress'],
            'MAIL_FROM_NAME' => $validated['fromName'],
        ];

        if (($validated['password'] ?? '') !== '') {
            $values['MAIL_PASSWORD'] = $validated['password'];
        }

        try {
            $env->set($values);
        } catch (\Throwable $e) {
            report($e);
            $this->formError = 'Gagal menyimpan .env: '.$e->getMessage();

            return;
        }

        // Supaya pengaturan langsung aktif tanpa langkah manual — config bisa saja sudah
        // di-cache (php artisan config:cache) sebelumnya.
        Artisan::call('config:clear');

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
