<?php

namespace App\Livewire\Admin\Jenjang;

use App\Models\Jenjang;
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
        abort_unless(PanelAccess::can(Auth::user(), 'jenjang', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus jenjang.');

        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'jenjang', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus jenjang.');

        if (! $this->confirmingDeleteId) {
            return;
        }

        Jenjang::findOrFail($this->confirmingDeleteId)->delete();

        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    /**
     * Sama persis dengan JenjangController::index.
     */
    public function render()
    {
        $query = Jenjang::query();

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('kode', 'like', "%{$this->search}%")
                    ->orWhere('deskripsi', 'like', "%{$this->search}%");
            });
        }

        $jenjangList = $query->orderBy('nama')->paginate($this->perPage);

        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.jenjang.index', [
            'jenjangList' => $jenjangList,
        ])->extends('layouts.web');
    }
}
