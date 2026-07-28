<?php

namespace App\Livewire\Admin\JenisKeringananBiaya;

use App\Models\JenisKeringananBiaya;
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

        JenisKeringananBiaya::findOrFail($this->confirmingDeleteId)->delete();

        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    /**
     * Sama persis dengan JenisKeringananBiayaController::index.
     */
    public function render()
    {
        $query = JenisKeringananBiaya::query();

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('keterangan', 'like', "%{$this->search}%");
            });
        }

        $jenisKeringananBiayaList = $query->orderBy('nama')->paginate($this->perPage);

        return view('livewire.admin.jenis-keringanan-biaya.index', [
            'jenisKeringananBiayaList' => $jenisKeringananBiayaList,
        ])->extends('layouts.web');
    }
}
