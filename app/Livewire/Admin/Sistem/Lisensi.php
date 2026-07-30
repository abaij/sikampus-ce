<?php

namespace App\Livewire\Admin\Sistem;

use App\Models\Setting;
use App\Services\InstallationReporter;
use Livewire\Component;

class Lisensi extends Component
{
    public string $licenseKey = '';

    public function mount(): void
    {
        $this->licenseKey = (string) Setting::where('key', 'app_license_key')->value('value');
    }

    protected function rules(): array
    {
        return [
            'licenseKey' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        Setting::updateOrCreate(['key' => 'app_license_key'], ['value' => $validated['licenseKey']]);

        if ($validated['licenseKey'] !== '') {
            app(InstallationReporter::class)->report($validated['licenseKey']);
        }

        session()->flash('status', 'License key berhasil disimpan.');
    }

    public function render()
    {
        return view('livewire.admin.sistem.lisensi')->extends('layouts.web');
    }
}
