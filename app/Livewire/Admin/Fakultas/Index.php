<?php

namespace App\Livewire\Admin\Fakultas;

use App\Models\Fakultas;
use App\Support\PanelAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public int $perPage = 10;

    public ?int $confirmingDeleteId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        // Tombol pemicu ini disembunyikan di Blade untuk user tanpa hak hapus, tapi method
        // Livewire tetap bisa dipanggil langsung lewat request yang dipalsukan — pengecekan di
        // sini dan di delete() adalah otoritas sebenarnya, bukan sekadar UI.
        abort_unless(PanelAccess::can(Auth::user(), 'fakultas', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus fakultas.');

        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    /**
     * Sama seperti FakultasController::destroy — scope-filter dicek ulang di sini
     * (bukan cuma disembunyikan dari daftar) supaya user scoped tidak bisa hapus lewat id langsung.
     */
    public function delete(): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'fakultas', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus fakultas.');

        if (! $this->confirmingDeleteId) {
            return;
        }

        $fakultas = Fakultas::findOrFail($this->confirmingDeleteId);

        $user = Auth::user();
        if ($user && $user->hasFakultasScope()) {
            $fakultasScopeIds = $user->getFakultasScopeIds();
            if (! empty($fakultasScopeIds) && ! in_array((int) $fakultas->id, $fakultasScopeIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke fakultas ini.');
            }
        }

        $fakultas->delete();

        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    /**
     * Sama persis dengan FakultasController::index — query + scope-filter disalin,
     * bukan diekstrak jadi shared service (lihat rencana implementasi).
     */
    public function render()
    {
        $query = Fakultas::query()->with('dekan');

        $user = Auth::user();
        if ($user && $user->hasFakultasScope()) {
            $fakultasScopeIds = $user->getFakultasScopeIds();
            if (! empty($fakultasScopeIds)) {
                $query->whereIn('id', $fakultasScopeIds);
            }
        }

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('kode', 'like', "%{$this->search}%");
            });
        }

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        $fakultasList = $query->orderBy('nama')->paginate($this->perPage);

        // ->extends() (bukan #[Layout] attribute) karena layouts.web pakai @yield/@section
        // ("extends" style) — #[Layout] mem-bungkus lewat @component/@slot dan diam-diam
        // membuang isi komponen karena layouts.web tidak punya {{ $slot }}.
        return view('livewire.admin.fakultas.index', [
            'fakultasList' => $fakultasList,
        ])->extends('layouts.web');
    }
}
