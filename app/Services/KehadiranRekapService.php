<?php

namespace App\Services;

use App\Models\Jadwal;
use App\Models\Kehadiran;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Perkuliahan;
use Illuminate\Support\Collection;

/**
 * Matriks rekap kehadiran (mahasiswa x pertemuan) untuk satu kelas, dipakai baik oleh
 * Dosen\Kehadiran\Index (modal "Lihat Rekap") maupun Dosen\Kehadiran\RekapKelas (halaman
 * standalone) — disatukan di sini supaya logika pengurutan pertemuan & pemetaan kehadiran tidak
 * pernah berbeda antara keduanya. Sama persis dengan KehadiranController::getRekapByKelas.
 */
class KehadiranRekapService
{
    /**
     * @return array{
     *     perkuliahan: Collection<int, object{id: int, pertemuan_ke: int, tanggal: ?string, materi: ?string}>,
     *     mahasiswa: Collection<int, object{id_mahasiswa: int, nim: ?string, nama: string, kehadiran: array<int, array{status: string, keterangan: ?string}|null>}>,
     * }
     */
    public static function build(Kelas $kelas): array
    {
        $jadwalIds = Jadwal::where('id_kelas', $kelas->id)->whereNull('deleted_at')->pluck('id')->all();

        [$perkuliahanList, $perkuliahanIdToCol] = self::perkuliahanSortedForRekap($jadwalIds);

        $mahasiswaList = Krs::with('mahasiswa:id,nim,nama')
            ->where('krs.id_kelas', $kelas->id)
            ->whereNull('krs.deleted_at')
            ->join('mahasiswa', 'krs.id_mahasiswa', '=', 'mahasiswa.id')
            ->whereNull('mahasiswa.deleted_at')
            ->orderBy('mahasiswa.nim')
            ->select('krs.*')
            ->get();

        $perkuliahanIds = $perkuliahanList->pluck('id')->all();
        $kehadiranByPerkuliahan = $perkuliahanIds === []
            ? collect()
            : Kehadiran::whereIn('id_perkuliahan', $perkuliahanIds)
                ->whereNull('deleted_at')
                ->get()
                ->groupBy('id_perkuliahan')
                ->map(fn (Collection $items) => $items->keyBy('id_mhs'));

        $mahasiswaData = $mahasiswaList->map(function (Krs $krs) use ($perkuliahanList, $kehadiranByPerkuliahan, $perkuliahanIdToCol) {
            $kehadiranPerPertemuan = [];
            foreach ($perkuliahanList as $p) {
                $col = $perkuliahanIdToCol[(int) $p->id] ?? null;
                if ($col === null) {
                    continue;
                }
                $kehadiran = $kehadiranByPerkuliahan[$p->id][$krs->id_mahasiswa] ?? null;
                $kehadiranPerPertemuan[$col] = $kehadiran ? [
                    'status' => $kehadiran->status,
                    'keterangan' => $kehadiran->keterangan,
                ] : null;
            }

            return (object) [
                'id_mahasiswa' => $krs->mahasiswa->id,
                'nim' => $krs->mahasiswa->nim,
                'nama' => $krs->mahasiswa->nama,
                'kehadiran' => $kehadiranPerPertemuan,
            ];
        })->values();

        return [
            'perkuliahan' => $perkuliahanList->map(fn (Perkuliahan $p) => (object) [
                'id' => $p->id,
                'pertemuan_ke' => $perkuliahanIdToCol[(int) $p->id] ?? null,
                'tanggal' => $p->waktu_mulai?->format('Y-m-d'),
                'materi' => $p->materi,
            ])->values(),
            'mahasiswa' => $mahasiswaData,
        ];
    }

    /**
     * Sama persis dengan KehadiranController::perkuliahanSortedForRekap.
     *
     * @param  array<int, int>  $jadwalIds
     * @return array{0: Collection<int, Perkuliahan>, 1: array<int, int>}
     */
    private static function perkuliahanSortedForRekap(array $jadwalIds): array
    {
        if ($jadwalIds === []) {
            return [collect(), []];
        }

        // Collection::sortBy([$closure, $closure]) memanggil tiap closure sebagai comparator
        // dua-argumen ($a, $b), bukan sebagai pengambil nilai — closure satu-argumen di sini
        // (pola yang sama seperti di KehadiranController::perkuliahanSortedForRekap /
        // Admin\Perkuliahan\Show::perkuliahanSortedForRekap) diam-diam menghasilkan urutan yang
        // salah. Satu closure yang mengembalikan array kunci majemuk aman untuk perbandingan
        // array bawaan PHP.
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
}
