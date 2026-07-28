<?php

namespace App\Livewire\Admin\Pengumuman;

use App\Livewire\Admin\Pengumuman\Concerns\ForwardsIndexState;
use App\Models\Pengumuman;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    use ForwardsIndexState;

    public ?int $pengumumanId = null;

    public string $judul = '';

    public string $isi = '';

    public string $audien = '';

    public string $prioritas = '';

    public string $tanggal_mulai = '';

    public string $tanggal_selesai = '';

    public function mount(?int $id = null): void
    {
        $this->pengumumanId = $id;
        $this->resolveBackUrl();

        if ($id === null) {
            return;
        }

        $pengumuman = Pengumuman::findOrFail($id);

        $this->judul = $pengumuman->judul;
        $this->isi = $pengumuman->isi;
        $this->audien = (string) $pengumuman->audien;
        $this->prioritas = (string) $pengumuman->prioritas;
        $this->tanggal_mulai = $pengumuman->tanggal_mulai?->format('Y-m-d\TH:i') ?? '';
        $this->tanggal_selesai = $pengumuman->tanggal_selesai?->format('Y-m-d\TH:i') ?? '';
    }

    /**
     * Rule sama persis dengan PengumumanController::store/update.
     */
    protected function rules(): array
    {
        $judulIsiPrefix = $this->pengumumanId ? ['sometimes'] : [];

        return [
            'judul' => [...$judulIsiPrefix, 'required', 'string', 'max:255'],
            'isi' => [...$judulIsiPrefix, 'required', 'string'],
            'audien' => ['nullable', 'string', Rule::in(['mahasiswa', 'dosen', 'staff', 'alumni'])],
            'prioritas' => ['nullable', 'string', Rule::in(['low', 'medium', 'high'])],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ];
    }

    public function save()
    {
        $validated = $this->validate();

        foreach (['audien', 'prioritas', 'tanggal_mulai', 'tanggal_selesai'] as $field) {
            if ($validated[$field] === '') {
                $validated[$field] = null;
            }
        }

        if ($this->pengumumanId) {
            $validated['updated_by'] = auth()->id();
            Pengumuman::findOrFail($this->pengumumanId)->update($validated);
        } else {
            $validated['created_by'] = auth()->id();
            Pengumuman::create($validated);
        }

        session()->flash('status', 'Pengumuman berhasil disimpan.');

        return redirect()->to($this->backUrl);
    }

    public function render()
    {
        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.pengumuman.form')->extends('layouts.web');
    }
}
