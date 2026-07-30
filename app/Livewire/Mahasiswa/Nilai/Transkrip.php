<?php

namespace App\Livewire\Mahasiswa\Nilai;

use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Nilai;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Transkrip extends Component
{
    #[Locked]
    public int $mahasiswaId;

    public function mount(): void
    {
        $mahasiswa = Mahasiswa::where('id_user', Auth::id())->firstOrFail();
        $this->mahasiswaId = $mahasiswa->id;
    }

    /**
     * Sama persis dengan NilaiController::buildTranskripLengkapPayload — hanya mata kuliah yang
     * sudah punya nilai final (huruf_mutu) yang ikut tampil, berbeda dengan Nilai Semester yang
     * menampilkan seluruh KRS tersetujui termasuk yang belum dinilai.
     */
    #[Computed]
    public function data(): array
    {
        $krsList = Krs::with([
            'kelas.kurikulumMatkul.matkul',
            'kelas.semester',
        ])
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->whereNotNull('approved_at')
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->get();

        $krsIds = $krsList->pluck('id')->all();
        $nilaiMap = $krsIds === []
            ? collect()
            : Nilai::whereIn('id_krs', $krsIds)
                ->whereNull('deleted_at')
                ->where('is_final', true)
                ->get()
                ->keyBy('id_krs');

        $mataKuliahList = [];
        $totalSks = 0;
        $totalAngkaMutu = 0;
        $totalSksDenganNilai = 0;

        foreach ($krsList as $krs) {
            $matkul = $krs->kelas->kurikulumMatkul->matkul ?? null;
            $semester = $krs->kelas->semester ?? null;
            $nilai = $nilaiMap->get($krs->id);

            if (! $matkul || ! $semester) {
                continue;
            }
            if (! $nilai || ! $nilai->huruf_mutu) {
                continue;
            }

            $sks = $matkul->sks ?? 0;
            $totalSks += $sks;

            $angkaMutu = $nilai->angka_mutu;
            $isFinal = $nilai->is_final;

            if ($isFinal && $angkaMutu !== null && $sks > 0) {
                $totalAngkaMutu += $angkaMutu * $sks;
                $totalSksDenganNilai += $sks;
            }

            $mataKuliahList[] = [
                'id_krs' => $krs->id,
                'matkul' => $matkul,
                'semester' => $semester,
                'nilai' => $nilai,
            ];
        }

        usort($mataKuliahList, function (array $a, array $b) {
            $cmp = $a['semester']->id <=> $b['semester']->id;

            return $cmp !== 0 ? $cmp : strcmp((string) $a['matkul']->kode, (string) $b['matkul']->kode);
        });

        return [
            'mata_kuliah' => $mataKuliahList,
            'statistik' => [
                'total_sks' => $totalSks,
                'total_sks_dengan_nilai' => $totalSksDenganNilai,
                'ipk' => $totalSksDenganNilai > 0 ? round($totalAngkaMutu / $totalSksDenganNilai, 2) : null,
            ],
        ];
    }

    public function render()
    {
        return view('livewire.mahasiswa.nilai.transkrip')->extends('layouts.mahasiswa');
    }
}
