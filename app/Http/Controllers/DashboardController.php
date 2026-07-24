<?php

namespace App\Http\Controllers;

use App\Models\Mahasiswa;
use App\Models\Krs;
use App\Models\Semester;
use App\Models\StatusAkademik;
use App\Models\Kelas;
use App\Models\Prodi;
use App\Models\Pembayaran;
use App\Models\TugasAkhir;
use App\Models\UjianSidang;
use App\Models\WisudaMahasiswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Kelas di semester aktif yang perkuliahannya sudah selesai (jumlah pertemuan terlaksana
     * >= target) tapi nilai mahasiswanya belum semua difinalisasi dosen — dipakai sebagai
     * widget "Nilai Belum Difinalisasi" di dashboard admin.
     */
    public function getNilaiBelumFinalisasi(): JsonResponse
    {
        $activeSemester = Semester::where('is_active', true)->whereNull('deleted_at')->first();
        if (! $activeSemester) {
            return response()->json(['items' => [], 'total' => 0, 'semester' => null]);
        }

        $kelasList = Kelas::where('id_semester', $activeSemester->id)
            ->whereNull('deleted_at')
            ->with(['kurikulumMatkul.matkul', 'prodi'])
            ->get();
        $kelasIds = $kelasList->pluck('id');

        if ($kelasIds->isEmpty()) {
            return response()->json([
                'items' => [],
                'total' => 0,
                'semester' => ['id' => $activeSemester->id, 'nama' => $activeSemester->nama],
            ]);
        }

        // Jumlah pertemuan yang sudah terlaksana per kelas (baris `perkuliahan` lewat `jadwal`).
        $pertemuanPerKelas = DB::table('perkuliahan')
            ->join('jadwal', 'perkuliahan.id_jadwal', '=', 'jadwal.id')
            ->whereIn('jadwal.id_kelas', $kelasIds)
            ->whereNull('perkuliahan.deleted_at')
            ->whereNull('jadwal.deleted_at')
            ->select('jadwal.id_kelas', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('jadwal.id_kelas')
            ->pluck('jumlah', 'id_kelas');

        // Jumlah KRS (mahasiswa) per kelas.
        $krsPerKelas = DB::table('krs')
            ->whereIn('id_kelas', $kelasIds)
            ->whereNull('deleted_at')
            ->select('id_kelas', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('id_kelas')
            ->pluck('jumlah', 'id_kelas');

        // Jumlah nilai yang sudah difinalisasi (is_final = true) per kelas.
        $finalPerKelas = DB::table('nilai')
            ->join('krs', 'nilai.id_krs', '=', 'krs.id')
            ->whereIn('krs.id_kelas', $kelasIds)
            ->whereNull('nilai.deleted_at')
            ->whereNull('krs.deleted_at')
            ->where('nilai.is_final', true)
            ->select('krs.id_kelas', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('krs.id_kelas')
            ->pluck('jumlah', 'id_kelas');

        $items = [];
        foreach ($kelasList as $kelas) {
            $pertemuan = (int) ($pertemuanPerKelas[$kelas->id] ?? 0);
            if ($pertemuan < (int) $kelas->jml_pertemuan) {
                continue; // perkuliahan belum selesai
            }

            $jumlahKrs = (int) ($krsPerKelas[$kelas->id] ?? 0);
            if ($jumlahKrs === 0) {
                continue; // tidak ada mahasiswa di kelas ini
            }

            $jumlahFinal = (int) ($finalPerKelas[$kelas->id] ?? 0);
            if ($jumlahFinal >= $jumlahKrs) {
                continue; // nilai semua mahasiswa sudah final
            }

            $matkul = $kelas->kurikulumMatkul?->matkul;
            $matkulLabel = $matkul
                ? trim(($matkul->kode ? $matkul->kode.' — ' : '').$matkul->nama)
                : ($kelas->kurikulumMatkul?->nama_matkul ?? 'Mata kuliah');

            $items[] = [
                'id_kelas' => $kelas->id,
                'kode_kelas' => $kelas->kode,
                'matkul' => $matkulLabel,
                'prodi' => $kelas->prodi?->nama,
                'jumlah_pertemuan' => $pertemuan,
                'jml_pertemuan_target' => (int) $kelas->jml_pertemuan,
                'jumlah_mahasiswa' => $jumlahKrs,
                'jumlah_nilai_final' => $jumlahFinal,
            ];
        }

        return response()->json([
            'items' => array_slice($items, 0, 10),
            'total' => count($items),
            'semester' => ['id' => $activeSemester->id, 'nama' => $activeSemester->nama],
        ]);
    }

    /**
     * Ringkasan jumlah item yang menunggu tindakan admin di beberapa modul sekaligus —
     * dipakai sebagai widget "Antrian Tindakan" di dashboard admin.
     */
    public function getAntrianTindakan(): JsonResponse
    {
        $krsMenunggu = Krs::whereNull('approved_at')->whereNull('deleted_at')->count();

        $pembayaranMenunggu = Pembayaran::whereNull('approved_at')->whereNull('deleted_at')->count();

        $tugasAkhirMenunggu = TugasAkhir::where('status', 'submitted')->whereNull('deleted_at')->count();

        $ujianSidangMenunggu = UjianSidang::where('status', 'submitted')->whereNull('deleted_at')->count();

        $wisudaMenunggu = WisudaMahasiswa::where('status', 'pending')->whereNull('deleted_at')->count();

        return response()->json([
            'items' => [
                [
                    'kode' => 'krs',
                    'label' => 'KRS menunggu approval',
                    'jumlah' => $krsMenunggu,
                    'url' => '/admin/krs',
                ],
                [
                    'kode' => 'pembayaran',
                    'label' => 'Pembayaran menunggu approval',
                    'jumlah' => $pembayaranMenunggu,
                    'url' => '/admin/pembayaran',
                ],
                [
                    'kode' => 'tugas_akhir',
                    'label' => 'Pengajuan tugas akhir menunggu keputusan',
                    'jumlah' => $tugasAkhirMenunggu,
                    'url' => '/admin/tugas-akhir',
                ],
                [
                    'kode' => 'ujian_sidang',
                    'label' => 'Pengajuan ujian sidang menunggu keputusan',
                    'jumlah' => $ujianSidangMenunggu,
                    'url' => '/admin/tugas-akhir',
                ],
                [
                    'kode' => 'wisuda',
                    'label' => 'Pendaftaran wisuda menunggu verifikasi',
                    'jumlah' => $wisudaMenunggu,
                    'url' => '/admin/wisuda',
                ],
            ],
            'total' => $krsMenunggu + $pembayaranMenunggu + $tugasAkhirMenunggu + $ujianSidangMenunggu + $wisudaMenunggu,
        ]);
    }

    /**
     * Get statistics for mahasiswa aktif yang sudah membuat KRS pada semester aktif
     */
    public function getMahasiswaKrsStats(): JsonResponse
    {
        $statusLain = $this->mahasiswaStatusLainBreakdown();

        // Get active semester
        $activeSemester = Semester::where('is_active', true)
            ->whereNull('deleted_at')
            ->first();

        if (!$activeSemester) {
            return response()->json([
                'total_aktif' => 0,
                'sudah_krs' => 0,
                'belum_krs' => 0,
                'persentase' => 0,
                'semester' => null,
                'status_lain' => $statusLain,
            ]);
        }

        // Get status akademik "Aktif"
        $statusAktif = StatusAkademik::where('nama', 'Aktif')
            ->whereNull('deleted_at')
            ->first();

        if (!$statusAktif) {
            return response()->json([
                'total_aktif' => 0,
                'sudah_krs' => 0,
                'belum_krs' => 0,
                'persentase' => 0,
                'semester' => [
                    'id' => $activeSemester->id,
                    'kode' => $activeSemester->kode,
                    'nama' => $activeSemester->nama,
                ],
                'status_lain' => $statusLain,
            ]);
        }

        // Get all active mahasiswa
        $totalAktif = Mahasiswa::where('id_status_akademik', $statusAktif->id)
            ->whereNull('deleted_at')
            ->count();

        // Get kelas IDs for active semester
        $kelasIds = Kelas::where('id_semester', $activeSemester->id)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->toArray();

        if (empty($kelasIds)) {
            return response()->json([
                'total_aktif' => $totalAktif,
                'sudah_krs' => 0,
                'belum_krs' => $totalAktif,
                'persentase' => 0,
                'semester' => [
                    'id' => $activeSemester->id,
                    'kode' => $activeSemester->kode,
                    'nama' => $activeSemester->nama,
                ],
                'status_lain' => $statusLain,
            ]);
        }

        // Get unique mahasiswa IDs yang sudah membuat KRS pada semester aktif
        $mahasiswaSudahKrsIds = Krs::whereIn('id_kelas', $kelasIds)
            ->whereNull('deleted_at')
            ->select('id_mahasiswa')
            ->distinct()
            ->pluck('id_mahasiswa')
            ->toArray();

        // Filter hanya mahasiswa aktif yang sudah membuat KRS
        $sudahKrs = Mahasiswa::whereIn('id', $mahasiswaSudahKrsIds)
            ->where('id_status_akademik', $statusAktif->id)
            ->whereNull('deleted_at')
            ->count();

        $belumKrs = $totalAktif - $sudahKrs;
        $persentase = $totalAktif > 0 ? round(($sudahKrs / $totalAktif) * 100, 1) : 0;

        return response()->json([
            'total_aktif' => $totalAktif,
            'sudah_krs' => $sudahKrs,
            'belum_krs' => $belumKrs,
            'persentase' => $persentase,
            'semester' => [
                'id' => $activeSemester->id,
                'kode' => $activeSemester->kode,
                'nama' => $activeSemester->nama,
            ],
            'status_lain' => $statusLain,
        ]);
    }

    /**
     * Jumlah mahasiswa per status akademik selain "Aktif" (cuti, dropout, lulus, dst),
     * plus mahasiswa yang belum ada status akademiknya sama sekali — dipakai sebagai
     * rincian kecil di widget "Mahasiswa Aktif" pada dashboard admin.
     *
     * @return array<int, array{nama: string, jumlah: int}>
     */
    private function mahasiswaStatusLainBreakdown(): array
    {
        $statusLainList = StatusAkademik::where('nama', '!=', 'Aktif')
            ->whereNull('deleted_at')
            ->get(['id', 'nama']);

        $jumlahPerStatusId = Mahasiswa::whereIn('id_status_akademik', $statusLainList->pluck('id'))
            ->whereNull('deleted_at')
            ->select('id_status_akademik', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('id_status_akademik')
            ->pluck('jumlah', 'id_status_akademik');

        $perStatus = $statusLainList
            ->map(fn (StatusAkademik $s) => ['nama' => $s->nama, 'jumlah' => (int) ($jumlahPerStatusId[$s->id] ?? 0)])
            ->sortByDesc('jumlah')
            ->values()
            ->all();

        $tanpaStatus = Mahasiswa::whereNull('id_status_akademik')->whereNull('deleted_at')->count();
        if ($tanpaStatus > 0) {
            $perStatus[] = ['nama' => 'Belum ditentukan', 'jumlah' => $tanpaStatus];
        }

        return $perStatus;
    }

    /**
     * Get jumlah mahasiswa 3 tahun terakhir per prodi berdasarkan semester masuk
     * Data dijumlahkan per tahun (ganjil + genap)
     */
    public function getMahasiswaPerProdiBySemesterMasuk(): JsonResponse
    {
        // Ambil semua semester untuk mendapatkan tahun
        $semesterMasukList = Semester::whereNull('deleted_at')
            ->orderBy('kode', 'desc')
            ->get();

        if ($semesterMasukList->isEmpty()) {
            return response()->json([
                'data' => [],
                'tahun_list' => [],
            ]);
        }

        // Ekstrak tahun dari kode semester (4 digit pertama)
        // Format kode: 20241 (2024 Ganjil), 20242 (2024 Genap)
        $tahunSet = [];
        foreach ($semesterMasukList as $semester) {
            $kode = $semester->kode;
            // Ambil 4 digit pertama sebagai tahun
            if (preg_match('/^(\d{4})/', $kode, $matches)) {
                $tahun = (int) $matches[1];
                $tahunSet[$tahun] = true;
            }
        }

        // Ambil 3 tahun terakhir
        $tahunList = array_keys($tahunSet);
        rsort($tahunList); // Sort descending
        $tahunTerakhir = array_slice($tahunList, 0, 3);

        if (empty($tahunTerakhir)) {
            return response()->json([
                'data' => [],
                'tahun_list' => [],
            ]);
        }

        // Ambil semua semester yang termasuk dalam 3 tahun terakhir
        $semesterIds = [];
        $semesterByTahun = [];
        foreach ($semesterMasukList as $semester) {
            $kode = $semester->kode;
            if (preg_match('/^(\d{4})/', $kode, $matches)) {
                $tahun = (int) $matches[1];
                if (in_array($tahun, $tahunTerakhir)) {
                    $semesterIds[] = $semester->id;
                    if (!isset($semesterByTahun[$tahun])) {
                        $semesterByTahun[$tahun] = [];
                    }
                    $semesterByTahun[$tahun][] = $semester->id;
                }
            }
        }

        // Ambil semua prodi
        $prodiList = Prodi::whereNull('deleted_at')
            ->orderBy('nama')
            ->get();

        // Query jumlah mahasiswa per prodi per semester masuk
        $results = DB::table('mahasiswa')
            ->join('semester', 'mahasiswa.id_semester_masuk', '=', 'semester.id')
            ->select([
                'mahasiswa.id_prodi',
                'semester.kode',
                DB::raw('COUNT(mahasiswa.id) as jumlah_mahasiswa')
            ])
            ->whereIn('mahasiswa.id_semester_masuk', $semesterIds)
            ->whereNull('mahasiswa.deleted_at')
            ->whereNull('semester.deleted_at')
            ->groupBy('mahasiswa.id_prodi', 'semester.kode')
            ->get();

        // Group by prodi dan tahun (jumlahkan ganjil + genap)
        $dataByProdi = [];
        foreach ($prodiList as $prodi) {
            $dataByProdi[$prodi->id] = [
                'id_prodi' => $prodi->id,
                'kode_prodi' => $prodi->kode,
                'nama_prodi' => $prodi->nama,
                'data' => [],
            ];
        }

        // Isi data per tahun (jumlahkan ganjil + genap)
        foreach ($results as $result) {
            $prodiId = $result->id_prodi;
            $kode = $result->kode;
            
            // Ekstrak tahun dari kode
            if (preg_match('/^(\d{4})/', $kode, $matches)) {
                $tahun = (int) $matches[1];
                
                if (isset($dataByProdi[$prodiId]) && in_array($tahun, $tahunTerakhir)) {
                    if (!isset($dataByProdi[$prodiId]['data'][$tahun])) {
                        $dataByProdi[$prodiId]['data'][$tahun] = 0;
                    }
                    $dataByProdi[$prodiId]['data'][$tahun] += (int) $result->jumlah_mahasiswa;
                }
            }
        }

        // Pastikan semua tahun ada di setiap prodi (isi 0 jika tidak ada)
        foreach ($dataByProdi as $prodiId => &$prodiData) {
            foreach ($tahunTerakhir as $tahun) {
                if (!isset($prodiData['data'][$tahun])) {
                    $prodiData['data'][$tahun] = 0;
                }
            }
            // Sort by tahun descending
            krsort($prodiData['data']);
        }
        unset($prodiData);

        // Convert to array and sort by nama prodi
        $data = array_values($dataByProdi);
        usort($data, function ($a, $b) {
            return strcmp($a['nama_prodi'], $b['nama_prodi']);
        });

        // Format tahun list untuk label grafik (dari terbaru ke terlama)
        $tahunListFormatted = [];
        foreach ($tahunTerakhir as $tahun) {
            $tahunListFormatted[] = [
                'tahun' => $tahun,
                'label' => (string) $tahun,
            ];
        }

        return response()->json([
            'data' => $data,
            'tahun_list' => $tahunListFormatted,
        ]);
    }
}

