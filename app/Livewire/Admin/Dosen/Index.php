<?php

namespace App\Livewire\Admin\Dosen;

use App\Models\Dosen;
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

        Dosen::findOrFail($this->confirmingDeleteId)->delete();

        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    /**
     * Sama persis dengan DosenController::index — tidak ada scope-filter di sana
     * (dosen tidak punya kolom id_fakultas/id_prodi langsung), jadi di sini juga tidak ada.
     */
    public function render()
    {
        $query = Dosen::query();

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                    ->orWhere('kode_dosen', 'like', "%{$this->search}%")
                    ->orWhere('nip', 'like', "%{$this->search}%")
                    ->orWhere('nidn', 'like', "%{$this->search}%");
            });
        }

        $query->withCount('mahasiswaBimbingan');

        $dosenList = $query->orderBy('nama')->paginate($this->perPage);

        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.dosen.index', [
            'dosenList' => $dosenList,
        ])->extends('layouts.web');
    }
}
