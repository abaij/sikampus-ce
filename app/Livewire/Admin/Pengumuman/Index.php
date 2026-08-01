<?php

namespace App\Livewire\Admin\Pengumuman;

use App\Models\Pengumuman;
use App\Support\PanelAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    // #[Url] supaya state ini bisa dibaca ulang lewat query string ketika user kembali dari
    // halaman ubah (lihat Pengumuman\Concerns\ForwardsIndexState).
    #[Url(as: 'search')]
    public string $search = '';

    // Properti filter yang terikat <select> harus string, bukan ?enum — lihat catatan di SKILL.md.
    #[Url(as: 'audien')]
    public string $filterAudien = '';

    #[Url(as: 'prioritas')]
    public string $filterPrioritas = '';

    #[Url(as: 'status')]
    public string $filterStatus = '';

    public int $perPage = 10;

    public ?int $confirmingDeleteId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterAudien(): void
    {
        $this->resetPage();
    }

    public function updatingFilterPrioritas(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        // Tombol pemicu ini disembunyikan di Blade untuk user tanpa hak hapus, tapi method
        // Livewire tetap bisa dipanggil langsung lewat request yang dipalsukan — pengecekan di
        // sini dan di delete() adalah otoritas sebenarnya, bukan sekadar UI.
        abort_unless(PanelAccess::can(Auth::user(), 'pengumuman', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus pengumuman.');

        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    /**
     * Sama persis dengan PengumumanController::destroy.
     */
    public function delete(): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'pengumuman', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus pengumuman.');

        if (! $this->confirmingDeleteId) {
            return;
        }

        $pengumuman = Pengumuman::findOrFail($this->confirmingDeleteId);
        $pengumuman->deleted_by = auth()->id();
        $pengumuman->save();
        $pengumuman->delete();

        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    /**
     * Sama persis dengan PengumumanController::index.
     */
    public function render()
    {
        $query = Pengumuman::query();

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('judul', 'like', "%{$this->search}%")
                    ->orWhere('isi', 'like', "%{$this->search}%");
            });
        }

        if ($this->filterAudien !== '') {
            $query->where('audien', $this->filterAudien);
        }

        if ($this->filterPrioritas !== '') {
            $query->where('prioritas', $this->filterPrioritas);
        }

        $now = now();
        if ($this->filterStatus === 'aktif') {
            $query->where(function ($q) use ($now) {
                $q->whereNull('tanggal_mulai')
                    ->orWhere('tanggal_mulai', '<=', $now);
            })->where(function ($q) use ($now) {
                $q->whereNull('tanggal_selesai')
                    ->orWhere('tanggal_selesai', '>=', $now);
            });
        } elseif ($this->filterStatus === 'akan_datang') {
            $query->where('tanggal_mulai', '>', $now);
        } elseif ($this->filterStatus === 'selesai') {
            $query->whereNotNull('tanggal_selesai')
                ->where('tanggal_selesai', '<', $now);
        }

        $pengumumanList = $query->orderByDesc('created_at')->paginate($this->perPage);

        // Diselipkan ke link "Ubah" supaya tombol Batal di halaman ubah bisa mendarat di
        // halaman/filter yang sama persis — lihat Pengumuman\Concerns\ForwardsIndexState.
        $returnParams = array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'audien' => $this->filterAudien !== '' ? $this->filterAudien : null,
            'prioritas' => $this->filterPrioritas !== '' ? $this->filterPrioritas : null,
            'status' => $this->filterStatus !== '' ? $this->filterStatus : null,
            'page' => $pengumumanList->currentPage() > 1 ? $pengumumanList->currentPage() : null,
        ], fn ($value) => $value !== null);

        return view('livewire.admin.pengumuman.index', [
            'pengumumanList' => $pengumumanList,
            'returnQuery' => http_build_query($returnParams),
        ])->extends('layouts.web');
    }
}
