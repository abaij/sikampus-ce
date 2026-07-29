<?php

namespace App\Services;

use App\Models\BobotPenilaian;
use App\Models\Jadwal;
use App\Models\JenisPenilaian;
use App\Models\Kehadiran;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Nilai;
use App\Models\Perkuliahan;
use App\Models\RentangNilai;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Data mahasiswa + nilai komponen + jenis penilaian untuk satu kelas, dipakai baik oleh
 * Dosen\Nilai\Input (input komponen) maupun Dosen\Nilai\Rekap (rekap & kalkulasi) — disatukan di
 * sini (bukan diduplikasi di kedua komponen) supaya logika bobot/kehadiran/rentang tidak pernah
 * berbeda antara kedua halaman. Sama persis dengan NilaiController::getMahasiswaByKelas.
 */
class NilaiKelasDataService
{
    /**
     * @return array{
     *     jenis_penilaian: array<int, array<string, mixed>>,
     *     rentang_nilai: array<int, array<string, mixed>>,
     *     id_jenis_penilaian_kelas: array<int, int>,
     *     mahasiswa: array<int, array<string, mixed>>,
     * }
     */
    public static function build(Kelas $kelas): array
    {
        $kelas->loadMissing(['kurikulumMatkul.matkul', 'prodi.jenjang']);

        $krsList = Krs::with(['mahasiswa.prodi', 'mahasiswa.semester_masuk'])
            ->join('mahasiswa', 'krs.id_mahasiswa', '=', 'mahasiswa.id')
            ->where('krs.id_kelas', $kelas->id)
            ->whereNull('krs.deleted_at')
            ->whereNull('mahasiswa.deleted_at')
            ->select('krs.*')
            ->orderBy('mahasiswa.nim')
            ->get();

        $krsIds = $krsList->pluck('id')->all();

        $nilaiKomponenMap = $krsIds === []
            ? collect()
            : DB::table('nilai_komponen')
                ->whereIn('id_krs', $krsIds)
                ->whereNull('deleted_at')
                ->get()
                ->groupBy('id_krs')
                ->map(fn (Collection $items) => $items->keyBy('id_jenis_penilaian'));

        $nilaiMap = $krsIds === []
            ? collect()
            : Nilai::whereIn('id_krs', $krsIds)->whereNull('deleted_at')->get()->keyBy('id_krs');

        // Bobot per jenis penilaian: prioritas dari bobot_penilaian (mata kuliah), fallback ke
        // jenis_penilaian (default).
        $bobotPenilaianMap = $kelas->id_kurikulum_matkul
            ? BobotPenilaian::where('id_kurikulum_matkul', $kelas->id_kurikulum_matkul)->whereNull('deleted_at')->get()->keyBy('id_jenis_penilaian')
            : collect();

        $jenisPenilaianBase = JenisPenilaian::whereNull('deleted_at')->where('status', 'manual')->orderBy('nama')->get();
        $jenisPenilaianWithBobot = $jenisPenilaianBase->map(function (JenisPenilaian $jp) use ($bobotPenilaianMap) {
            $bobotPenilaian = $bobotPenilaianMap->get($jp->id);
            $bobot = $bobotPenilaian !== null ? (float) $bobotPenilaian->bobot : (float) $jp->bobot;

            return ['id' => $jp->id, 'kode' => $jp->kode, 'nama' => $jp->nama, 'bobot' => $bobot, 'status' => $jp->status];
        })->values()->all();

        // Jenis penilaian otomatis (Kehadiran): nilainya = persentase hadir dari tabel kehadiran.
        $jenisPenilaianKehadiran = JenisPenilaian::whereNull('deleted_at')
            ->where(fn ($q) => $q->where('kode', 'PRESENSI')->orWhere('nama', 'like', '%presensi%')->orWhere('nama', 'like', '%kehadiran%'))
            ->first();

        if ($jenisPenilaianKehadiran) {
            $bobotKehadiran = $bobotPenilaianMap->get($jenisPenilaianKehadiran->id);
            $bobotKehadiranVal = $bobotKehadiran !== null ? (float) $bobotKehadiran->bobot : (float) $jenisPenilaianKehadiran->bobot;
            $jenisPenilaianWithBobot[] = [
                'id' => $jenisPenilaianKehadiran->id,
                'kode' => $jenisPenilaianKehadiran->kode,
                'nama' => $jenisPenilaianKehadiran->nama,
                'bobot' => $bobotKehadiranVal,
                'status' => $jenisPenilaianKehadiran->status,
            ];
        }

        // Persentase kehadiran per mahasiswa (status hadir / jumlah perkuliahan) di kelas ini.
        $persentaseKehadiranMap = [];
        $jadwalIds = Jadwal::where('id_kelas', $kelas->id)->whereNull('deleted_at')->pluck('id')->all();
        if ($jadwalIds !== [] && $jenisPenilaianKehadiran) {
            $perkuliahanIds = Perkuliahan::whereIn('id_jadwal', $jadwalIds)->whereNull('deleted_at')->pluck('id')->all();
            $jumlahPerkuliahan = count($perkuliahanIds);
            if ($jumlahPerkuliahan > 0) {
                $kehadiranPerMahasiswa = Kehadiran::whereIn('id_perkuliahan', $perkuliahanIds)
                    ->whereNull('deleted_at')
                    ->where('status', 'hadir')
                    ->get()
                    ->groupBy('id_mhs')
                    ->map(fn (Collection $items) => $items->count());

                foreach ($krsList as $krs) {
                    $jumlahHadir = $kehadiranPerMahasiswa[$krs->id_mahasiswa] ?? 0;
                    $persentaseKehadiranMap[$krs->id_mahasiswa] = round(($jumlahHadir / $jumlahPerkuliahan) * 100, 2);
                }
            }
        }

        $idJenisKehadiran = $jenisPenilaianKehadiran?->id;
        $mahasiswaData = $krsList->map(function (Krs $krs) use ($nilaiKomponenMap, $nilaiMap, $persentaseKehadiranMap, $idJenisKehadiran) {
            $mahasiswa = $krs->mahasiswa;
            $nilaiKomponen = $nilaiKomponenMap->get($krs->id, collect());

            if ($idJenisKehadiran !== null && isset($persentaseKehadiranMap[$krs->id_mahasiswa])) {
                $nilaiKomponen = $nilaiKomponen->put($idJenisKehadiran, (object) [
                    'id_jenis_penilaian' => $idJenisKehadiran,
                    'nilai' => $persentaseKehadiranMap[$krs->id_mahasiswa],
                ]);
            }

            return [
                'id_krs' => $krs->id,
                'id_mahasiswa' => $mahasiswa->id,
                'nim' => $mahasiswa->nim,
                'nama' => $mahasiswa->nama,
                'prodi' => $mahasiswa->prodi,
                'nilai_komponen' => $nilaiKomponen,
                'nilai' => $nilaiMap->get($krs->id),
            ];
        })->values()->all();

        $rentangNilaiList = [];
        $jenjang = $kelas->prodi?->jenjang;
        if ($jenjang) {
            $rentangNilaiList = RentangNilai::where('id_jenjang', $jenjang->id)
                ->whereNull('deleted_at')
                ->orderByDesc('nilai_tinggi')
                ->get()
                ->values()
                ->all();
        }

        $idsManualKelas = $bobotPenilaianMap->isNotEmpty() ? $bobotPenilaianMap->keys() : $jenisPenilaianBase->pluck('id');
        $idsOtomatis = JenisPenilaian::whereNull('deleted_at')->where('status', 'otomatis')->pluck('id');
        $idJenisPenilaianKelas = $idsManualKelas->merge($idsOtomatis)->unique()->values()->all();

        return [
            'jenis_penilaian' => $jenisPenilaianWithBobot,
            'rentang_nilai' => $rentangNilaiList,
            'id_jenis_penilaian_kelas' => $idJenisPenilaianKelas,
            'mahasiswa' => $mahasiswaData,
        ];
    }

