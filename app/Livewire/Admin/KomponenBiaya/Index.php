<?php

namespace App\Livewire\Admin\KomponenBiaya;

use App\Models\KomponenBiaya;
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

        KomponenBiaya::findOrFail($this->confirmingDeleteId)->delete();

        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    /**
     * Sama persis dengan KomponenBiayaController::index.
     */
    public function render()
    {
        $query = KomponenBiaya::query();

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('kode', 'like', "%{$this->search}%");
            });
        }

        $komponenBiayaList = $query->orderBy('kode')->paginate($this->perPage);

        return view('livewire.admin.komponen-biaya.index', [
            'komponenBiayaList' => $komponenBiayaList,
        ])->extends('layouts.web');
    }
}
