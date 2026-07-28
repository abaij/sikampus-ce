<?php

namespace App\Livewire\Admin\KomponenBiaya;

use App\Models\KomponenBiaya;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?int $komponenBiayaId = null;

    public string $kode = '';

    public string $nama = '';

    public bool $is_per_semester = true;

    public bool $is_akademik = false;

    public function mount(?int $id = null): void
    {
        $this->komponenBiayaId = $id;

        if ($id === null) {
            return;
        }

        $komponenBiaya = KomponenBiaya::findOrFail($id);

        $this->kode = $komponenBiaya->kode;
        $this->nama = $komponenBiaya->nama;
        $this->is_per_semester = $komponenBiaya->is_per_semester;
        $this->is_akademik = $komponenBiaya->is_akademik;
    }

    /**
     * Sama persis dengan KomponenBiayaController::store/update.
     */
    protected function rules(): array
    {
        $uniqueKode = Rule::unique('komponen_biaya', 'kode');

        if ($this->komponenBiayaId) {
            $uniqueKode = $uniqueKode->ignore($this->komponenBiayaId);
        }

        return [
            'kode' => ['required', 'string', 'max:50', $uniqueKode],
            'nama' => ['required', 'string', 'max:255'],
            'is_per_semester' => ['nullable', 'boolean'],
            'is_akademik' => ['nullable', 'boolean'],
        ];
    }

    public function save()
    {
        $validated = $this->validate();

        if ($this->komponenBiayaId) {
            KomponenBiaya::findOrFail($this->komponenBiayaId)->update($validated);
        } else {
            KomponenBiaya::create($validated);
        }

        session()->flash('status', 'Komponen biaya berhasil disimpan.');

        return redirect()->route('admin.keuangan.komponen-biaya');
    }

    public function render()
    {
        return view('livewire.admin.komponen-biaya.form')->extends('layouts.web');
    }
}
