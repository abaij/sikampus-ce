<?php

namespace App\Livewire\Admin\DosenWali;

use App\Livewire\Admin\DosenWali\Concerns\ForwardsIndexState;
use App\Models\DosenWali;
use App\Models\Semester;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Riwayat extends Component
{
    use ForwardsIndexState;

    public int $dosenWaliId;

    // Terikat <select> filter semester — string, bukan ?int, karena opsi "Semua semester"
    // mengirim "" (lihat catatan TypeError di SKILL.md).
    public string $filterSemester = '';

    public function mount(int $id, int $dosenWaliId): void
    {
        // $id (id dosen) dipakai untuk breadcrumb "kembali" dan resolveBackToShowUrl; validasi
        // akses tetap lewat dosenWaliId.
        $this->dosenWaliId = $dosenWaliId;

        $item = DosenWali::with('mahasiswa')->findOrFail($dosenWaliId);
        $this->assertScope($item);

        $this->resolveBackToShowUrl($id);

        $semesterAktif = Semester::where('is_active', true)->first();
        $this->filterSemester = $semesterAktif ? (string) $semesterAktif->id : '';
    }

    /**
     * Sama dengan DosenWaliBimbinganController::assertDosenWaliInUserScope.
     */
    private function assertScope(DosenWali $item): void
    {
        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && (! $item->mahasiswa || ! in_array((int) $item->mahasiswa->id_prodi, $allowedProdiIds, true))) {
                abort(403, 'Anda tidak memiliki akses ke data bimbingan ini.');
            }
        }
    }

    #[Computed]
    public function dosenWali(): DosenWali
    {
        return DosenWali::with(['dosen', 'mahasiswa.prodi'])->findOrFail($this->dosenWaliId);
    }

    #[Computed]
    public function semesterOptions()
    {
        return Semester::orderByDesc('kode')->get(['id', 'kode', 'nama', 'is_active']);
    }

    /**
     * Sama dengan DosenWaliBimbinganController::index — read-only, filter opsional id_semester.
     */
    #[Computed]
    public function bimbinganRows()
    {
        $query = $this->dosenWali->bimbingan()->with('semester');

        if ($this->filterSemester !== '') {
            $query->where('id_semester', (int) $this->filterSemester);
        }

        return $query->orderByDesc('tanggal_bimbingan')->orderByDesc('id')->get();
    }

    public function render()
    {
        return view('livewire.admin.dosen-wali.riwayat')->extends('layouts.web');
    }
}
