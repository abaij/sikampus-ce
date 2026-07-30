<?php

namespace App\Livewire\Mahasiswa\Kehadiran;

use App\Models\Jadwal;
use App\Models\Kehadiran;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Perkuliahan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Index extends Component
{
    #[Locked]
    public int $mahasiswaId;

    public string $filterKelas = '';

    public function mount(): void
    {
        $mahasiswa = Mahasiswa::where('id_user', Auth::id())->firstOrFail();
        $this->mahasiswaId = $mahasiswa->id;

        $options = $this->kelasOptions;
        if ($options !== [] && $this->filterKelas === '') {
            $this->filterKelas = (string) array_key_first($options);
        }
    }

    /**
     * Kelas kontrak mahasiswa lintas semester, mengikuti krs-saya di siak-frontend — diurutkan
     * dari semester terbaru, satu baris per kelas (dedup kalau ada anomali data KRS ganda).
     */
    #[Computed]
    public function kelasOptions(): array
    {
        return Krs::with(['kelas.kurikulumMatkul.matkul', 'kelas.semester'])
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->whereNull('deleted_at')
            ->whereHas('kelas.semester')
            ->get()
            ->sortByDesc(fn (Krs $krs) => $krs->kelas->semester->id ?? 0)
            ->unique('id_kelas')
            ->mapWithKeys(function (Krs $krs) {
                $kelas = $krs->kelas;
                $matkul = $kelas->kurikulumMatkul->matkul ?? null;
                $label = trim(($matkul->kode ?? '').' '.($matkul->nama ?? 'Mata kuliah'));
                $label .= ' — '.$kelas->semester->nama;

                return [$kelas->id => $label];
            })
            ->all();
    }

    /**
     * Sama persis dengan KehadiranController::getRekapByKelasForMahasiswa.
     */
    #[Computed]
    public function rekap(): ?array
    {
        if ($this->filterKelas === '') {
            return null;
        }

        $idKelas = (int) $this->filterKelas;

        $hasKrs = Krs::where('id_mahasiswa', $this->mahasiswaId)
            ->where('id_kelas', $idKelas)
            ->whereNull('deleted_at')
            ->exists();
        abort_unless($hasKrs, 403, 'Anda tidak terdaftar di kelas ini.');

        $kelas = Kelas::with([
            'kurikulumMatkul.matkul',
            'prodi.jenjang',
            'semester',
            'dosenPic',
        ])->findOrFail($idKelas);

        $jadwalIds = Jadwal::where('id_kelas', $idKelas)->whereNull('deleted_at')->pluck('id')->all();

        [$perkuliahanList, $perkuliahanIdToCol] = $this->perkuliahanSortedForRekap($jadwalIds);

        $perkuliahanIds = $perkuliahanList->pluck('id')->all();
        $kehadiranById = $perkuliahanIds === []
            ? collect()
            : Kehadiran::whereIn('id_perkuliahan', $perkuliahanIds)
                ->where('id_mhs', $this->mahasiswaId)
                ->whereNull('deleted_at')
                ->get()
                ->keyBy('id_perkuliahan');

        $pertemuan = $perkuliahanList->map(function (Perkuliahan $p) use ($perkuliahanIdToCol, $kehadiranById) {
            $kh = $kehadiranById->get($p->id);

            return (object) [
                'id' => $p->id,
                'pertemuan_ke' => $perkuliahanIdToCol[(int) $p->id] ?? null,
                'tanggal' => $p->waktu_mulai?->format('Y-m-d'),
                'kehadiran' => $kh,
            ];
        })->values();

        $ringkasan = [
            'total_pertemuan' => $pertemuan->count(),
            'hadir' => 0,
            'izin' => 0,
            'sakit' => 0,
            'alfa' => 0,
            'belum_catat' => 0,
        ];

        foreach ($pertemuan as $row) {
            $st = $row->kehadiran?->status;
            if ($st === null || $st === '') {
                $ringkasan['belum_catat']++;
            } else {
                $key = strtolower((string) $st);
                if (array_key_exists($key, $ringkasan)) {
                    $ringkasan[$key]++;
                }
            }
        }

        return [
            'kelas' => $kelas,
            'pertemuan' => $pertemuan,
            'ringkasan' => $ringkasan,
        ];
    }

    /**
     * Sama persis dengan KehadiranController::perkuliahanSortedForRekap.
     *
     * @param  array<int, int>  $jadwalIds
     * @return array{0: Collection<int, Perkuliahan>, 1: array<int, int>}
     */
    private function perkuliahanSortedForRekap(array $jadwalIds): array
    {
        if ($jadwalIds === []) {
            return [collect(), []];
        }

        $list = Perkuliahan::whereIn('id_jadwal', $jadwalIds)
            ->whereNull('deleted_at')
            ->get()
            ->sortBy(fn (Perkuliahan $p) => [$p->waktu_mulai?->getTimestamp() ?? \PHP_INT_MAX, $p->id])
            ->values();

        $idToCol = [];
        foreach ($list as $i => $p) {
            $idToCol[(int) $p->id] = $i + 1;
        }

        return [$list, $idToCol];
    }

    public function render()
    {
        return view('livewire.mahasiswa.kehadiran.index')->extends('layouts.mahasiswa');
    }
}
