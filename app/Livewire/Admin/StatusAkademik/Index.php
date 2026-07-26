<?php

namespace App\Livewire\Admin\StatusAkademik;

use App\Models\Mahasiswa;
use App\Models\StatusAkademik;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public int $perPage = 10;

    public ?int $confirmingDeleteId = null;

    public string $deleteError = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingDeleteId = $id;
        $this->deleteError = '';
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
        $this->deleteError = '';
    }

    /**
     * Sama persis dengan StatusAkademikController::destroy.
     */
    public function delete(): void
    {
        if (! $this->confirmingDeleteId) {
            return;
        }

        $statusAkademik = StatusAkademik::findOrFail($this->confirmingDeleteId);

        $dipakaiMahasiswa = Mahasiswa::where('id_status_akademik', $statusAkademik->id)->exists();
        if ($dipakaiMahasiswa) {
            $this->deleteError = 'Status akademik tidak dapat dihapus karena masih dipakai oleh data mahasiswa.';

            return;
        }

        $statusAkademik->delete();

        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    /**
     * Sama persis dengan StatusAkademikController::index.
     */
    public function render()
    {
        $query = StatusAkademik::query();

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('deskripsi', 'like', "%{$this->search}%");
            });
        }

        $statusAkademikList = $query->orderBy('nama')->paginate($this->perPage);

        return view('livewire.admin.status-akademik.index', [
            'statusAkademikList' => $statusAkademikList,
        ])->extends('layouts.web');
    }
}
