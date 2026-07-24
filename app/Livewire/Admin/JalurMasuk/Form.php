<?php

namespace App\Livewire\Admin\JalurMasuk;

use App\Models\JalurMasuk;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?int $jalurMasukId = null;

    public string $nama = '';

    public string $deskripsi = '';

    // Default sama dengan default kolom di migration create_jalur_masuk_table.
    public bool $is_free_of_charge = false;

    public bool $has_selection = true;

    public bool $has_interview = true;

    public bool $has_physical_test = false;

    public bool $has_psychological_test = false;

    public bool $has_medical_test = false;

    public string $status = 'active';

    public function mount(?int $id = null): void
    {
        $this->jalurMasukId = $id;

        if ($id === null) {
            return;
        }

        $jalurMasuk = JalurMasuk::findOrFail($id);

        $this->nama = $jalurMasuk->nama;
        $this->deskripsi = (string) $jalurMasuk->deskripsi;
        $this->is_free_of_charge = (bool) $jalurMasuk->is_free_of_charge;
        $this->has_selection = (bool) $jalurMasuk->has_selection;
        $this->has_interview = (bool) $jalurMasuk->has_interview;
        $this->has_physical_test = (bool) $jalurMasuk->has_physical_test;
        $this->has_psychological_test = (bool) $jalurMasuk->has_psychological_test;
        $this->has_medical_test = (bool) $jalurMasuk->has_medical_test;
        $this->status = $jalurMasuk->status ?? 'active';
    }

    /**
     * Rule sama persis dengan JalurMasukController::store/update.
     */
    protected function rules(): array
    {
        $uniqueNama = Rule::unique('jalur_masuk', 'nama');

        if ($this->jalurMasukId) {
            $uniqueNama = $uniqueNama->ignore($this->jalurMasukId);
        }

        return [
            'nama' => ['required', 'string', 'max:255', $uniqueNama],
            'deskripsi' => ['nullable', 'string'],
            'is_free_of_charge' => ['boolean'],
            'has_selection' => ['boolean'],
            'has_interview' => ['boolean'],
            'has_physical_test' => ['boolean'],
            'has_psychological_test' => ['boolean'],
            'has_medical_test' => ['boolean'],
            'status' => ['string', Rule::in(['active', 'inactive'])],
        ];
    }

    public function save()
    {
        $validated = $this->validate();

        if ($validated['deskripsi'] === '') {
            $validated['deskripsi'] = null;
        }

        if ($this->jalurMasukId) {
            JalurMasuk::findOrFail($this->jalurMasukId)->update($validated);
        } else {
            JalurMasuk::create($validated);
        }

        session()->flash('status', 'Jalur masuk berhasil disimpan.');

        return redirect()->route('admin.jalur-masuk.index');
    }

    public function render()
    {
        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.jalur-masuk.form')->extends('layouts.web');
    }
}
