<?php

namespace App\Livewire\Mahasiswa\Jadwal;

use App\Models\Jadwal;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Perkuliahan;
use App\Models\Semester;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Index extends Component
{
    #[Locked]
    public int $mahasiswaId;

    public string $filterSemester = '';

    public string $filterKelas = '';

    private const HARI_ORDER = [
        'senin' => 1, 'selasa' => 2, 'rabu' => 3, 'kamis' => 4, 'jumat' => 5, 'sabtu' => 6, 'minggu' => 7,
    ];

    public function mount(): void
    {
        $mahasiswa = Mahasiswa::where('id_user', Auth::id())->firstOrFail();
        $this->mahasiswaId = $mahasiswa->id;

        $activeSemester = Semester::where('is_active', true)->whereNull('deleted_at')->first();
        $this->filterSemester = $activeSemester ? (string) $activeSemester->id : '';

        $this->autoSelectSingleKelas();
    }

    /**
     * Sama persis dengan KrsController::getJadwalKuliah — daftar semester yang punya KRS,
     * ditambah semester aktif kalau belum masuk daftar.
     */
    #[Computed]
    public function semesterOptions(): array
    {
        $krsSemesters = Krs::with('kelas.semester')
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->whereNull('deleted_at')
            ->whereHas('kelas.semester')
            ->get()
            ->pluck('kelas.semester')
            ->filter()
            ->unique('id')
            ->sortByDesc('id')
            ->values();

        $activeSemester = Semester::where('is_active', true)->whereNull('deleted_at')->first();
        if ($activeSemester && $krsSemesters->where('id', $activeSemester->id)->isEmpty()) {
            $krsSemesters->prepend($activeSemester);
        }

        return $krsSemesters->mapWithKeys(fn (Semester $s) => [
            $s->id => $s->kode ? "{$s->nama} ({$s->kode})" : $s->nama,
        ])->all();
    }

    /**
     * Kelas kontrak (KRS) mahasiswa untuk semester yang dipilih.
     */
    #[Computed]
    public function kelasOptions(): array
    {
        if ($this->filterSemester === '') {
            return [];
        }

        return $this->krsListForSemester((int) $this->filterSemester)
            ->mapWithKeys(function (Krs $krs) {
                $kelas = $krs->kelas;
                $matkul = $kelas->kurikulumMatkul->matkul ?? null;
                $label = trim(($matkul->kode ?? '').' '.($matkul->nama ?? 'Mata kuliah'));
                if ($kelas->nama) {
                    $label .= " — {$kelas->nama}";
                }

                return [$kelas->id => $label];
            })
            ->all();
    }

    /**
     * Sama persis dengan KrsController::getJadwalKuliah.
     */
    #[Computed]
    public function jadwalList(): Collection
    {
        if ($this->filterKelas === '') {
            return collect();
        }

        $idKelas = (int) $this->filterKelas;

        $jadwalRows = Jadwal::with([
            'kelas.kurikulumMatkul.matkul',
            'ruangan',
            'jenisKuliah',
            'dosen.dosen',
        ])
            ->where('id_kelas', $idKelas)
            ->whereNull('deleted_at')
            ->get();

        $krs = Krs::where('id_mahasiswa', $this->mahasiswaId)
            ->where('id_kelas', $idKelas)
            ->whereNull('deleted_at')
            ->first();

        $jadwalIds = $jadwalRows->pluck('id')->all();
        $perkuliahanRows = $jadwalIds === []
            ? collect()
            : Perkuliahan::whereIn('id_jadwal', $jadwalIds)->whereNull('deleted_at')->get();

        return $jadwalRows->map(function (Jadwal $jadwal) use ($krs, $perkuliahanRows) {
            $p = $this->findPerkuliahanForJadwalSlot($jadwal, $perkuliahanRows);
            $sesi = $this->sesiStatusForPerkuliahan($p);

            return (object) [
                'id' => $jadwal->id,
                'hari' => $jadwal->hari,
                'jam_mulai' => $jadwal->jam_mulai,
                'jam_selesai' => $jadwal->jam_selesai,
                'matkul' => $jadwal->kelas->kurikulumMatkul->matkul ?? null,
                'dosen' => $jadwal->dosen->map(fn ($jd) => $jd->dosen)->filter()->values(),
                'ruangan' => $jadwal->ruangan,
                'jenis_kuliah' => $jadwal->jenisKuliah,
                'krs_status' => $krs && $krs->approved_at ? 'approved' : 'pending',
                'sesi_status' => $sesi['sesi_status'],
                'sesi_status_label' => $sesi['sesi_status_label'],
            ];
        })->sortBy(function ($item) {
            $hariNum = self::HARI_ORDER[strtolower((string) $item->hari)] ?? 8;
            $jamMulai = str_replace(':', '', (string) $item->jam_mulai);

            return $hariNum * 10000 + (int) $jamMulai;
        })->values();
    }

    public function updatedFilterSemester(): void
    {
        unset($this->kelasOptions);
        $this->filterKelas = '';
        $this->autoSelectSingleKelas();
    }

    private function autoSelectSingleKelas(): void
    {
        $options = $this->kelasOptions;
        if (count($options) === 1) {
            $this->filterKelas = (string) array_key_first($options);
        }
    }

    private function krsListForSemester(int $semesterId): Collection
    {
        return Krs::with([
            'kelas.kurikulumMatkul.matkul',
        ])
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->whereNull('deleted_at')
            ->whereHas('kelas', fn ($q) => $q->where('id_semester', $semesterId))
            ->get()
            ->sortBy(fn (Krs $krs) => ($krs->kelas->kurikulumMatkul->matkul->kode ?? '').($krs->kelas->kurikulumMatkul->matkul->nama ?? ''))
            ->values();
    }

    /**
     * Sama persis dengan KrsController::findPerkuliahanForJadwalSlotKrs.
     */
    private function findPerkuliahanForJadwalSlot(Jadwal $jadwal, Collection $perkuliahanRows): ?Perkuliahan
    {
        $slotId = (int) $jadwal->id;
        $candidates = $perkuliahanRows->filter(fn (Perkuliahan $p) => (int) $p->id_jadwal === $slotId);

        $ts = static fn (?Perkuliahan $p): int => $p?->waktu_mulai ? $p->waktu_mulai->getTimestamp() : 0;

        $ongoing = $candidates
            ->filter(fn (Perkuliahan $p) => $p->waktu_mulai && ! $p->waktu_selesai)
            ->sortByDesc(fn (Perkuliahan $p) => $ts($p))
            ->first();

        if ($ongoing) {
            return $ongoing;
        }

        return $candidates
            ->sortByDesc(fn (Perkuliahan $p) => [$ts($p), $p->id])
            ->first();
    }

    /**
     * Sama persis dengan KrsController::sesiStatusForPerkuliahanKrs.
     *
     * @return array{sesi_status: string, sesi_status_label: string}
     */
    private function sesiStatusForPerkuliahan(?Perkuliahan $p): array
    {
        if ($p === null || ! $p->waktu_mulai) {
            return ['sesi_status' => 'belum_mulai', 'sesi_status_label' => 'Belum dilaksanakan'];
        }

        if (! $p->waktu_selesai) {
            return ['sesi_status' => 'sedang_berlangsung', 'sesi_status_label' => 'Sedang berlangsung'];
        }

        return ['sesi_status' => 'selesai', 'sesi_status_label' => 'Sudah dilaksanakan'];
    }

    public function render()
    {
        return view('livewire.mahasiswa.jadwal.index')->extends('layouts.mahasiswa');
    }
}
