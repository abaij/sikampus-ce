<?php

namespace App\Livewire\Admin\KategoriBiaya;

use App\Models\KategoriBiaya;
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

        KategoriBiaya::findOrFail($this->confirmingDeleteId)->delete();

        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    /**
     * Sama persis dengan KategoriBiayaController::index.
     */
    public function render()
    {
        $query = KategoriBiaya::withCount([
            'kategori_biaya_mahasiswa as jumlah_mahasiswa' => function ($q) {
                $q->where('status', 'active');
            },
        ]);

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('kode', 'like', "%{$this->search}%");
            });
        }

        $kategoriBiayaList = $query->orderBy('nama')->paginate($this->perPage);

        return view('livewire.admin.kategori-biaya.index', [
            'kategoriBiayaList' => $kategoriBiayaList,
        ])->extends('layouts.web');
    }
}
