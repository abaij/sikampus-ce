<?php

namespace App\Livewire\Admin\Sistem;

use App\Services\EnvFileWriter;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;

class Lisensi extends Component
{
    public string $licenseKey = '';

    public string $formError = '';

    public function mount(): void
    {
        $this->licenseKey = app(EnvFileWriter::class)->get('APP_LICENSE_KEY');
    }

    protected function rules(): array
    {
        return [
            'licenseKey' => ['nullable', 'string', 'max:255'],
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

        try {
            $env->set(['APP_LICENSE_KEY' => $validated['licenseKey']]);
        } catch (\Throwable $e) {
            report($e);
            $this->formError = 'Gagal menyimpan .env: '.$e->getMessage();

            return;
        }

        Artisan::call('config:clear');

        session()->flash('status', 'License key berhasil disimpan.');
    }

    public function render()
    {
        return view('livewire.admin.sistem.lisensi')->extends('layouts.web');
    }
}
