<?php

namespace App\Livewire\Mahasiswa\BimbinganTugasAkhir;

use App\Models\Mahasiswa;
use App\Models\TugasAkhir;
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
     * Sama persis dengan TugasAkhirController::bimbinganIndexMahasiswa.
     */
    #[Computed]
    public function data(): array
    {
        $hasAnyTa = TugasAkhir::where('id_mahasiswa', $this->mahasiswaId)->exists();

        if (! $hasAnyTa) {
            return [
                'has_tugas_akhir' => false,
                'tugas_akhir_disetujui' => collect(),
                'pesan_belum_ajukan' => 'Anda belum memiliki data tugas akhir. Silakan mengajukan tugas akhir terlebih dahulu melalui menu Pengajuan Tugas Akhir.',
                'pesan_tanpa_disetujui' => null,
            ];
        }

        $tugasAkhirApproved = TugasAkhir::with('semester')
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->where('status', 'approved')
            ->orderByDesc('id')
            ->get()
            ->map(function (TugasAkhir $t) {
                $t->bimbingan_count = $t->bimbingan()->count();

                return $t;
            });

        return [
            'has_tugas_akhir' => true,
            'tugas_akhir_disetujui' => $tugasAkhirApproved,
            'pesan_belum_ajukan' => null,
            'pesan_tanpa_disetujui' => $tugasAkhirApproved->isEmpty()
                ? 'Belum ada pengajuan tugas akhir yang berstatus disetujui. Setelah judul disetujui, tugas akhir akan tampil di sini dan Anda dapat melihat riwayat bimbingan.'
                : null,
        ];
    }

    public function render()
    {
        return view('livewire.mahasiswa.bimbingan-tugas-akhir.index')->extends('layouts.mahasiswa');
    }
}
