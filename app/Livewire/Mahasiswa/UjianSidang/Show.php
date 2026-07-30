<?php

namespace App\Livewire\Mahasiswa\UjianSidang;

use App\Models\Mahasiswa;
use App\Models\UjianSidang;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public int $mahasiswaId;

    #[Locked]
    public int $ujianSidangId;

    public function mount(int $id): void
    {
        $mahasiswa = Mahasiswa::where('id_user', Auth::id())->firstOrFail();
        $this->mahasiswaId = $mahasiswa->id;

        $ujianSidang = UjianSidang::with('tugasAkhir')->find($id);
        abort_if($ujianSidang === null, 404);
        abort_unless(
            $ujianSidang->tugasAkhir && (int) $ujianSidang->tugasAkhir->id_mahasiswa === $this->mahasiswaId,
            403,
            'Anda tidak memiliki akses ke data ujian sidang ini.'
        );

        $this->ujianSidangId = $id;
    }

    /**
     * Sama persis dengan TugasAkhirController::showUjianSidangMahasiswa.
     */
    #[Computed]
    public function ujianSidang(): UjianSidang
    {
        return UjianSidang::with(['semester', 'penguji.dosen', 'tugasAkhir'])->findOrFail($this->ujianSidangId);
    }

    public function render()
    {
        return view('livewire.mahasiswa.ujian-sidang.show')->extends('layouts.mahasiswa');
    }
}
