<?php

namespace App\Livewire\Admin\Perkuliahan;

use App\Livewire\Admin\Perkuliahan\Concerns\ForwardsIndexState;
use App\Models\JenisPenilaian;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Nilai as NilaiModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Nilai extends Component
{
    use ForwardsIndexState;

    public int $kelasId;

    public function mount(int $id): void
    {
        $this->kelasId = $id;
        $this->resolveBackUrl();

        $kelas = Kelas::findOrFail($id);
        $this->ensureAccess($kelas);
    }

    /**
     * Sama persis dengan NilaiController::getMahasiswaByKelasAdmin — pengecekan scope prodi.
     */
    private function ensureAccess(Kelas $kelas): void
    {
        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $kelas->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke data nilai kelas ini.');
            }
        }
    }

    #[Computed]
    public function kelas(): Kelas
    {
        return Kelas::with([
            'kurikulumMatkul.matkul',
            'kurikulumMatkul.kurikulum',
            'prodi.jenjang',
            'semester',
        ])->findOrFail($this->kelasId);
    }

    #[Computed]
    public function jenisPenilaian()
    {
        return JenisPenilaian::whereNull('deleted_at')->orderBy('nama')->get();
    }

    /**
     * Sama persis dengan NilaiController::getMahasiswaByKelasAdmin — nilai_komponen dibaca lewat
     * DB::table (bukan model, karena tabel ini memang tidak punya model Eloquent di repo ini).
     */
    #[Computed]
    public function mahasiswaList()
    {
        $krsList = Krs::with(['mahasiswa.prodi', 'mahasiswa.semester_masuk'])
            ->join('mahasiswa', 'krs.id_mahasiswa', '=', 'mahasiswa.id')
            ->where('krs.id_kelas', $this->kelasId)
            ->whereNull('krs.deleted_at')
            ->whereNull('mahasiswa.deleted_at')
            ->select('krs.*')
            ->orderBy('mahasiswa.nim')
            ->get();

        $krsIds = $krsList->pluck('id')->all();

        $nilaiKomponenMap = collect();
        if ($krsIds !== []) {
            $nilaiKomponenMap = DB::table('nilai_komponen')
                ->join('jenis_penilaian', 'nilai_komponen.id_jenis_penilaian', '=', 'jenis_penilaian.id')
                ->whereIn('nilai_komponen.id_krs', $krsIds)
                ->whereNull('nilai_komponen.deleted_at')
                ->whereNull('jenis_penilaian.deleted_at')
                ->select('nilai_komponen.*', 'jenis_penilaian.nama as jenis_penilaian_nama', 'jenis_penilaian.kode as jenis_penilaian_kode', 'jenis_penilaian.bobot')
                ->get()
                ->groupBy('id_krs')
                ->map(fn ($items) => $items->keyBy('id_jenis_penilaian'));
        }

        $nilaiMap = $krsIds === []
            ? collect()
            : NilaiModel::whereIn('id_krs', $krsIds)->whereNull('deleted_at')->get()->keyBy('id_krs');

        return $krsList->map(function (Krs $krs) use ($nilaiKomponenMap, $nilaiMap) {
            $mahasiswa = $krs->mahasiswa;

            return (object) [
                'id_krs' => $krs->id,
                'nim' => $mahasiswa->nim,
                'nama' => $mahasiswa->nama,
                'nilai_komponen' => $nilaiKomponenMap[$krs->id] ?? collect(),
                'nilai' => $nilaiMap[$krs->id] ?? null,
            ];
        })->values();
    }

    public function render()
    {
        return view('livewire.admin.perkuliahan.nilai')->extends('layouts.web');
    }
}
