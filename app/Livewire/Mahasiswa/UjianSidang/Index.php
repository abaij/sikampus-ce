<?php

namespace App\Livewire\Mahasiswa\UjianSidang;

use App\Models\Mahasiswa;
use App\Models\Semester;
use App\Models\TugasAkhir;
use App\Models\UjianSidang;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Index extends Component
{
    #[Locked]
    public int $mahasiswaId;

    public function mount(): void
    {
        $mahasiswa = Mahasiswa::where('id_user', Auth::id())->firstOrFail();
        $this->mahasiswaId = $mahasiswa->id;
    }

    /**
     * Sama persis dengan TugasAkhirController::ujianSidangContextMahasiswa.
     */
    #[Computed]
    public function ctx(): array
    {
        $tugasAkhirTerbaru = TugasAkhir::with('semester')
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->orderByDesc('id')
            ->first();

        if (! $tugasAkhirTerbaru) {
            return [
                'has_tugas_akhir' => false,
                'eligible_pengajuan' => false,
                'pesan_tidak_eligible' => 'Anda belum memiliki data tugas akhir. Ajukan judul tugas akhir terlebih dahulu.',
                'ujian_sidang' => collect(),
            ];
        }

        $tugasAkhirApproved = TugasAkhir::where('id_mahasiswa', $this->mahasiswaId)
            ->where('status', 'approved')
            ->orderByDesc('id')
            ->get();

        $ujianSidang = UjianSidang::whereHas('tugasAkhir', fn ($q) => $q->where('id_mahasiswa', $this->mahasiswaId))
            ->with(['semester', 'penguji.dosen', 'tugasAkhir'])
            ->orderByDesc('id')
            ->get();

        $semesterIdsUsedPerTa = $ujianSidang->groupBy('id_tugas_akhir')
            ->map(fn ($rows) => $rows->pluck('id_semester')->unique()->all());

        $allSemesters = Semester::orderByDesc('kode')->get(['id', 'kode', 'nama', 'is_active']);

        $unionSemesterIds = [];
        foreach ($tugasAkhirApproved as $ta) {
            $used = $semesterIdsUsedPerTa->get($ta->id, []);
            foreach ($allSemesters as $s) {
                if (! in_array($s->id, $used, true)) {
                    $unionSemesterIds[$s->id] = true;
                }
            }
        }

        $eligible = $tugasAkhirApproved->isNotEmpty() && count($unionSemesterIds) > 0;

        $pesanTidakEligible = null;
        if ($tugasAkhirApproved->isEmpty()) {
            $pesanTidakEligible = 'Pengajuan ujian sidang hanya dapat dilakukan setelah judul tugas akhir Anda disetujui (status: disetujui).';
        } elseif (! $eligible) {
            $pesanTidakEligible = 'Semua semester yang tersedia sudah digunakan untuk pengajuan ujian sidang pada tugas akhir yang disetujui.';
        }

        return [
            'has_tugas_akhir' => true,
            'eligible_pengajuan' => $eligible,
            'pesan_tidak_eligible' => $pesanTidakEligible,
            'ujian_sidang' => $ujianSidang,
        ];
    }

    public function render()
    {
        return view('livewire.mahasiswa.ujian-sidang.index')->extends('layouts.mahasiswa');
    }
}
