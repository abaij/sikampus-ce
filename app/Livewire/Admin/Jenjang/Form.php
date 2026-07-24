<?php

namespace App\Livewire\Admin\Jenjang;

use App\Models\Jenjang;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?int $jenjangId = null;

    public string $kode = '';

    public string $nama = '';

    public string $deskripsi = '';

    public function mount(?int $id = null): void
    {
        $this->jenjangId = $id;

        if ($id === null) {
            return;
        }

        $jenjang = Jenjang::findOrFail($id);

        $this->kode = (string) $jenjang->kode;
        $this->nama = $jenjang->nama;
        $this->deskripsi = (string) $jenjang->deskripsi;
    }

    /**
     * Rule sama persis dengan JenjangController::store/update.
     */
    protected function rules(): array
    {
        $uniqueKode = Rule::unique('jenjang', 'kode');
        $uniqueNama = Rule::unique('jenjang', 'nama');

        if ($this->jenjangId) {
            $uniqueKode = $uniqueKode->ignore($this->jenjangId);
            $uniqueNama = $uniqueNama->ignore($this->jenjangId);
        }

        return [
            'kode' => ['nullable', 'string', 'max:50', $uniqueKode],
            'nama' => ['required', 'string', 'max:255', $uniqueNama],
            'deskripsi' => ['nullable', 'string'],
        ];
    }

    public function save()
    {
        $validated = $this->validate();

        foreach (['kode', 'deskripsi'] as $field) {
            if ($validated[$field] === '') {
                $validated[$field] = null;
            }
        }

        if ($this->jenjangId) {
            Jenjang::findOrFail($this->jenjangId)->update($validated);
        } else {
            Jenjang::create($validated);
        }

        session()->flash('status', 'Jenjang berhasil disimpan.');

        return redirect()->route('admin.jenjang.index');
    }

    public function render()
    {
        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.jenjang.form')->extends('layouts.web');
    }
}
