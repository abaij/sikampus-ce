<?php

namespace App\Livewire\Admin\JenisDaftar;

use App\Models\JenisDaftar;
use App\Support\PanelAccess;
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
        abort_unless(PanelAccess::can(Auth::user(), 'jenis pendaftaran', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus jenis daftar.');

        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'jenis pendaftaran', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus jenis daftar.');

        if (! $this->confirmingDeleteId) {
            return;
        }

        JenisDaftar::findOrFail($this->confirmingDeleteId)->delete();

        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    /**
     * Sama persis dengan JenisDaftarController::index.
     */
    public function render()
    {
        $query = JenisDaftar::query();

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('deskripsi', 'like', "%{$this->search}%");
            });
        }

        $jenisDaftarList = $query->orderBy('nama')->paginate($this->perPage);

        return view('livewire.admin.jenis-daftar.index', [
            'jenisDaftarList' => $jenisDaftarList,
        ])->extends('layouts.web');
    }
}
