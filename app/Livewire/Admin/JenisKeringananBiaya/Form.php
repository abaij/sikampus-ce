<?php

namespace App\Livewire\Admin\JenisKeringananBiaya;

use App\Models\JenisKeringananBiaya;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Form extends Component
{
    public ?int $jenisKeringananBiayaId = null;

    public string $nama = '';

    public bool $is_persentase = false;

    public string $nominal = '';

    public bool $is_active = true;

    public string $keterangan = '';

    public function mount(?int $id = null): void
    {
        $this->jenisKeringananBiayaId = $id;

        if ($id === null) {
            return;
        }

        $jenisKeringananBiaya = JenisKeringananBiaya::findOrFail($id);

        $this->nama = $jenisKeringananBiaya->nama;
        $this->is_persentase = (bool) $jenisKeringananBiaya->is_persentase;
        $this->nominal = (string) $jenisKeringananBiaya->nominal;
        $this->is_active = (bool) $jenisKeringananBiaya->is_active;
        $this->keterangan = (string) $jenisKeringananBiaya->keterangan;
    }

    /**
     * Sama persis dengan JenisKeringananBiayaController::store/update — batas max:100 hanya
     * berlaku kalau nilainya persentase.
     */
    protected function rules(): array
    {
        $nominalRules = ['required', 'numeric', 'min:0'];
        if ($this->is_persentase) {
            $nominalRules[] = 'max:100';
        }

        return [
            'nama' => ['required', 'string', 'max:255'],
            'is_persentase' => ['boolean'],
            'nominal' => $nominalRules,
            'is_active' => ['boolean'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    public function save()
    {
        $validated = $this->validate();

        if ($validated['keterangan'] === '') {
            $validated['keterangan'] = null;
        }

        $user = Auth::user();
        $userName = $user?->name ?? (string) $user?->id;

        if ($this->jenisKeringananBiayaId) {
            $validated['updated_by'] = $userName;
            JenisKeringananBiaya::findOrFail($this->jenisKeringananBiayaId)->update($validated);
        } else {
            $validated['created_by'] = $userName;
            JenisKeringananBiaya::create($validated);
        }

        session()->flash('status', 'Jenis keringanan biaya berhasil disimpan.');

        return redirect()->route('admin.keuangan.jenis-keringanan-biaya');
    }

    public function render()
    {
        return view('livewire.admin.jenis-keringanan-biaya.form')->extends('layouts.web');
    }
}