    /**
     * Sama persis dengan getJumlahTotalNilai di dosen/evaluasi/nilai/[id]/rekap/page.tsx: jumlah
     * (nilai × bobot/100) untuk komponen yang termasuk id_jenis_penilaian_kelas.
     *
     * @param  Collection<int, object>  $nilaiKomponen
     * @param  array<int, int>  $idJenisPenilaianKelas
     * @param  array<int, array<string, mixed>>  $jenisPenilaianList
     */
    public static function jumlahTotalNilai(Collection $nilaiKomponen, array $idJenisPenilaianKelas, array $jenisPenilaianList): ?float
    {
        if ($nilaiKomponen->isEmpty()) {
            return null;
        }

        $allowedIds = $idJenisPenilaianKelas !== [] ? array_flip($idJenisPenilaianKelas) : null;
        $bobotById = collect($jenisPenilaianList)->pluck('bobot', 'id');

        $total = 0.0;
        $hasAny = false;
        foreach ($nilaiKomponen as $idJenisPenilaian => $komponen) {
            if ($allowedIds !== null && ! isset($allowedIds[$idJenisPenilaian])) {
                continue;
            }
            $bobot = $bobotById->get($idJenisPenilaian);
            if ($bobot === null) {
                continue;
            }
            $total += ((float) $komponen->nilai) * ($bobot / 100);
            $hasAny = true;
        }

        return $hasAny ? round($total, 2) : null;
    }
}
