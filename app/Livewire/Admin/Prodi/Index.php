<?php

namespace App\Livewire\Admin\Prodi;

use App\Models\Fakultas;
use App\Models\Prodi;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $idFakultas = '';

    public int $perPage = 10;

    public ?int $confirmingDeleteId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingIdFakultas(): void
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
     * Sama seperti ProdiController::destroy — scope-filter dicek ulang di sini.
     */
    public function delete(): void
    {
        if (! $this->confirmingDeleteId) {
            return;
        }

        $prodi = Prodi::findOrFail($this->confirmingDeleteId);

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $prodi->id, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke program studi ini.');
            }
        }

        $prodi->delete();

        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    /**
     * Sama persis dengan ProdiController::index — query + scope-filter disalin,
     * bukan diekstrak jadi shared service (lihat rencana implementasi).
     */
    public function render()
    {
        $query = Prodi::with(['fakultas', 'kaprodi', 'sekprodi', 'jenjang', 'semesterAktif']);

        $user = Auth::user();
        $fakultasId = $this->idFakultas !== '' ? (int) $this->idFakultas : null;

        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereIn('id', $allowedProdiIds);
            }

            $fakultasScopeIds = $user->getFakultasScopeIds();
            if ($fakultasId && ! empty($fakultasScopeIds) && ! in_array($fakultasId, $fakultasScopeIds, true)) {
                $fakultasId = null;
            }
        }

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('kode', 'like', "%{$this->search}%");
            });
        }

        if ($fakultasId) {
            $query->where('id_fakultas', $fakultasId);
        }

        $prodiList = $query->orderBy('nama')->paginate($this->perPage);

        return view('livewire.admin.prodi.index', [
            'prodiList' => $prodiList,
            'fakultasOptions' => Fakultas::whereNull('deleted_at')->orderBy('nama')->get(['id', 'nama']),
        ])->extends('layouts.web'); // lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
    }
}
