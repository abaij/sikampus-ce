<?php

namespace App\Livewire\Mahasiswa\TugasAkhir;

use App\Models\Mahasiswa;
use App\Models\TugasAkhir;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public int $mahasiswaId;

    #[Locked]
    public int $tugasAkhirId;

    public function mount(int $id): void
    {
        $mahasiswa = Mahasiswa::where('id_user', Auth::id())->firstOrFail();
        $this->mahasiswaId = $mahasiswa->id;

        $tugasAkhir = TugasAkhir::find($id);
        abort_if($tugasAkhir === null, 404);
        abort_unless((int) $tugasAkhir->id_mahasiswa === $this->mahasiswaId, 403, 'Anda tidak memiliki akses ke data tugas akhir ini.');

        $this->tugasAkhirId = $id;
    }

    /**
     * Sama persis dengan TugasAkhirController::showTugasAkhirMahasiswa.
     */
    #[Computed]
    public function tugasAkhir(): TugasAkhir
    {
        return TugasAkhir::with(['semester', 'pembimbing.dosen', 'statusLogs.user'])
            ->findOrFail($this->tugasAkhirId);
    }

    #[Computed]
    public function canEdit(): bool
    {
        return in_array($this->tugasAkhir->status, ['draft', 'rejected', 'returned'], true);
    }

    public function render()
    {
        return view('livewire.mahasiswa.tugas-akhir.show')->extends('layouts.mahasiswa');
    }
}
