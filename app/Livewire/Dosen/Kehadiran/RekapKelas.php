<?php

namespace App\Livewire\Dosen\Kehadiran;

use App\Models\Dosen;
use App\Models\JadwalDosen;
use App\Models\Kelas;
use App\Models\KelasDosen;
use App\Services\KehadiranRekapService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RekapKelas extends Component
{
    #[Locked]
    public int $kelasId;

    #[Locked]
    public int $dosenId;

    public function mount(int $id): void
    {
        $dosen = Dosen::where('id_user', Auth::id())->firstOrFail();
        $this->dosenId = $dosen->id;

        $kelas = Kelas::find($id);
        abort_unless($kelas, 404, 'Kelas tidak ditemukan.');
        abort_unless($this->dosenHasAccess($kelas), 403, 'Anda tidak memiliki akses ke kelas ini.');

        $this->kelasId = $id;
    }

    /**
     * Sama persis dengan KehadiranController::dosenBisaAksesKelas.
     */
    private function dosenHasAccess(Kelas $kelas): bool
    {
        if ((int) $kelas->id_dosen_pic === $this->dosenId) {
            return true;
        }

        if (KelasDosen::where('id_dosen', $this->dosenId)->where('id_kelas', $kelas->id)->whereNull('deleted_at')->exists()) {
            return true;
        }

        return JadwalDosen::where('id_dosen', $this->dosenId)
            ->where('status', 'active')
            ->whereHas('jadwal', fn ($q) => $q->where('id_kelas', $kelas->id)->whereNull('deleted_at'))
            ->exists();
    }

    #[Computed]
    public function kelas(): Kelas
    {
        return Kelas::with(['kurikulumMatkul.matkul', 'prodi.jenjang', 'semester'])->findOrFail($this->kelasId);
    }

    #[Computed]
    public function rekap(): array
    {
        return KehadiranRekapService::build($this->kelas);
    }

    public function render()
    {
        return view('livewire.dosen.kehadiran.rekap-kelas')->extends('layouts.dosen');
    }
}
