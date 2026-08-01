<?php

namespace App\Livewire\Admin\Dosen;

use App\Models\Dosen;
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
        abort_unless(PanelAccess::can(Auth::user(), 'dosen', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus dosen.');

        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'dosen', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus dosen.');

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

        // Diselipkan ke link "Lihat"/"Ubah" supaya tombol Kembali di halaman detail/ubah bisa
        // mendarat di halaman/pencarian yang sama persis — lihat Dosen\Concerns\ForwardsIndexState.
        $returnParams = array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'page' => $dosenList->currentPage() > 1 ? $dosenList->currentPage() : null,
        ], fn ($value) => $value !== null);

        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.dosen.index', [
            'dosenList' => $dosenList,
            'returnQuery' => http_build_query($returnParams),
        ])->extends('layouts.web');
    }
}
