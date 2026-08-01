<?php

namespace App\Livewire\Admin\Ruangan;

use App\Models\Ruangan;
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
        // Tombol pemicu ini disembunyikan di Blade untuk user tanpa hak hapus, tapi method
        // Livewire tetap bisa dipanggil langsung lewat request yang dipalsukan — pengecekan di
        // sini dan di delete() adalah otoritas sebenarnya, bukan sekadar UI.
        abort_unless(PanelAccess::can(Auth::user(), 'ruangan', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus ruangan.');

        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'ruangan', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus ruangan.');

        if (! $this->confirmingDeleteId) {
            return;
        }

        Ruangan::findOrFail($this->confirmingDeleteId)->delete();

        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    /**
     * Sama persis dengan RuanganController::index.
     */
    public function render()
    {
        $query = Ruangan::query();

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('deskripsi', 'like', "%{$this->search}%");
            });
        }

        $ruanganList = $query->orderBy('nama')->paginate($this->perPage);

        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.ruangan.index', [
            'ruanganList' => $ruanganList,
        ])->extends('layouts.web');
    }
}
