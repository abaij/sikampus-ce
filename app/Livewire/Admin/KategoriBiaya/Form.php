<?php

namespace App\Livewire\Admin\KategoriBiaya;

use App\Models\KategoriBiaya;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?int $kategoriBiayaId = null;

    public string $nama = '';

    public string $kode = '';

    public string $status = 'active';

    public function mount(?int $id = null): void
    {
        $this->kategoriBiayaId = $id;

        if ($id === null) {
            return;
        }

        $kategoriBiaya = KategoriBiaya::findOrFail($id);

        $this->nama = $kategoriBiaya->nama;
        $this->kode = (string) $kategoriBiaya->kode;
        $this->status = $kategoriBiaya->status ?? 'active';
    }

    /**
     * Sama persis dengan KategoriBiayaController::store/update.
     */
    protected function rules(): array
    {
        $uniqueKode = Rule::unique('kategori_biaya', 'kode');

        if ($this->kategoriBiayaId) {
            $uniqueKode = $uniqueKode->ignore($this->kategoriBiayaId);
        }

        return [
            'nama' => ['required', 'string', 'max:255'],
            'kode' => ['nullable', 'string', 'max:50', $uniqueKode],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ];
    }

    public function save()
    {
        $validated = $this->validate();

        if ($validated['kode'] === '') {
            $validated['kode'] = null;
        }

        if (empty($validated['status'])) {
            $validated['status'] = 'active';
        }

        if ($this->kategoriBiayaId) {
            KategoriBiaya::findOrFail($this->kategoriBiayaId)->update($validated);
        } else {
            KategoriBiaya::create($validated);
        }

        session()->flash('status', 'Kategori biaya berhasil disimpan.');

        return redirect()->route('admin.keuangan.kategori-biaya');
    }

    public function render()
    {
        return view('livewire.admin.kategori-biaya.form')->extends('layouts.web');
    }
}
