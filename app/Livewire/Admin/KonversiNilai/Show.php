<?php

namespace App\Livewire\Admin\KonversiNilai;

use App\Models\KonversiNilai;
use App\Models\Mahasiswa;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    public int $mahasiswaId;

    public function mount(int $id): void
    {
        $this->mahasiswaId = $id;

        $mahasiswa = Mahasiswa::findOrFail($id);

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $mahasiswa->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke data mahasiswa ini.');
            }
        }
    }

    #[Computed]
    public function mahasiswa()
    {
        return Mahasiswa::with('prodi:id,nama,kode')->findOrFail($this->mahasiswaId);
    }

    /**
     * Sama persis dengan KonversiNilaiController::rincianMahasiswa.
     */
    #[Computed]
    public function items()
    {
        return KonversiNilai::where('id_mahasiswa', $this->mahasiswaId)
            ->whereNull('deleted_at')
            ->with(['kurikulum:id,kode,nama,status', 'jenisKonversi:id,nama'])
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function ringkasan(): array
    {
        return [
            'jumlah_matkul' => $this->items->count(),
            'total_sks_lama' => (int) $this->items->sum('sks_lama'),
            'total_sks_baru' => (int) $this->items->sum('sks_baru'),
        ];
    }

    public function render()
    {
        return view('livewire.admin.konversi-nilai.show')->extends('layouts.web');
    }
}
