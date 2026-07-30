<?php

namespace App\Livewire\Mahasiswa\Nilai;

use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Nilai;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Semester extends Component
{
    #[Locked]
    public int $mahasiswaId;

    public function mount(): void
    {
        $mahasiswa = Mahasiswa::where('id_user', Auth::id())->firstOrFail();
        $this->mahasiswaId = $mahasiswa->id;
    }

    /**
     * Sama persis dengan NilaiController::getTranskripMahasiswa.
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

        $transkripData = [];
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

            $sks = $matkul->sks ?? 0;
            $totalSks += $sks;

            $angkaMutu = $nilai?->angka_mutu;
            $isFinal = $nilai?->is_final ?? false;

            if ($isFinal && $angkaMutu !== null && $sks > 0) {
                $totalAngkaMutu += $angkaMutu * $sks;
                $totalSksDenganNilai += $sks;
            }

            $semesterId = $semester->id;
            if (! isset($transkripData[$semesterId])) {
                $transkripData[$semesterId] = [
                    'semester' => $semester,
                    'mata_kuliah' => [],
                    'total_sks' => 0,
                    'total_angka_mutu' => 0,
                    'total_sks_dengan_nilai' => 0,
                ];
            }

            $transkripData[$semesterId]['mata_kuliah'][] = [
                'id_krs' => $krs->id,
                'matkul' => $matkul,
                'nilai' => $nilai,
            ];

            $transkripData[$semesterId]['total_sks'] += $sks;
            if ($isFinal && $angkaMutu !== null) {
                $transkripData[$semesterId]['total_angka_mutu'] += $angkaMutu * $sks;
                $transkripData[$semesterId]['total_sks_dengan_nilai'] += $sks;
            }
        }

        foreach ($transkripData as &$row) {
            $row['ip_semester'] = $row['total_sks_dengan_nilai'] > 0
                ? round($row['total_angka_mutu'] / $row['total_sks_dengan_nilai'], 2)
                : null;
        }
        unset($row);

        usort($transkripData, fn ($a, $b) => $b['semester']->id <=> $a['semester']->id);

        return [
            'transkrip' => array_values($transkripData),
            'statistik' => [
                'total_sks' => $totalSks,
                'total_sks_dengan_nilai' => $totalSksDenganNilai,
                'ip_kumulatif' => $totalSksDenganNilai > 0 ? round($totalAngkaMutu / $totalSksDenganNilai, 2) : null,
            ],
        ];
    }

    public function render()
    {
        return view('livewire.mahasiswa.nilai.semester')->extends('layouts.mahasiswa');
    }
}
