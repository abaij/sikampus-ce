<?php

namespace App\Livewire\Admin\Ruangan;

use App\Models\Ruangan;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?int $ruanganId = null;

    public string $nama = '';

    // Kolom NOT NULL dengan default DB (lihat migration): default di sini juga, karena default
    // DB hanya berlaku kalau kolom di-OMIT dari INSERT, bukan saat dikirim eksplisit null.
    public int $kapasitas = 0;

    public string $deskripsi = '';

    public function mount(?int $id = null): void
    {
        $this->ruanganId = $id;

        if ($id === null) {
            return;
        }

        $ruangan = Ruangan::findOrFail($id);

        $this->nama = $ruangan->nama;
        $this->kapasitas = $ruangan->kapasitas ?? 0;
        $this->deskripsi = (string) $ruangan->deskripsi;
    }

    /**
     * Rule sama persis dengan RuanganController::store/update.
     */
    protected function rules(): array
    {
        $uniqueNama = Rule::unique('ruangan', 'nama');

        if ($this->ruanganId) {
            $uniqueNama = $uniqueNama->ignore($this->ruanganId);
        }

        return [
            'nama' => ['required', 'string', 'max:255', $uniqueNama],
            'kapasitas' => ['nullable', 'integer', 'min:0'],
            'deskripsi' => ['nullable', 'string'],
        ];
    }

    public function save()
    {
        $validated = $this->validate();

        if ($validated['deskripsi'] === '') {
            $validated['deskripsi'] = null;
        }

        if ($this->ruanganId) {
            Ruangan::findOrFail($this->ruanganId)->update($validated);
        } else {
            Ruangan::create($validated);
        }

        session()->flash('status', 'Ruangan berhasil disimpan.');

        return redirect()->route('admin.administrasi.ruangan');
    }

    public function render()
    {
        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.ruangan.form')->extends('layouts.web');
    }
}
