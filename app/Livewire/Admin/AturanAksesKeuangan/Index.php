<?php

namespace App\Livewire\Admin\AturanAksesKeuangan;

use App\Models\AturanAksesKeuangan;
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
        abort_unless(PanelAccess::can(Auth::user(), 'aturan akses keuangan', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus aturan akses keuangan.');

        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    /**
     * Sama persis dengan AturanAksesKeuanganController::destroy — tidak ada pengecekan
     * pemakaian sebelum hapus. Model ini soft-delete, jadi FK restrictOnDelete di
     * keringanan_biaya tidak pernah terpicu (baris tidak benar-benar dihapus dari DB).
     */
    public function delete(): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'aturan akses keuangan', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus aturan akses keuangan.');

        if (! $this->confirmingDeleteId) {
            return;
        }

        AturanAksesKeuangan::findOrFail($this->confirmingDeleteId)->delete();

        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    /**
     * Sama persis dengan AturanAksesKeuanganController::index.
     */
    public function render()
    {
        $query = AturanAksesKeuangan::query();

        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('kode_akses', 'like', "%{$search}%")
                    ->orWhere('nama', 'like', "%{$search}%");
            });
        }

        $aturanAksesKeuanganList = $query->orderBy('kode_akses')->paginate($this->perPage);

        return view('livewire.admin.aturan-akses-keuangan.index', [
            'aturanAksesKeuanganList' => $aturanAksesKeuanganList,
        ])->extends('layouts.web');
    }
}
