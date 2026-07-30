<?php

namespace App\Livewire\Prodi\Dosen;

use App\Models\Dosen;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public int $perPage = 10;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Sama persis dengan DosenController::index (rute /prodi/dosen memakainya langsung,
     * lihat routes/api.php). Tidak ada scope-filter di controller — dosen tidak punya
     * kolom id_fakultas/id_prodi langsung — jadi kaprodi/sekprodi bisa melihat seluruh
     * dosen institusi lewat portal ini, bukan hanya dosen prodinya sendiri. Ini perilaku
     * API apa adanya, bukan celah yang ditambal di sini.
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

        $dosenList = $query->orderBy('nama')->paginate($this->perPage);

        return view('livewire.prodi.dosen.index', [
            'dosenList' => $dosenList,
        ])->extends('layouts.prodi');
    }
}
