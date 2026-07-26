<?php

namespace App\Livewire\Admin\JenisDaftar;

use App\Models\JenisDaftar;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?int $jenisDaftarId = null;

    public string $nama = '';

    public string $deskripsi = '';

    public function mount(?int $id = null): void
    {
        $this->jenisDaftarId = $id;

        if ($id === null) {
            return;
        }

        $jenisDaftar = JenisDaftar::findOrFail($id);

        $this->nama = $jenisDaftar->nama;
        $this->deskripsi = (string) $jenisDaftar->deskripsi;
    }

    /**
     * Rule sama persis dengan JenisDaftarController::store/update.
     */
    protected function rules(): array
    {
        $uniqueNama = Rule::unique('jenis_daftar', 'nama');

        if ($this->jenisDaftarId) {
            $uniqueNama = $uniqueNama->ignore($this->jenisDaftarId);
        }

        return [
            'nama' => ['required', 'string', 'max:255', $uniqueNama],
            'deskripsi' => ['nullable', 'string'],
        ];
    }

    public function save()
    {
        $validated = $this->validate();

        if ($validated['deskripsi'] === '') {
            $validated['deskripsi'] = null;
        }

        if ($this->jenisDaftarId) {
            JenisDaftar::findOrFail($this->jenisDaftarId)->update($validated);
        } else {
            JenisDaftar::create($validated);
        }

        session()->flash('status', 'Jenis daftar berhasil disimpan.');

        return redirect()->route('admin.jenis-daftar.index');
    }

    public function render()
    {
        return view('livewire.admin.jenis-daftar.form')->extends('layouts.web');
    }
}
