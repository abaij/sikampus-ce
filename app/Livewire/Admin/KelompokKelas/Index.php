<?php

namespace App\Livewire\Admin\KelompokKelas;

use App\Models\KelompokKelas;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public int $perPage = 10;

    public ?int $confirmingDeleteId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(): void
    {
        if (! $this->confirmingDeleteId) {
            return;
        }

        KelompokKelas::findOrFail($this->confirmingDeleteId)->delete();

        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    /**
     * Sama persis dengan KelompokKelasController::index.
     */
    public function render()
    {
        $query = KelompokKelas::query()->with('prodi')->withCount('mahasiswa as jumlah_mahasiswa');

        if ($this->search !== '') {
            $query->where('nama', 'like', "%{$this->search}%");
        }

        $kelompokKelasList = $query->orderBy('nama')->paginate($this->perPage);

        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.kelompok-kelas.index', [
            'kelompokKelasList' => $kelompokKelasList,
        ])->extends('layouts.web');
    }
}
