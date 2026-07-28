<?php

namespace App\Livewire\Admin\Wisuda;

use App\Models\Wisuda;
use Illuminate\Support\Facades\Auth;
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

    /**
     * Sama persis dengan WisudaController::destroy — soft delete plus jejak deleted_by.
     */
    public function delete(): void
    {
        if (! $this->confirmingDeleteId) {
            return;
        }

        $wisuda = Wisuda::findOrFail($this->confirmingDeleteId);
        $wisuda->deleted_by = $this->actor();
        $wisuda->save();
        $wisuda->delete();

        $this->confirmingDeleteId = null;
        $this->resetPage();
        session()->flash('status', 'Data wisuda dihapus.');
    }

    private function actor(): string
    {
        $user = Auth::user();

        return $user ? ($user->name ?? (string) ($user->email ?? $user->id)) : 'system';
    }

    /**
     * Sama persis dengan WisudaController::index.
     */
    public function render()
    {
        $query = Wisuda::query()->withCount('wisudaMahasiswa as jumlah_mahasiswa');

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('nama', 'like', '%'.$this->search.'%')
                    ->orWhere('keterangan', 'like', '%'.$this->search.'%');
            });
        }

        $wisudaList = $query->orderByDesc('tanggal_wisuda')->orderBy('nama')->paginate($this->perPage);

        return view('livewire.admin.wisuda.index', [
            'wisudaList' => $wisudaList,
        ])->extends('layouts.web');
    }
}
