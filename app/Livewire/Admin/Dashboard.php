<?php

namespace App\Livewire\Admin;

use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Pembayaran;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\StatusAkademik;
use App\Models\Tagihan;
use App\Models\TugasAkhir;
use App\Models\UjianSidang;
use App\Models\WisudaMahasiswa;
use App\Services\KeringananBiayaKreditService;
use App\Services\StatusPembayaranTagihan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class Dashboard extends Component
{
    // Visibilitas bagian dashboard ditentukan oleh role Spatie user, bukan oleh route —
    // keduanya dirender di halaman admin.dashboard yang sama:
    // - Superadmin: kedua bagian tampil.
    // - Akademik saja: hanya bagian akademik.
    // - Keuangan saja: hanya bagian keuangan.
    public bool $showAkademik = false;

    public bool $showKeuangan = false;

    // Terikat <x-searchable-select>, string karena opsi kosong = "semua periode" — sama
    // seperti PembayaranController::dashboardStats yang menganggap parameter kosong sebagai
    // "semua periode" (beda dengan endpoint lain yang default ke semester aktif).
    #[Url(as: 'id_semester')]
    public string $filterSemesterKeuangan = '';

    public function mount(): void
    {
        $user = Auth::user();
        $isSuperadmin = $user?->isSuperadmin() ?? false;

        $this->showAkademik = $isSuperadmin || ($user?->isAkademik() ?? false);
        $this->showKeuangan = $isSuperadmin || ($user?->isKeuangan() ?? false);
    }

    /**
     * Sama persis dengan DashboardController::getMahasiswaKrsStats, dipangkas ke field yang
     * benar-benar ditampilkan kartu "Mahasiswa Aktif" (total_aktif, semester, status_lain).
     */
    #[Computed]
    public function krsStats(): array
    {
        $statusLain = $this->mahasiswaStatusLainBreakdown();

        $activeSemester = Semester::where('is_active', true)->whereNull('deleted_at')->first();
        if (! $activeSemester) {
            return ['total_aktif' => 0, 'semester' => null, 'status_lain' => $statusLain];
        }

        $statusAktif = StatusAkademik::where('nama', 'Aktif')->whereNull('deleted_at')->first();
        if (! $statusAktif) {
            return [
                'total_aktif' => 0,
                'semester' => ['id' => $activeSemester->id, 'nama' => $activeSemester->nama],
                'status_lain' => $statusLain,
            ];
        }

        $totalAktif = Mahasiswa::where('id_status_akademik', $statusAktif->id)->whereNull('deleted_at')->count();

        return [
            'total_aktif' => $totalAktif,
            'semester' => ['id' => $activeSemester->id, 'nama' => $activeSemester->nama],
            'status_lain' => $statusLain,
        ];
    }

    /**
     * Sama persis dengan DashboardController::mahasiswaStatusLainBreakdown.
     *
     * @return array<int, array{nama: string, jumlah: int}>
     */
    private function mahasiswaStatusLainBreakdown(): array
    {
        $statusLainList = StatusAkademik::where('nama', '!=', 'Aktif')->whereNull('deleted_at')->get(['id', 'nama']);

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
     * Sama persis dengan DashboardController::getAntrianTindakan, ditambah resolusi url lewat
     * Route::has() — dipakai sebagai jaga-jaga kalau salah satu modul belum/tidak lagi punya
     * halaman di panel ini. Ujian sidang belum punya halaman daftar tersendiri (show-nya butuh
     * {id}/{sidangId} tugas akhir induknya), jadi diarahkan ke daftar tugas akhir yang sama.
     */
    #[Computed]
    public function antrianTindakan(): array
    {
        $krsMenunggu = Krs::whereNull('approved_at')->whereNull('deleted_at')->count();
        $pembayaranMenunggu = Pembayaran::whereNull('approved_at')->whereNull('deleted_at')->count();
        $tugasAkhirMenunggu = TugasAkhir::where('status', 'submitted')->whereNull('deleted_at')->count();
        $ujianSidangMenunggu = UjianSidang::where('status', 'submitted')->whereNull('deleted_at')->count();
        $wisudaMenunggu = WisudaMahasiswa::where('status', 'pending')->whereNull('deleted_at')->count();

        $items = [
            ['kode' => 'krs', 'icon' => 'clipboard-check', 'label' => 'KRS menunggu approval', 'jumlah' => $krsMenunggu, 'route' => 'admin.akademik.krs'],
            ['kode' => 'pembayaran', 'icon' => 'credit-card', 'label' => 'Pembayaran menunggu approval', 'jumlah' => $pembayaranMenunggu, 'route' => 'admin.keuangan.pembayaran'],
            ['kode' => 'tugas_akhir', 'icon' => 'file-text', 'label' => 'Pengajuan tugas akhir menunggu keputusan', 'jumlah' => $tugasAkhirMenunggu, 'route' => 'admin.akademik.tugas-akhir'],
            // Belum ada halaman daftar ujian sidang tersendiri — show-nya butuh {id}/{sidangId}
            // tugas akhir induknya, jadi diarahkan ke daftar tugas akhir yang sama.
            ['kode' => 'ujian_sidang', 'icon' => 'graduation-cap', 'label' => 'Pengajuan ujian sidang menunggu keputusan', 'jumlah' => $ujianSidangMenunggu, 'route' => 'admin.akademik.tugas-akhir'],
            ['kode' => 'wisuda', 'icon' => 'award', 'label' => 'Pendaftaran wisuda menunggu verifikasi', 'jumlah' => $wisudaMenunggu, 'route' => 'admin.akademik.wisuda'],
        ];

        foreach ($items as &$item) {
            $item['url'] = RouteFacade::has($item['route']) ? route($item['route']) : null;
        }
        unset($item);

        return [
            'items' => $items,
            'total' => $krsMenunggu + $pembayaranMenunggu + $tugasAkhirMenunggu + $ujianSidangMenunggu + $wisudaMenunggu,
        ];
    }

    /**
     * Sama persis dengan DashboardController::getNilaiBelumFinalisasi, ditambah url lewat
     * Route::has('admin.akademik.nilai').
     */
    #[Computed]
    public function nilaiBelumFinalisasi(): array
    {
        $activeSemester = Semester::where('is_active', true)->whereNull('deleted_at')->first();
        if (! $activeSemester) {
            return ['items' => [], 'total' => 0, 'semester' => null, 'url' => null];
        }

        $kelasList = Kelas::where('id_semester', $activeSemester->id)
            ->whereNull('deleted_at')
            ->with(['kurikulumMatkul.matkul', 'prodi'])
            ->get();
        $kelasIds = $kelasList->pluck('id');

        $url = RouteFacade::has('admin.akademik.nilai') ? route('admin.akademik.nilai') : null;

        if ($kelasIds->isEmpty()) {
            return [
                'items' => [],
                'total' => 0,
                'semester' => ['id' => $activeSemester->id, 'nama' => $activeSemester->nama],
                'url' => $url,
            ];
        }

        $pertemuanPerKelas = DB::table('perkuliahan')
            ->join('jadwal', 'perkuliahan.id_jadwal', '=', 'jadwal.id')
            ->whereIn('jadwal.id_kelas', $kelasIds)
            ->whereNull('perkuliahan.deleted_at')
            ->whereNull('jadwal.deleted_at')
            ->select('jadwal.id_kelas', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('jadwal.id_kelas')
            ->pluck('jumlah', 'id_kelas');

        $krsPerKelas = DB::table('krs')
            ->whereIn('id_kelas', $kelasIds)
            ->whereNull('deleted_at')
            ->select('id_kelas', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('id_kelas')
            ->pluck('jumlah', 'id_kelas');

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
                continue;
            }

            $jumlahKrs = (int) ($krsPerKelas[$kelas->id] ?? 0);
            if ($jumlahKrs === 0) {
                continue;
            }

            $jumlahFinal = (int) ($finalPerKelas[$kelas->id] ?? 0);
            if ($jumlahFinal >= $jumlahKrs) {
                continue;
            }

            $matkul = $kelas->kurikulumMatkul?->matkul;
            $matkulLabel = $matkul
                ? trim(($matkul->kode ? $matkul->kode.' — ' : '').$matkul->nama)
                : ($kelas->kurikulumMatkul?->nama_matkul ?? 'Mata kuliah');

            $items[] = [
                'id_kelas' => $kelas->id,
                'matkul' => $matkulLabel,
                'prodi' => $kelas->prodi?->nama,
                'jumlah_pertemuan' => $pertemuan,
                'jml_pertemuan_target' => (int) $kelas->jml_pertemuan,
                'jumlah_mahasiswa' => $jumlahKrs,
                'jumlah_nilai_final' => $jumlahFinal,
            ];
        }

        return [
            'items' => array_slice($items, 0, 10),
            'total' => count($items),
            'semester' => ['id' => $activeSemester->id, 'nama' => $activeSemester->nama],
            'url' => $url,
        ];
    }

    /**
     * Sama persis dengan DashboardController::getMahasiswaPerProdiBySemesterMasuk, ditambah
     * penentuan warna batang (buildProdiSeries) dan tinggi batang siap-render (niceAxisMax) —
     * dua fungsi murni yang di frontend dihitung di browser, di sini dipindah ke server karena
     * Blade tidak menjalankan JS.
     */
    #[Computed]
    public function mahasiswaPerProdi(): array
    {
        $semesterList = Semester::whereNull('deleted_at')->orderBy('kode', 'desc')->get();
        if ($semesterList->isEmpty()) {
            return ['has_data' => false];
        }

        $tahunSet = [];
        foreach ($semesterList as $semester) {
            if (preg_match('/^(\d{4})/', $semester->kode, $m)) {
                $tahunSet[(int) $m[1]] = true;
            }
        }

        $tahunUrut = array_keys($tahunSet);
        rsort($tahunUrut);
        $tahunTerakhir = array_slice($tahunUrut, 0, 3);
        if (empty($tahunTerakhir)) {
            return ['has_data' => false];
        }

        $semesterIds = [];
        foreach ($semesterList as $semester) {
            if (preg_match('/^(\d{4})/', $semester->kode, $m) && in_array((int) $m[1], $tahunTerakhir, true)) {
                $semesterIds[] = $semester->id;
            }
        }

        $prodiList = Prodi::whereNull('deleted_at')->orderBy('nama')->get();

        $results = DB::table('mahasiswa')
            ->join('semester', 'mahasiswa.id_semester_masuk', '=', 'semester.id')
            ->select(['mahasiswa.id_prodi', 'semester.kode', DB::raw('COUNT(mahasiswa.id) as jumlah_mahasiswa')])
            ->whereIn('mahasiswa.id_semester_masuk', $semesterIds)
            ->whereNull('mahasiswa.deleted_at')
            ->whereNull('semester.deleted_at')
            ->groupBy('mahasiswa.id_prodi', 'semester.kode')
            ->get();

        $dataByProdi = [];
        foreach ($prodiList as $prodi) {
            $dataByProdi[$prodi->id] = [
                'id_prodi' => $prodi->id,
                'nama_prodi' => $prodi->nama,
                'data' => array_fill_keys($tahunTerakhir, 0),
            ];
        }

        foreach ($results as $result) {
            if (! isset($dataByProdi[$result->id_prodi])) {
                continue;
            }
            if (preg_match('/^(\d{4})/', $result->kode, $m)) {
                $tahun = (int) $m[1];
                if (in_array($tahun, $tahunTerakhir, true)) {
                    $dataByProdi[$result->id_prodi]['data'][$tahun] += (int) $result->jumlah_mahasiswa;
                }
            }
        }

        $data = array_values($dataByProdi);
        if (empty($data)) {
            return ['has_data' => false];
        }
        usort($data, fn ($a, $b) => strcmp($a['nama_prodi'], $b['nama_prodi']));

        $series = $this->buildProdiSeries($data, $tahunTerakhir);

        $globalMax = 1;
        foreach ($series as $prodi) {
            foreach ($tahunTerakhir as $tahun) {
                $globalMax = max($globalMax, $prodi['data'][$tahun] ?? 0);
            }
        }
        $axisMax = $this->niceAxisMax($globalMax);
        $chartHeight = 220;
        $ticks = array_map(fn ($f) => (int) round($axisMax * $f), [1, 0.75, 0.5, 0.25, 0]);

        foreach ($series as &$prodi) {
            $bars = [];
            foreach ($tahunTerakhir as $tahun) {
                $value = $prodi['data'][$tahun] ?? 0;
                $bars[$tahun] = [
                    'value' => $value,
                    'height_px' => $value > 0 ? max(2, (int) round(($value / $axisMax) * $chartHeight)) : 0,
                ];
            }
            $prodi['bars'] = $bars;
        }
        unset($prodi);

        return [
            'has_data' => true,
            'tahun_list' => array_map(fn ($t) => ['tahun' => $t, 'label' => (string) $t], $tahunTerakhir),
            'chart_height' => $chartHeight,
            'ticks' => $ticks,
            'series' => $series,
        ];
    }

    /**
     * Sama persis dengan buildProdiSeries di frontend: maks. 8 prodi dapat warna identitas
     * sendiri (palet 8-slot tetap), sisanya digabung ke bucket "Lainnya" abu-abu netral.
     *
     * @param  array<int, array{id_prodi: int, nama_prodi: string, data: array<int, int>}>  $data
     * @param  array<int, int>  $tahunTerakhir
     * @return array<int, array{id_prodi: int, nama_prodi: string, data: array<int, int>, color: string}>
     */
    private function buildProdiSeries(array $data, array $tahunTerakhir): array
    {
        $palette = ['#2a78d6', '#008300', '#e87ba4', '#eda100', '#1baf7a', '#eb6834', '#4a3aa7', '#e34948'];
        $otherColor = '#94a3b8';

        if (count($data) <= count($palette)) {
            foreach ($data as $i => &$prodi) {
                $prodi['color'] = $palette[$i];
            }
            unset($prodi);

            return $data;
        }

        $totalOf = fn (array $p) => array_sum($p['data']);
        usort($data, fn ($a, $b) => $totalOf($b) <=> $totalOf($a));

        $top = array_slice($data, 0, count($palette) - 1);
        foreach ($top as $i => &$prodi) {
            $prodi['color'] = $palette[$i];
        }
        unset($prodi);

        $rest = array_slice($data, count($palette) - 1);

        $otherData = [];
        foreach ($tahunTerakhir as $tahun) {
            $otherData[$tahun] = array_sum(array_map(fn ($p) => $p['data'][$tahun] ?? 0, $rest));
        }

        $top[] = [
            'id_prodi' => -1,
            'nama_prodi' => 'Lainnya ('.count($rest).' prodi)',
            'data' => $otherData,
            'color' => $otherColor,
        ];

        return $top;
    }

    /**
     * Sama persis dengan niceAxisMax di frontend: bulatkan ke angka rapi (1/2/5 Γ— 10^n)
     * untuk skala sumbu-Y grafik.
     */
    private function niceAxisMax(int $value): int
    {
        if ($value <= 0) {
            return 10;
        }

        $magnitude = (int) (10 ** floor(log10($value)));
        $residual = $value / $magnitude;
        $niceResidual = $residual <= 1 ? 1 : ($residual <= 2 ? 2 : ($residual <= 5 ? 5 : 10));

        return (int) ($niceResidual * $magnitude);
    }

    /**
     * Sama persis dengan daftar "Tautan Singkat" di frontend (Jadwal Kuliah/Kurikulum/Perkuliahan).
     * Route::has() dipakai sebagai jaga-jaga kalau salah satu modul ini suatu saat dipindah/dihapus
     * dari panel, bukan karena rute-rute ini belum ada — ketiganya sudah ada di panel ini.
     */
    #[Computed]
    public function quickLinks(): array
    {
        $candidates = [
            ['route' => 'admin.akademik.jadwal', 'label' => 'Jadwal Kuliah'],
            ['route' => 'admin.akademik.kurikulum', 'label' => 'Kurikulum'],
            ['route' => 'admin.akademik.perkuliahan', 'label' => 'Perkuliahan'],
        ];

        return collect($candidates)
            ->filter(fn ($link) => RouteFacade::has($link['route']))
            ->map(fn ($link) => ['label' => $link['label'], 'url' => route($link['route'])])
            ->values()
            ->all();
    }

    #[Computed]
    public function semesterOptionsKeuangan(): array
    {
        return Semester::orderByDesc('kode')
            ->get(['id', 'nama', 'kode'])
            ->mapWithKeys(fn ($s) => [$s->id => $s->kode ? "{$s->nama} ({$s->kode})" : $s->nama])
            ->all();
    }

    /**
     * Sama persis dengan PembayaranController::dashboardStats, ditambah tinggi batang tren
     * bulanan siap-render (Blade tidak menjalankan JS) — mengikuti pola mahasiswaPerProdi().
     */
    #[Computed]
    public function keuanganStats(): array
    {
        $semesterId = $this->filterSemesterKeuangan !== '' ? (int) $this->filterSemesterKeuangan : null;
        $semester = $semesterId ? Semester::find($semesterId, ['id', 'nama']) : null;

        $tagihanQuery = fn () => Tagihan::query()->when($semesterId, fn ($q) => $q->where('id_semester', $semesterId));

        $totalTagihan = (float) $tagihanQuery()->sum('total');
        // Diturunkan dari pembayaran yang disetujui + kredit keringanan, bukan dari kolom
        // `tagihan.status` — supaya angka ringkasan ini tidak pernah berbeda dari status yang
        // tampil di halaman daftar tagihan (dulu berbeda, mis. pada tagihan bernilai Rp0).
        $jumlahPerStatusAcc = StatusPembayaranTagihan::hitungPerStatus($tagihanQuery());

        $approvedPembayaranQuery = fn () => Pembayaran::query()
            ->whereNotNull('approved_at')
            ->when($semesterId, fn ($q) => $q->whereHas('tagihan', fn ($tq) => $tq->where('id_semester', $semesterId)));

        $totalTerbayar = (float) $approvedPembayaranQuery()->sum('nominal');
        $totalKeringanan = KeringananBiayaKreditService::totalKreditDisetujui($semesterId);
        $totalPiutang = max(0.0, $totalTagihan - $totalTerbayar - $totalKeringanan);

        $pendingPembayaranQuery = fn () => Pembayaran::query()
            ->whereNull('approved_at')
            ->when($semesterId, fn ($q) => $q->whereHas('tagihan', fn ($tq) => $tq->where('id_semester', $semesterId)));

        $pendingCount = $pendingPembayaranQuery()->count();
        $pendingTotal = (float) $pendingPembayaranQuery()->sum('nominal');

        $trenMentah = [];
        for ($i = 5; $i >= 0; $i--) {
            $bulan = now()->subMonths($i);
            $awal = $bulan->copy()->startOfMonth();
            $akhir = $bulan->copy()->endOfMonth();
            $total = (float) $approvedPembayaranQuery()->whereBetween('tanggal_pembayaran', [$awal, $akhir])->sum('nominal');
            $trenMentah[] = ['bulan' => $bulan->format('Y-m'), 'label' => $bulan->translatedFormat('M Y'), 'total' => $total];
        }

        $chartHeight = 140;
        $maxTren = max(1.0, (float) collect($trenMentah)->max('total'));
        $trenBulanan = array_map(function ($t) use ($maxTren, $chartHeight) {
            $t['height_px'] = $t['total'] > 0 ? max(4, (int) round(($t['total'] / $maxTren) * $chartHeight)) : 0;

            return $t;
        }, $trenMentah);

        return [
            'semester' => $semester ? ['id' => $semester->id, 'nama' => $semester->nama] : null,
            'ringkasan' => [
                'total_tagihan' => $totalTagihan,
                'total_terbayar' => $totalTerbayar,
                'total_keringanan' => $totalKeringanan,
                'total_piutang' => $totalPiutang,
                'jumlah_tagihan_lunas' => $jumlahPerStatusAcc[StatusPembayaranTagihan::LUNAS],
                'jumlah_tagihan_dibayar_sebagian' => $jumlahPerStatusAcc[StatusPembayaranTagihan::DIBAYAR_SEBAGIAN],
                'jumlah_tagihan_belum_bayar' => $jumlahPerStatusAcc[StatusPembayaranTagihan::BELUM_BAYAR],
                'jumlah_tagihan_kedaluwarsa' => $jumlahPerStatusAcc[StatusPembayaranTagihan::KEDALUWARSA],
                // Kunci lama dipertahankan supaya frontend Next.js tidak patah; isinya kini
                // memakai status turunan, dan "unpaid" berarti belum lunas (termasuk sebagian).
                'jumlah_tagihan_paid' => $jumlahPerStatusAcc[StatusPembayaranTagihan::LUNAS],
                'jumlah_tagihan_unpaid' => $jumlahPerStatusAcc[StatusPembayaranTagihan::BELUM_BAYAR]
                    + $jumlahPerStatusAcc[StatusPembayaranTagihan::DIBAYAR_SEBAGIAN],
                'jumlah_tagihan_expired' => $jumlahPerStatusAcc[StatusPembayaranTagihan::KEDALUWARSA],
                'pembayaran_menunggu_approval_count' => $pendingCount,
                'pembayaran_menunggu_approval_total' => $pendingTotal,
            ],
            'tren_bulanan' => $trenBulanan,
            'chart_height' => $chartHeight,
        ];
    }

    /**
     * Sama persis dengan daftar "Akses cepat" keuangan di frontend (Tagihan/Pembayaran/
     * Pengaturan biaya).
     */
    #[Computed]
    public function keuanganQuickLinks(): array
    {
        $candidates = [
            ['route' => 'admin.keuangan.tagihan', 'label' => 'Tagihan'],
            ['route' => 'admin.keuangan.pembayaran', 'label' => 'Pembayaran'],
            ['route' => 'admin.keuangan.struktur-biaya', 'label' => 'Pengaturan Biaya'],
        ];

        return collect($candidates)
            ->filter(fn ($link) => RouteFacade::has($link['route']))
            ->map(fn ($link) => ['label' => $link['label'], 'url' => route($link['route'])])
            ->values()
            ->all();
    }

    public function render()
    {
        return view('livewire.admin.dashboard')->extends('layouts.web');
    }
}
