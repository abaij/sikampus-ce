<?php

namespace App\Livewire\Dosen\Nilai;

use App\Models\Dosen;
use App\Models\JadwalDosen;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Semester;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Index extends Component
{
    #[Locked]
    public int $dosenId;

    public function mount(): void
    {
        $dosen = Dosen::where('id_user', Auth::id())->firstOrFail();
        $this->dosenId = $dosen->id;
    }

    #[Computed]
    public function activeSemester(): ?Semester
    {
        return Semester::where('is_active', true)->whereNull('deleted_at')->first();
    }

    /**
     * Sama persis dengan NilaiController::getMyMataKuliah — kelas dimana dosen adalah PIC ATAU
     * punya jadwal_dosen aktif, pada semester aktif saja (tidak ada filter semester lain di sini,
     * mirror API apa adanya).
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function rows(): array
    {
        $activeSemester = $this->activeSemester;
        if (! $activeSemester) {
            return [];
        }

        $kelasAsPic = Kelas::where('id_dosen_pic', $this->dosenId)
            ->where('id_semester', $activeSemester->id)
            ->pluck('id')
            ->all();

        $kelasWithJadwal = JadwalDosen::where('id_dosen', $this->dosenId)
            ->where('status', 'active')
            ->whereHas('jadwal.kelas', fn ($q) => $q->where('id_semester', $activeSemester->id))
            ->with('jadwal:id,id_kelas')
            ->get()
            ->pluck('jadwal.id_kelas')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $kelasIds = array_unique(array_merge($kelasAsPic, $kelasWithJadwal));
        if ($kelasIds === []) {
            return [];
        }

        $kelasList = Kelas::with(['kurikulumMatkul.matkul', 'prodi.jenjang'])
            ->whereIn('id', $kelasIds)
            ->where('id_semester', $activeSemester->id)
            ->get();

        $mahasiswaCounts = Krs::whereIn('id_kelas', $kelasIds)
            ->whereNull('deleted_at')
            ->selectRaw('id_kelas, COUNT(DISTINCT id_mahasiswa) as jumlah_mahasiswa')
            ->groupBy('id_kelas')
            ->pluck('jumlah_mahasiswa', 'id_kelas');

        return $kelasList
            ->map(function (Kelas $kelas) use ($mahasiswaCounts) {
                $km = $kelas->kurikulumMatkul;

                return [
                    'kelas' => $kelas,
                    'kode_matkul' => $km?->kodeMatkulLabel() ?? '-',
                    'nama_matkul' => $km?->namaMatkulLabel() ?? '-',
                    'sks' => $km?->sksLabel() ?? 0,
                    'jumlah_mahasiswa' => (int) ($mahasiswaCounts[$kelas->id] ?? 0),
                ];
            })
            ->sortBy('nama_matkul')
            ->values()
            ->all();
    }

    public function render()
    {
        return view('livewire.dosen.nilai.index')->extends('layouts.dosen');
    }
}
