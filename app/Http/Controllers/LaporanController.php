<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\JenisPenilaian;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Pembayaran;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\StatusAkademik;
use App\Models\Tagihan;
use App\Services\KeringananBiayaKreditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanController extends Controller
{
    /**
     * Mahasiswa tanpa baris KRS untuk kelas pada semester tertentu (untuk laporan persetujuan KRS).
     */
    private function mahasiswaTanpaKrsSemesterUntukLaporan(int $semesterId, ?int $prodiId): Builder
    {
        $q = Mahasiswa::query()
            ->select([
                'mahasiswa.id as id_mahasiswa',
                'mahasiswa.nim',
                'mahasiswa.nama',
                'prodi.id as id_prodi',
                'prodi.nama as prodi_nama',
                'prodi.kode as prodi_kode',
            ])
            ->join('prodi', 'mahasiswa.id_prodi', '=', 'prodi.id')
            ->whereNull('mahasiswa.deleted_at')
            ->whereNull('prodi.deleted_at')
            ->whereNotExists(function ($sub) use ($semesterId): void {
                $sub->select(DB::raw('1'))
                    ->from('krs')
                    ->join('kelas', 'krs.id_kelas', '=', 'kelas.id')
                    ->whereColumn('krs.id_mahasiswa', 'mahasiswa.id')
                    ->where('kelas.id_semester', $semesterId)
                    ->whereNull('krs.deleted_at');
            });

        if ($prodiId) {
            $q->where('mahasiswa.id_prodi', $prodiId);
        }

        return $q;
    }

    private function labelStatusPengajuanKrs(?string $status): string
    {
        return match ($status) {
            'belum_mengajukan' => 'Belum mengajukan (tanpa KRS di semester ini)',
            'ada_belum_acc' => 'Ada yang belum di-ACC',
            'sudah_acc_semua' => 'Sudah ACC semua',
            default => 'Semua',
        };
    }

    /**
     * Get laporan mahasiswa berdasarkan status akademik per prodi
     */
    public function getMahasiswaAktif(Request $request): JsonResponse
    {
        $prodiId = $request->get('id_prodi') ? (int) $request->get('id_prodi') : null;
        $semesterMasukId = $request->get('id_semester_masuk') ? (int) $request->get('id_semester_masuk') : null;
        $statusAkademikId = $request->get('id_status_akademik') ? (int) $request->get('id_status_akademik') : null;
        $search = $request->get('search');
        $perPage = (int) $request->get('per_page', 10);
        $page = (int) $request->get('page', 1);

        if ($statusAkademikId) {
            $statusExists = StatusAkademik::where('id', $statusAkademikId)
                ->whereNull('deleted_at')
                ->exists();
            if (! $statusExists) {
                return response()->json([
                    'data' => [],
                    'detail_mahasiswa' => [],
                    'detail_pagination' => null,
                    'total' => 0,
                    'message' => 'Status akademik tidak ditemukan',
                ], 404);
            }
        }

        // Query mahasiswa berdasarkan filter
        $query = Mahasiswa::with(['prodi', 'status_akademik', 'semester_masuk'])
            ->whereNull('deleted_at');

        if ($statusAkademikId) {
            $query->where('id_status_akademik', $statusAkademikId);
        }

        // Filter berdasarkan prodi
        if ($prodiId) {
            $query->where('id_prodi', $prodiId);
        }

        // Filter berdasarkan semester masuk
        if ($semesterMasukId) {
            $query->where('id_semester_masuk', $semesterMasukId);
        }

        // Filter berdasarkan pencarian
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('nim', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Group by prodi dan get count
        $results = $query->select([
            'mahasiswa.id_prodi',
            DB::raw('COUNT(mahasiswa.id) as jumlah_mahasiswa'),
        ])
            ->groupBy('mahasiswa.id_prodi')
            ->get();

        // Get prodi details
        $prodiIds = $results->pluck('id_prodi')->filter()->toArray();
        $prodiData = [];
        if (! empty($prodiIds)) {
            $prodiList = Prodi::whereIn('id', $prodiIds)
                ->whereNull('deleted_at')
                ->get();

            foreach ($prodiList as $prodi) {
                $prodiData[$prodi->id] = $prodi;
            }
        }

        // Format data
        $data = $results->map(function ($item) use ($prodiData) {
            $prodi = $prodiData[$item->id_prodi] ?? null;

            return [
                'id_prodi' => $item->id_prodi,
                'prodi' => $prodi ? [
                    'id' => $prodi->id,
                    'kode' => $prodi->kode,
                    'nama' => $prodi->nama,
                ] : null,
                'jumlah_mahasiswa' => (int) $item->jumlah_mahasiswa,
            ];
        })->sortBy(function ($item) {
            return $item['prodi'] ? $item['prodi']['nama'] : '';
        })->values();

        // Get detail mahasiswa if prodiId is specified
        $detailMahasiswa = [];
        $detailPagination = null;
        if ($prodiId) {
            $mahasiswaQuery = Mahasiswa::with(['prodi', 'status_akademik', 'semester_masuk'])
                ->where('id_prodi', $prodiId)
                ->whereNull('deleted_at');

            if ($statusAkademikId) {
                $mahasiswaQuery->where('id_status_akademik', $statusAkademikId);
            }

            if ($semesterMasukId) {
                $mahasiswaQuery->where('id_semester_masuk', $semesterMasukId);
            }

            if ($search) {
                $mahasiswaQuery->where(function ($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%")
                        ->orWhere('nim', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Hitung total sebelum pagination
            $totalDetail = $mahasiswaQuery->count();

            // Pagination
            $offset = ($page - 1) * $perPage;
            $detailMahasiswa = $mahasiswaQuery->orderBy('nim')
                ->offset($offset)
                ->limit($perPage)
                ->get()
                ->map(function ($mhs) {
                    return [
                        'id' => $mhs->id,
                        'nim' => $mhs->nim,
                        'nama' => $mhs->nama,
                        'email' => $mhs->email,
                        'no_hp' => $mhs->no_hp,
                        'jenis_kelamin' => $mhs->jenis_kelamin,
                        'status_akademik' => $mhs->status_akademik ? [
                            'id' => $mhs->status_akademik->id,
                            'nama' => $mhs->status_akademik->nama,
                        ] : null,
                        'prodi' => $mhs->prodi ? [
                            'id' => $mhs->prodi->id,
                            'kode' => $mhs->prodi->kode,
                            'nama' => $mhs->prodi->nama,
                        ] : null,
                        'semester_masuk' => $mhs->semester_masuk ? [
                            'id' => $mhs->semester_masuk->id,
                            'kode' => $mhs->semester_masuk->kode,
                            'nama' => $mhs->semester_masuk->nama,
                        ] : null,
                    ];
                });

            $lastPage = (int) ceil($totalDetail / $perPage);
            $detailPagination = [
                'total' => $totalDetail,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
                'from' => $totalDetail > 0 ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $totalDetail),
            ];
        }

        return response()->json([
            'data' => $data,
            'detail_mahasiswa' => $detailMahasiswa,
            'detail_pagination' => $detailPagination,
            'total' => $data->sum('jumlah_mahasiswa'),
        ]);
    }

    /**
     * Export laporan mahasiswa berdasarkan status akademik per prodi ke Excel
     */
    public function exportMahasiswaAktif(Request $request): StreamedResponse
    {
        $prodiId = $request->get('id_prodi') ? (int) $request->get('id_prodi') : null;
        $semesterMasukId = $request->get('id_semester_masuk') ? (int) $request->get('id_semester_masuk') : null;
        $statusAkademikId = $request->get('id_status_akademik') ? (int) $request->get('id_status_akademik') : null;
        $statusAkademik = null;
        if ($statusAkademikId) {
            $statusAkademik = StatusAkademik::where('id', $statusAkademikId)
                ->whereNull('deleted_at')
                ->first();
            if (! $statusAkademik) {
                throw new \Exception('Status akademik tidak ditemukan');
            }
        }

        $spreadsheet = new Spreadsheet;

        if ($prodiId) {
            // Export detail mahasiswa per prodi
            $this->exportDetailMahasiswaAktif($spreadsheet, $statusAkademikId, $prodiId, $semesterMasukId, $statusAkademik?->nama);
        } else {
            // Export summary per prodi
            $this->exportSummaryMahasiswaAktif($spreadsheet, $statusAkademikId, $semesterMasukId, $statusAkademik?->nama);
        }

        $filename = $prodiId
            ? 'laporan_mahasiswa_berdasarkan_status_detail_'.date('YmdHis').'.xlsx'
            : 'laporan_mahasiswa_berdasarkan_status_per_prodi_'.date('YmdHis').'.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment;filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Export summary mahasiswa berdasarkan status akademik per prodi
     */
    private function exportSummaryMahasiswaAktif(Spreadsheet $spreadsheet, ?int $statusAkademikId, ?int $semesterMasukId = null, ?string $statusAkademikNama = null): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Mahasiswa Per Prodi');

        $sheet->setCellValue('A1', 'LAPORAN MAHASISWA BERDASARKAN STATUS');
        $sheet->setCellValue('A2', 'Filter Status Akademik: '.($statusAkademikNama ?? 'Semua Status'));
        $sheet->setCellValue('A3', 'Tanggal: '.date('d/m/Y H:i:s'));

        // Header
        $headers = [
            'No',
            'Kode Prodi',
            'Nama Prodi',
            'Jumlah Mahasiswa',
        ];
        $sheet->fromArray([$headers], null, 'A5');

        // Get data
        $query = Mahasiswa::select([
            'mahasiswa.id_prodi',
            DB::raw('COUNT(mahasiswa.id) as jumlah_mahasiswa'),
        ])
            ->whereNull('mahasiswa.deleted_at');

        if ($statusAkademikId) {
            $query->where('id_status_akademik', $statusAkademikId);
        }

        if ($semesterMasukId) {
            $query->where('mahasiswa.id_semester_masuk', $semesterMasukId);
        }

        $results = $query->groupBy('mahasiswa.id_prodi')->get();

        $prodiIds = $results->pluck('id_prodi')->filter()->toArray();
        $prodiData = [];
        if (! empty($prodiIds)) {
            $prodiList = Prodi::whereIn('id', $prodiIds)
                ->whereNull('deleted_at')
                ->get();

            foreach ($prodiList as $prodi) {
                $prodiData[$prodi->id] = $prodi;
            }
        }

        // Data rows
        $row = 6;
        $no = 1;
        foreach ($results as $item) {
            $prodi = $prodiData[$item->id_prodi] ?? null;
            $sheet->setCellValue('A'.$row, $no);
            $sheet->setCellValue('B'.$row, $prodi ? $prodi->kode : '-');
            $sheet->setCellValue('C'.$row, $prodi ? $prodi->nama : '-');
            $sheet->setCellValue('D'.$row, (int) $item->jumlah_mahasiswa);
            $row++;
            $no++;
        }

        // Add total row
        $total = $results->sum('jumlah_mahasiswa');
        $sheet->setCellValue('C'.$row, 'TOTAL');
        $sheet->setCellValue('D'.$row, $total);

        // Style header
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A5:D5')->applyFromArray($headerStyle);

        // Style total row
        $totalStyle = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E7E6E6'],
            ],
        ];
        $sheet->getStyle('C'.$row.':D'.$row)->applyFromArray($totalStyle);

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(40);
        $sheet->getColumnDimension('D')->setWidth(20);

        // Center align number columns
        $sheet->getStyle('A6:A'.($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D6:D'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    /**
     * Export detail mahasiswa berdasarkan status akademik per prodi
     */
    private function exportDetailMahasiswaAktif(Spreadsheet $spreadsheet, ?int $statusAkademikId, int $prodiId, ?int $semesterMasukId = null, ?string $statusAkademikNama = null): void
    {
        $prodi = Prodi::where('id', $prodiId)->whereNull('deleted_at')->first();
        $prodiNama = $prodi ? $prodi->nama : 'Prodi Tidak Ditemukan';

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Mahasiswa');

        // Title
        $sheet->setCellValue('A1', 'LAPORAN MAHASISWA BERDASARKAN STATUS');
        $sheet->setCellValue('A2', 'Program Studi: '.$prodiNama);
        $sheet->setCellValue('A3', 'Filter Status Akademik: '.($statusAkademikNama ?? 'Semua Status'));
        $sheet->setCellValue('A4', 'Tanggal: '.date('d/m/Y H:i:s'));

        // Header
        $headers = [
            'No',
            'NIM',
            'Nama',
            'Email',
            'No. HP',
            'Jenis Kelamin',
            'Status Akademik',
            'Semester Masuk',
        ];
        $sheet->fromArray([$headers], null, 'A6');

        // Get data
        $query = Mahasiswa::with(['prodi', 'semester_masuk'])
            ->where('id_prodi', $prodiId)
            ->whereNull('deleted_at');

        if ($statusAkademikId) {
            $query->where('id_status_akademik', $statusAkademikId);
        }

        if ($semesterMasukId) {
            $query->where('id_semester_masuk', $semesterMasukId);
        }

        $mahasiswa = $query->orderBy('nim')->get();

        // Data rows
        $row = 7;
        $no = 1;
        foreach ($mahasiswa as $mhs) {
            $sheet->setCellValue('A'.$row, $no);
            $sheet->setCellValue('B'.$row, $mhs->nim ?? '-');
            $sheet->setCellValue('C'.$row, $mhs->nama);
            $sheet->setCellValue('D'.$row, $mhs->email ?? '-');
            $sheet->setCellValue('E'.$row, $mhs->no_hp ?? '-');
            $sheet->setCellValue('F'.$row, $mhs->jenis_kelamin ?? '-');
            $sheet->setCellValue('G'.$row, $mhs->status_akademik ? $mhs->status_akademik->nama : '-');
            $sheet->setCellValue('H'.$row, $mhs->semester_masuk ? $mhs->semester_masuk->nama : '-');
            $row++;
            $no++;
        }

        // Add total row
        $sheet->setCellValue('B'.$row, 'TOTAL');
        $sheet->setCellValue('C'.$row, count($mahasiswa).' Mahasiswa');

        // Style title
        $titleStyle = [
            'font' => ['bold' => true, 'size' => 14],
        ];
        $sheet->getStyle('A1')->applyFromArray($titleStyle);
        $sheet->getStyle('A2')->applyFromArray($titleStyle);
        $sheet->getStyle('A3')->applyFromArray($titleStyle);

        // Style header
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A6:H6')->applyFromArray($headerStyle);

        // Style total row
        $totalStyle = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E7E6E6'],
            ],
        ];
        $sheet->getStyle('B'.$row.':C'.$row)->applyFromArray($totalStyle);

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(35);
        $sheet->getColumnDimension('D')->setWidth(30);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(20);
        $sheet->getColumnDimension('H')->setWidth(20);

        // Center align number columns
        $sheet->getStyle('A7:A'.($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F7:F'.($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    /**
     * Get laporan persetujuan KRS
     */
    public function getPersetujuanKrs(Request $request): JsonResponse
    {
        $prodiId = $request->get('id_prodi') ? (int) $request->get('id_prodi') : null;
        $semesterId = $request->get('id_semester') ? (int) $request->get('id_semester') : null;
        $sortBy = $request->get('sort_by', 'persentase');
        $sortOrderRaw = strtolower((string) $request->get('sort_order', 'desc'));
        $sortOrder = in_array($sortOrderRaw, ['asc', 'desc'], true) ? $sortOrderRaw : 'desc';
        $perPage = (int) $request->get('per_page', 10);
        $page = (int) $request->get('page', 1);
        $rawStatus = $request->get('status_pengajuan');
        $statusPengajuan = in_array($rawStatus, ['belum_mengajukan', 'ada_belum_acc', 'sudah_acc_semua'], true)
            ? $rawStatus
            : null;

        if (! $semesterId) {
            $activeSemester = Semester::where('is_active', true)
                ->whereNull('deleted_at')
                ->first();
            if ($activeSemester) {
                $semesterId = $activeSemester->id;
            }
        }

        if ($statusPengajuan === 'belum_mengajukan' && ! $semesterId) {
            return response()->json([
                'message' => 'Semester wajib untuk status belum mengajukan (pilih semester atau aktifkan semester di pengaturan).',
            ], 422);
        }

        $offset = ($page - 1) * $perPage;

        if ($statusPengajuan === 'belum_mengajukan') {
            $mQuery = $this->mahasiswaTanpaKrsSemesterUntukLaporan((int) $semesterId, $prodiId);
            $total = (clone $mQuery)->count();
            if ($sortBy === 'nim') {
                $mQuery->orderBy('mahasiswa.nim', $sortOrder);
            } else {
                $mQuery->orderBy('mahasiswa.nim', 'asc');
            }
            $results = $mQuery->offset($offset)->limit($perPage)->get();

            $mahasiswaIds = $results->pluck('id_mahasiswa')->toArray();
            $dosenWaliData = [];
            if (! empty($mahasiswaIds)) {
                $dosenWaliResults = DB::table('dosen_wali')
                    ->join('dosen', 'dosen_wali.id_dosen', '=', 'dosen.id')
                    ->whereIn('dosen_wali.id_mahasiswa', $mahasiswaIds)
                    ->where('dosen_wali.status', 'active')
                    ->whereNull('dosen_wali.deleted_at')
                    ->select('dosen_wali.id_mahasiswa', 'dosen.nama as dosen_nama')
                    ->get();

                foreach ($dosenWaliResults as $dw) {
                    $dosenWaliData[$dw->id_mahasiswa] = $dw->dosen_nama;
                }
            }

            $data = $results->map(function ($item) use ($dosenWaliData) {
                return [
                    'id_mahasiswa' => $item->id_mahasiswa,
                    'nim' => $item->nim,
                    'nama' => $item->nama,
                    'prodi' => [
                        'id' => $item->id_prodi,
                        'kode' => $item->prodi_kode,
                        'nama' => $item->prodi_nama,
                    ],
                    'dosen_wali' => $dosenWaliData[$item->id_mahasiswa] ?? null,
                    'total_kelas' => 0,
                    'sks_diajukan' => 0.0,
                    'sks_disetujui' => 0.0,
                    'persentase' => 0.0,
                ];
            });

            $semester = null;
            if ($semesterId) {
                $semesterData = Semester::find($semesterId);
                if ($semesterData) {
                    $semester = [
                        'id' => $semesterData->id,
                        'kode' => $semesterData->kode,
                        'nama' => $semesterData->nama,
                    ];
                }
            }

            $lastPage = (int) ceil($total / $perPage) ?: 1;

            return response()->json([
                'data' => $data,
                'semester' => $semester,
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $total),
            ]);
        }

        // Query untuk mendapatkan data KRS per mahasiswa dengan persentase persetujuan
        $query = Krs::select([
            'krs.id_mahasiswa',
            DB::raw('MAX(mahasiswa.nim) as nim'),
            DB::raw('MAX(mahasiswa.nama) as nama'),
            DB::raw('MAX(prodi.id) as id_prodi'),
            DB::raw('MAX(prodi.nama) as prodi_nama'),
            DB::raw('MAX(prodi.kode) as prodi_kode'),
            DB::raw('COUNT(DISTINCT krs.id) as total_kelas'),
            DB::raw('COALESCE(SUM(CASE WHEN krs.approved_at IS NOT NULL THEN COALESCE(kurikulum_matkul.sks, matkul.sks, 0) ELSE 0 END), 0) as sks_disetujui'),
            DB::raw('COALESCE(SUM(COALESCE(kurikulum_matkul.sks, matkul.sks, 0)), 0) as sks_diajukan'),
        ])
            ->join('mahasiswa', 'krs.id_mahasiswa', '=', 'mahasiswa.id')
            ->join('prodi', 'mahasiswa.id_prodi', '=', 'prodi.id')
            ->join('kelas', 'krs.id_kelas', '=', 'kelas.id')
            ->join('kurikulum_matkul', 'kelas.id_kurikulum_matkul', '=', 'kurikulum_matkul.id')
            ->leftJoin('matkul', 'kurikulum_matkul.id_matkul', '=', 'matkul.id')
            ->whereNull('krs.deleted_at')
            ->whereNull('mahasiswa.deleted_at');

        if ($semesterId) {
            $query->where('kelas.id_semester', $semesterId);
        }

        if ($prodiId) {
            $query->where('mahasiswa.id_prodi', $prodiId);
        }

        $query->groupBy('krs.id_mahasiswa');

        if ($statusPengajuan === 'ada_belum_acc') {
            $query->havingRaw('SUM(CASE WHEN krs.approved_at IS NULL THEN 1 ELSE 0 END) > 0');
        } elseif ($statusPengajuan === 'sudah_acc_semua') {
            $query->havingRaw('COUNT(DISTINCT krs.id) > 0 AND SUM(CASE WHEN krs.approved_at IS NULL THEN 1 ELSE 0 END) = 0');
        }

        $totalQuery = clone $query;
        $total = DB::table(DB::raw("({$totalQuery->toSql()}) as sub"))
            ->mergeBindings($totalQuery->getQuery())
            ->count();

        if ($sortBy === 'nim') {
            $query->orderBy('mahasiswa.nim', $sortOrder);
        } else {
            $query->orderByRaw("
                CASE 
                    WHEN COALESCE(SUM(COALESCE(kurikulum_matkul.sks, matkul.sks, 0)), 0) > 0 
                    THEN (COALESCE(SUM(CASE WHEN krs.approved_at IS NOT NULL THEN COALESCE(kurikulum_matkul.sks, matkul.sks, 0) ELSE 0 END), 0) * 100.0 / COALESCE(SUM(COALESCE(kurikulum_matkul.sks, matkul.sks, 0)), 0))
                    ELSE 0 
                END {$sortOrder}
            ");
        }

        $results = $query->offset($offset)->limit($perPage)->get();

        $mahasiswaIds = $results->pluck('id_mahasiswa')->toArray();
        $dosenWaliData = [];
        if (! empty($mahasiswaIds)) {
            $dosenWaliResults = DB::table('dosen_wali')
                ->join('dosen', 'dosen_wali.id_dosen', '=', 'dosen.id')
                ->whereIn('dosen_wali.id_mahasiswa', $mahasiswaIds)
                ->where('dosen_wali.status', 'active')
                ->whereNull('dosen_wali.deleted_at')
                ->select('dosen_wali.id_mahasiswa', 'dosen.nama as dosen_nama')
                ->get();

            foreach ($dosenWaliResults as $dw) {
                $dosenWaliData[$dw->id_mahasiswa] = $dw->dosen_nama;
            }
        }

        $data = $results->map(function ($item) use ($dosenWaliData) {
            $sksDiajukan = (float) $item->sks_diajukan;
            $sksDisetujui = (float) $item->sks_disetujui;
            $persentase = $sksDiajukan > 0 ? round(($sksDisetujui / $sksDiajukan) * 100, 2) : 0;

            return [
                'id_mahasiswa' => $item->id_mahasiswa,
                'nim' => $item->nim,
                'nama' => $item->nama,
                'prodi' => [
                    'id' => $item->id_prodi,
                    'kode' => $item->prodi_kode,
                    'nama' => $item->prodi_nama,
                ],
                'dosen_wali' => $dosenWaliData[$item->id_mahasiswa] ?? null,
                'total_kelas' => (int) $item->total_kelas,
                'sks_diajukan' => $sksDiajukan,
                'sks_disetujui' => $sksDisetujui,
                'persentase' => $persentase,
            ];
        });

        $semester = null;
        if ($semesterId) {
            $semesterData = Semester::find($semesterId);
            if ($semesterData) {
                $semester = [
                    'id' => $semesterData->id,
                    'kode' => $semesterData->kode,
                    'nama' => $semesterData->nama,
                ];
            }
        }

        $lastPage = (int) ceil($total / $perPage);

        return response()->json([
            'data' => $data,
            'semester' => $semester,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $lastPage,
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => min($offset + $perPage, $total),
        ]);
    }

    /**
     * Export laporan persetujuan KRS ke Excel
     */
    public function exportPersetujuanKrs(Request $request): StreamedResponse
    {
        $prodiId = $request->get('id_prodi') ? (int) $request->get('id_prodi') : null;
        $semesterId = $request->get('id_semester') ? (int) $request->get('id_semester') : null;
        $sortBy = $request->get('sort_by', 'persentase');
        $sortOrderRaw = strtolower((string) $request->get('sort_order', 'desc'));
        $sortOrder = in_array($sortOrderRaw, ['asc', 'desc'], true) ? $sortOrderRaw : 'desc';
        $rawStatus = $request->get('status_pengajuan');
        $statusPengajuan = in_array($rawStatus, ['belum_mengajukan', 'ada_belum_acc', 'sudah_acc_semua'], true)
            ? $rawStatus
            : null;

        if (! $semesterId) {
            $activeSemester = Semester::where('is_active', true)
                ->whereNull('deleted_at')
                ->first();
            if ($activeSemester) {
                $semesterId = $activeSemester->id;
            }
        }

        if ($statusPengajuan === 'belum_mengajukan' && ! $semesterId) {
            throw new HttpResponseException(response()->json([
                'message' => 'Semester wajib untuk status belum mengajukan (pilih semester atau aktifkan semester di pengaturan).',
            ], 422));
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Persetujuan KRS');

        // Get semester info
        $semesterNama = 'Semua Semester';
        if ($semesterId) {
            $semesterData = Semester::find($semesterId);
            if ($semesterData) {
                $semesterNama = $semesterData->nama;
            }
        }

        // Get prodi info
        $prodiNama = 'Semua Prodi';
        if ($prodiId) {
            $prodiData = Prodi::find($prodiId);
            if ($prodiData) {
                $prodiNama = $prodiData->nama;
            }
        }

        // Title
        $sheet->setCellValue('A1', 'LAPORAN PERSETUJUAN KRS');
        $sheet->setCellValue('A2', 'Semester: '.$semesterNama);
        $sheet->setCellValue('A3', 'Program Studi: '.$prodiNama);
        $sheet->setCellValue('A4', 'Status pengajuan: '.$this->labelStatusPengajuanKrs($statusPengajuan));
        $sheet->setCellValue('A5', 'Tanggal: '.date('d/m/Y H:i:s'));

        // Header
        $headers = [
            'No',
            'NIM',
            'Nama',
            'Program Studi',
            'Dosen Wali',
            'Total Kelas',
            'SKS Diajukan',
            'SKS Disetujui',
            'Persentase (%)',
        ];
        $sheet->fromArray([$headers], null, 'A6');

        if ($statusPengajuan === 'belum_mengajukan') {
            $mQuery = $this->mahasiswaTanpaKrsSemesterUntukLaporan((int) $semesterId, $prodiId);
            if ($sortBy === 'nim') {
                $mQuery->orderBy('mahasiswa.nim', $sortOrder);
            } else {
                $mQuery->orderBy('mahasiswa.nim', 'asc');
            }
            $results = $mQuery->get();
        } else {
            $query = Krs::select([
                'krs.id_mahasiswa',
                DB::raw('MAX(mahasiswa.nim) as nim'),
                DB::raw('MAX(mahasiswa.nama) as nama'),
                DB::raw('MAX(prodi.nama) as prodi_nama'),
                DB::raw('MAX(prodi.kode) as prodi_kode'),
                DB::raw('COUNT(DISTINCT krs.id) as total_kelas'),
                DB::raw('COALESCE(SUM(CASE WHEN krs.approved_at IS NOT NULL THEN COALESCE(kurikulum_matkul.sks, matkul.sks, 0) ELSE 0 END), 0) as sks_disetujui'),
                DB::raw('COALESCE(SUM(COALESCE(kurikulum_matkul.sks, matkul.sks, 0)), 0) as sks_diajukan'),
            ])
                ->join('mahasiswa', 'krs.id_mahasiswa', '=', 'mahasiswa.id')
                ->join('prodi', 'mahasiswa.id_prodi', '=', 'prodi.id')
                ->join('kelas', 'krs.id_kelas', '=', 'kelas.id')
                ->join('kurikulum_matkul', 'kelas.id_kurikulum_matkul', '=', 'kurikulum_matkul.id')
                ->leftJoin('matkul', 'kurikulum_matkul.id_matkul', '=', 'matkul.id')
                ->whereNull('krs.deleted_at')
                ->whereNull('mahasiswa.deleted_at');

            if ($semesterId) {
                $query->where('kelas.id_semester', $semesterId);
            }

            if ($prodiId) {
                $query->where('mahasiswa.id_prodi', $prodiId);
            }

            $query->groupBy('krs.id_mahasiswa');

            if ($statusPengajuan === 'ada_belum_acc') {
                $query->havingRaw('SUM(CASE WHEN krs.approved_at IS NULL THEN 1 ELSE 0 END) > 0');
            } elseif ($statusPengajuan === 'sudah_acc_semua') {
                $query->havingRaw('COUNT(DISTINCT krs.id) > 0 AND SUM(CASE WHEN krs.approved_at IS NULL THEN 1 ELSE 0 END) = 0');
            }

            if ($sortBy === 'nim') {
                $query->orderBy('mahasiswa.nim', $sortOrder);
            } else {
                $query->orderByRaw("
                    CASE 
                        WHEN COALESCE(SUM(COALESCE(kurikulum_matkul.sks, matkul.sks, 0)), 0) > 0 
                        THEN (COALESCE(SUM(CASE WHEN krs.approved_at IS NOT NULL THEN COALESCE(kurikulum_matkul.sks, matkul.sks, 0) ELSE 0 END), 0) * 100.0 / COALESCE(SUM(COALESCE(kurikulum_matkul.sks, matkul.sks, 0)), 0))
                        ELSE 0 
                    END {$sortOrder}
                ");
            }

            $results = $query->get();
        }

        // Get mahasiswa IDs untuk mengambil dosen wali
        $mahasiswaIds = $results->pluck('id_mahasiswa')->toArray();
        $dosenWaliData = [];
        if (! empty($mahasiswaIds)) {
            $dosenWaliResults = DB::table('dosen_wali')
                ->join('dosen', 'dosen_wali.id_dosen', '=', 'dosen.id')
                ->whereIn('dosen_wali.id_mahasiswa', $mahasiswaIds)
                ->where('dosen_wali.status', 'active')
                ->whereNull('dosen_wali.deleted_at')
                ->select('dosen_wali.id_mahasiswa', 'dosen.nama as dosen_nama')
                ->get();

            foreach ($dosenWaliResults as $dw) {
                $dosenWaliData[$dw->id_mahasiswa] = $dw->dosen_nama;
            }
        }

        // Data rows
        $row = 7;
        $no = 1;
        $totalSksDiajukan = 0;
        $totalSksDisetujui = 0;

        foreach ($results as $item) {
            if ($statusPengajuan === 'belum_mengajukan') {
                $sksDiajukan = 0.0;
                $sksDisetujui = 0.0;
                $persentase = 0.0;
                $totalKelas = 0;
            } else {
                $sksDiajukan = (float) $item->sks_diajukan;
                $sksDisetujui = (float) $item->sks_disetujui;
                $persentase = $sksDiajukan > 0 ? round(($sksDisetujui / $sksDiajukan) * 100, 2) : 0;
                $totalKelas = (int) $item->total_kelas;
            }

            $sheet->setCellValue('A'.$row, $no);
            $sheet->setCellValue('B'.$row, $item->nim);
            $sheet->setCellValue('C'.$row, $item->nama);
            $sheet->setCellValue('D'.$row, $item->prodi_nama);
            $sheet->setCellValue('E'.$row, $dosenWaliData[$item->id_mahasiswa] ?? '-');
            $sheet->setCellValue('F'.$row, $totalKelas);
            $sheet->setCellValue('G'.$row, $sksDiajukan);
            $sheet->setCellValue('H'.$row, $sksDisetujui);
            $sheet->setCellValue('I'.$row, $persentase);

            $totalSksDiajukan += $sksDiajukan;
            $totalSksDisetujui += $sksDisetujui;
            $row++;
            $no++;
        }

        // Add total row
        $totalPersentase = $totalSksDiajukan > 0 ? round(($totalSksDisetujui / $totalSksDiajukan) * 100, 2) : 0;
        $sheet->setCellValue('D'.$row, 'TOTAL');
        $sheet->setCellValue('G'.$row, $totalSksDiajukan);
        $sheet->setCellValue('H'.$row, $totalSksDisetujui);
        $sheet->setCellValue('I'.$row, $totalPersentase);

        // Style title
        $titleStyle = [
            'font' => ['bold' => true, 'size' => 14],
        ];
        $sheet->getStyle('A1')->applyFromArray($titleStyle);
        $sheet->getStyle('A2')->applyFromArray($titleStyle);
        $sheet->getStyle('A3')->applyFromArray($titleStyle);
        $sheet->getStyle('A4')->applyFromArray($titleStyle);
        $sheet->getStyle('A5')->applyFromArray($titleStyle);

        // Style header
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A6:I6')->applyFromArray($headerStyle);

        // Style total row
        $totalStyle = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E7E6E6'],
            ],
        ];
        $sheet->getStyle('D'.$row.':I'.$row)->applyFromArray($totalStyle);

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(35);
        $sheet->getColumnDimension('D')->setWidth(30);
        $sheet->getColumnDimension('E')->setWidth(30);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(15);
        $sheet->getColumnDimension('H')->setWidth(15);
        $sheet->getColumnDimension('I')->setWidth(15);

        // Center align number columns
        $sheet->getStyle('A7:A'.($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F7:I'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $filename = 'laporan_persetujuan_krs_'.date('YmdHis').'.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment;filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Get laporan pengisian nilai oleh dosen
     */
    public function getPengisianNilai(Request $request): JsonResponse
    {
        $prodiId = $request->get('id_prodi') ? (int) $request->get('id_prodi') : null;
        $semesterId = $request->get('id_semester') ? (int) $request->get('id_semester') : null;
        $dosenId = $request->get('id_dosen') ? (int) $request->get('id_dosen') : null;
        $perPage = (int) $request->get('per_page', 10);
        $page = (int) $request->get('page', 1);

        // Jika tidak ada semester yang dipilih, gunakan semester aktif
        if (! $semesterId) {
            $activeSemester = Semester::where('is_active', true)
                ->whereNull('deleted_at')
                ->first();
            if ($activeSemester) {
                $semesterId = $activeSemester->id;
            }
        }

        // Get semua jenis penilaian untuk menghitung total yang seharusnya
        $totalJenisPenilaian = JenisPenilaian::whereNull('deleted_at')
            ->where('status', 'manual')
            ->count();

        // Query untuk mendapatkan data jadwal dengan informasi pengisian nilai
        $query = Jadwal::select([
            'jadwal.id',
            'jadwal.id_kelas',
            'kelas.id_kelompok_kelas',
            'kelas.id_prodi',
            DB::raw('MAX(prodi.nama) as prodi_nama'),
            DB::raw('MAX(prodi.kode) as prodi_kode'),
            DB::raw('MAX(jenjang.kode) as jenjang_kode'),
            DB::raw('MAX(jenjang.nama) as jenjang_nama'),
            DB::raw('COALESCE(MAX(jadwal_dosen_dosen.nama), MAX(kelas_dosen.nama)) as dosen_nama'),
            DB::raw('COALESCE(MAX(jadwal_dosen_dosen.id), MAX(kelas.id_dosen_pic)) as dosen_id'),
            DB::raw('MAX(kelompok_kelas.nama) as kelompok_kelas_nama'),
            DB::raw('COALESCE(MAX(NULLIF(TRIM(kurikulum_matkul.kode_matkul), \'\')), MAX(matkul.kode)) as kode_matkul'),
            DB::raw('COALESCE(MAX(NULLIF(TRIM(kurikulum_matkul.nama_matkul), \'\')), MAX(matkul.nama)) as nama_matkul'),
            DB::raw('COUNT(DISTINCT krs.id) as total_krs'),
        ])
            ->join('kelas', 'jadwal.id_kelas', '=', 'kelas.id')
            ->join('prodi', 'kelas.id_prodi', '=', 'prodi.id')
            ->leftJoin('jenjang', 'prodi.id_jenjang', '=', 'jenjang.id')
            ->join('kurikulum_matkul', 'kelas.id_kurikulum_matkul', '=', 'kurikulum_matkul.id')
            ->leftJoin('matkul', 'kurikulum_matkul.id_matkul', '=', 'matkul.id')
            ->leftJoin('kelompok_kelas', function ($join) {
                $join->on('kelas.id_kelompok_kelas', '=', 'kelompok_kelas.id')
                    ->whereNull('kelompok_kelas.deleted_at');
            })
            ->leftJoin('jadwal_dosen', function ($join) {
                $join->on('jadwal_dosen.id_jadwal', '=', 'jadwal.id')
                    ->whereNull('jadwal_dosen.deleted_at');
            })
            ->leftJoin('dosen as jadwal_dosen_dosen', 'jadwal_dosen.id_dosen', '=', 'jadwal_dosen_dosen.id')
            ->leftJoin('dosen as kelas_dosen', 'kelas.id_dosen_pic', '=', 'kelas_dosen.id')
            ->leftJoin('krs', function ($join) {
                $join->on('krs.id_kelas', '=', 'kelas.id')
                    ->whereNull('krs.deleted_at');
            })
            ->whereNull('jadwal.deleted_at')
            ->whereNull('kelas.deleted_at');

        // Filter berdasarkan semester
        if ($semesterId) {
            $query->where('kelas.id_semester', $semesterId);
        }

        // Filter berdasarkan prodi
        if ($prodiId) {
            $query->where('kelas.id_prodi', $prodiId);
        }

        // Filter berdasarkan dosen (cek di jadwal_dosen atau kelas.id_dosen_pic)
        if ($dosenId) {
            $query->where(function ($q) use ($dosenId) {
                $q->where('jadwal_dosen.id_dosen', $dosenId)
                    ->orWhere('kelas.id_dosen_pic', $dosenId);
            });
        }

        $query->groupBy('jadwal.id', 'jadwal.id_kelas', 'kelas.id_kelompok_kelas', 'kelas.id_prodi');

        // Hitung total sebelum pagination
        $totalQuery = clone $query;
        $total = DB::table(DB::raw("({$totalQuery->toSql()}) as sub"))
            ->mergeBindings($totalQuery->getQuery())
            ->count();

        // Pagination
        $offset = ($page - 1) * $perPage;
        $results = $query->orderBy('prodi.nama')
            ->orderByRaw('COALESCE(NULLIF(TRIM(kurikulum_matkul.nama_matkul), \'\'), matkul.nama)')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        // Get jadwal IDs dan kelas IDs untuk menghitung nilai komponen yang sudah diisi
        $jadwalIds = $results->pluck('id')->toArray();
        $kelasIds = $results->pluck('id_kelas')->unique()->toArray();
        $nilaiKomponenCounts = [];
        if (! empty($kelasIds)) {
            $krsIds = Krs::whereIn('id_kelas', $kelasIds)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->toArray();

            if (! empty($krsIds)) {
                $nilaiKomponenResults = DB::table('nilai_komponen')
                    ->join('krs', 'nilai_komponen.id_krs', '=', 'krs.id')
                    ->join('jenis_penilaian', 'nilai_komponen.id_jenis_penilaian', '=', 'jenis_penilaian.id')
                    ->whereIn('krs.id_kelas', $kelasIds)
                    ->whereNull('nilai_komponen.deleted_at')
                    ->whereNull('jenis_penilaian.deleted_at')
                    ->where('jenis_penilaian.status', 'manual')
                    ->select('krs.id_kelas', DB::raw('COUNT(DISTINCT nilai_komponen.id) as total_diisi'))
                    ->groupBy('krs.id_kelas')
                    ->get();

                foreach ($nilaiKomponenResults as $result) {
                    $nilaiKomponenCounts[$result->id_kelas] = (int) $result->total_diisi;
                }
            }
        }

        // Format data dengan menghitung persentase
        $data = $results->map(function ($item) use ($totalJenisPenilaian, $nilaiKomponenCounts) {
            $totalKrs = (int) $item->total_krs;
            $totalSeharusnya = $totalKrs * $totalJenisPenilaian;
            $totalDiisi = $nilaiKomponenCounts[$item->id_kelas] ?? 0;
            $persentase = $totalSeharusnya > 0 ? round(($totalDiisi / $totalSeharusnya) * 100, 2) : 0;

            return [
                'id_jadwal' => $item->id,
                'id_kelas' => $item->id_kelas,
                'kode_matkul' => ! empty($item->kode_matkul) ? trim($item->kode_matkul) : '-',
                'nama_matkul' => ! empty($item->nama_matkul) ? trim($item->nama_matkul) : '-',
                'prodi' => [
                    'id' => $item->id_prodi,
                    'kode' => $item->prodi_kode,
                    'nama' => $item->prodi_nama,
                    'jenjang' => $item->jenjang_kode ? [
                        'kode' => $item->jenjang_kode,
                        'nama' => $item->jenjang_nama ?? '-',
                    ] : null,
                ],
                'grup_mahasiswa' => $item->id_kelompok_kelas ? [
                    'id' => (int) $item->id_kelompok_kelas,
                    'nama' => $item->kelompok_kelas_nama ?? '-',
                ] : null,
                'dosen' => [
                    'id' => $item->dosen_id,
                    'nama' => $item->dosen_nama ?? '-',
                ],
                'total_krs' => $totalKrs,
                'total_jenis_penilaian' => $totalJenisPenilaian,
                'total_seharusnya' => $totalSeharusnya,
                'total_diisi' => $totalDiisi,
                'persentase' => $persentase,
            ];
        });

        // Get semester info
        $semester = null;
        if ($semesterId) {
            $semesterData = Semester::find($semesterId);
            if ($semesterData) {
                $semester = [
                    'id' => $semesterData->id,
                    'kode' => $semesterData->kode,
                    'nama' => $semesterData->nama,
                ];
            }
        }

        $lastPage = (int) ceil($total / $perPage);

        return response()->json([
            'data' => $data,
            'semester' => $semester,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $lastPage,
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => min($offset + $perPage, $total),
        ]);
    }

    /**
     * Export laporan pengisian nilai ke Excel
     */
    public function exportPengisianNilai(Request $request): StreamedResponse
    {
        $prodiId = $request->get('id_prodi') ? (int) $request->get('id_prodi') : null;
        $semesterId = $request->get('id_semester') ? (int) $request->get('id_semester') : null;
        $dosenId = $request->get('id_dosen') ? (int) $request->get('id_dosen') : null;

        // Jika tidak ada semester yang dipilih, gunakan semester aktif
        if (! $semesterId) {
            $activeSemester = Semester::where('is_active', true)
                ->whereNull('deleted_at')
                ->first();
            if ($activeSemester) {
                $semesterId = $activeSemester->id;
            }
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pengisian Nilai');

        // Get semester info
        $semesterNama = 'Semua Semester';
        if ($semesterId) {
            $semesterData = Semester::find($semesterId);
            if ($semesterData) {
                $semesterNama = $semesterData->nama;
            }
        }

        // Get prodi info
        $prodiNama = 'Semua Prodi';
        if ($prodiId) {
            $prodiData = Prodi::find($prodiId);
            if ($prodiData) {
                $prodiNama = $prodiData->nama;
            }
        }

        // Title
        $sheet->setCellValue('A1', 'LAPORAN PENGISIAN NILAI OLEH DOSEN');
        $sheet->setCellValue('A2', 'Semester: '.$semesterNama);
        $sheet->setCellValue('A3', 'Program Studi: '.$prodiNama);
        $sheet->setCellValue('A4', 'Tanggal: '.date('d/m/Y H:i:s'));

        // Header
        $headers = [
            'No',
            'Kode Mata Kuliah',
            'Nama Mata Kuliah',
            'Program Studi',
            'Dosen',
            'Total KRS',
            'Total Diisi',
            'Total Seharusnya',
            'Persentase (%)',
        ];
        $sheet->fromArray([$headers], null, 'A6');

        // Get semua jenis penilaian untuk menghitung total yang seharusnya
        $totalJenisPenilaian = JenisPenilaian::whereNull('deleted_at')
            ->where('status', 'manual')
            ->count();

        // Get data (same query as getPengisianNilai)
        $query = Jadwal::select([
            'jadwal.id',
            'jadwal.id_kelas',
            'kelas.id_kelompok_kelas',
            'kelas.id_prodi',
            DB::raw('MAX(prodi.nama) as prodi_nama'),
            DB::raw('MAX(prodi.kode) as prodi_kode'),
            DB::raw('MAX(jenjang.kode) as jenjang_kode'),
            DB::raw('MAX(jenjang.nama) as jenjang_nama'),
            DB::raw('COALESCE(MAX(jadwal_dosen_dosen.nama), MAX(kelas_dosen.nama)) as dosen_nama'),
            DB::raw('MAX(kelompok_kelas.nama) as kelompok_kelas_nama'),
            DB::raw('COALESCE(MAX(NULLIF(TRIM(kurikulum_matkul.kode_matkul), \'\')), MAX(matkul.kode)) as kode_matkul'),
            DB::raw('COALESCE(MAX(NULLIF(TRIM(kurikulum_matkul.nama_matkul), \'\')), MAX(matkul.nama)) as nama_matkul'),
            DB::raw('COUNT(DISTINCT krs.id) as total_krs'),
        ])
            ->join('kelas', 'jadwal.id_kelas', '=', 'kelas.id')
            ->join('prodi', 'kelas.id_prodi', '=', 'prodi.id')
            ->leftJoin('jenjang', 'prodi.id_jenjang', '=', 'jenjang.id')
            ->join('kurikulum_matkul', 'kelas.id_kurikulum_matkul', '=', 'kurikulum_matkul.id')
            ->leftJoin('matkul', 'kurikulum_matkul.id_matkul', '=', 'matkul.id')
            ->leftJoin('kelompok_kelas', function ($join) {
                $join->on('kelas.id_kelompok_kelas', '=', 'kelompok_kelas.id')
                    ->whereNull('kelompok_kelas.deleted_at');
            })
            ->leftJoin('jadwal_dosen', function ($join) {
                $join->on('jadwal_dosen.id_jadwal', '=', 'jadwal.id')
                    ->whereNull('jadwal_dosen.deleted_at');
            })
            ->leftJoin('dosen as jadwal_dosen_dosen', 'jadwal_dosen.id_dosen', '=', 'jadwal_dosen_dosen.id')
            ->leftJoin('dosen as kelas_dosen', 'kelas.id_dosen_pic', '=', 'kelas_dosen.id')
            ->leftJoin('krs', function ($join) {
                $join->on('krs.id_kelas', '=', 'kelas.id')
                    ->whereNull('krs.deleted_at');
            })
            ->whereNull('jadwal.deleted_at')
            ->whereNull('kelas.deleted_at');

        if ($semesterId) {
            $query->where('kelas.id_semester', $semesterId);
        }

        if ($prodiId) {
            $query->where('kelas.id_prodi', $prodiId);
        }

        if ($dosenId) {
            $query->where(function ($q) use ($dosenId) {
                $q->where('jadwal_dosen.id_dosen', $dosenId)
                    ->orWhere('kelas.id_dosen_pic', $dosenId);
            });
        }

        $query->groupBy('jadwal.id', 'jadwal.id_kelas', 'kelas.id_kelompok_kelas', 'kelas.id_prodi')
            ->orderBy('prodi.nama')
            ->orderByRaw('COALESCE(NULLIF(TRIM(kurikulum_matkul.nama_matkul), \'\'), matkul.nama)');

        $results = $query->get();

        // Get kelas IDs untuk menghitung nilai komponen yang sudah diisi
        $kelasIds = $results->pluck('id_kelas')->unique()->toArray();
        $nilaiKomponenCounts = [];
        if (! empty($kelasIds)) {
            $krsIds = Krs::whereIn('id_kelas', $kelasIds)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->toArray();

            if (! empty($krsIds)) {
                $nilaiKomponenResults = DB::table('nilai_komponen')
                    ->join('krs', 'nilai_komponen.id_krs', '=', 'krs.id')
                    ->join('jenis_penilaian', 'nilai_komponen.id_jenis_penilaian', '=', 'jenis_penilaian.id')
                    ->whereIn('krs.id_kelas', $kelasIds)
                    ->whereNull('nilai_komponen.deleted_at')
                    ->whereNull('jenis_penilaian.deleted_at')
                    ->where('jenis_penilaian.status', 'manual')
                    ->select('krs.id_kelas', DB::raw('COUNT(DISTINCT nilai_komponen.id) as total_diisi'))
                    ->groupBy('krs.id_kelas')
                    ->get();

                foreach ($nilaiKomponenResults as $result) {
                    $nilaiKomponenCounts[$result->id_kelas] = (int) $result->total_diisi;
                }
            }
        }

        // Data rows
        $row = 7;
        $no = 1;
        foreach ($results as $item) {
            $totalKrs = (int) $item->total_krs;
            $totalSeharusnya = $totalKrs * $totalJenisPenilaian;
            $totalDiisi = $nilaiKomponenCounts[$item->id_kelas] ?? 0;
            $persentase = $totalSeharusnya > 0 ? round(($totalDiisi / $totalSeharusnya) * 100, 2) : 0;

            $kodeMatkul = ! empty($item->kode_matkul) ? trim($item->kode_matkul) : '-';
            $namaMatkul = ! empty($item->nama_matkul) ? trim($item->nama_matkul) : '-';

            $sheet->setCellValue('A'.$row, $no);
            $sheet->setCellValue('B'.$row, $kodeMatkul);
            $sheet->setCellValue('C'.$row, $namaMatkul);
            $sheet->setCellValue('D'.$row, $item->prodi_nama);
            $sheet->setCellValue('E'.$row, $item->dosen_nama ?? '-');
            $sheet->setCellValue('F'.$row, $totalKrs);
            $sheet->setCellValue('G'.$row, $totalDiisi);
            $sheet->setCellValue('H'.$row, $totalSeharusnya);
            $sheet->setCellValue('I'.$row, $persentase);
            $row++;
            $no++;
        }

        // Style title
        $titleStyle = [
            'font' => ['bold' => true, 'size' => 14],
        ];
        $sheet->getStyle('A1')->applyFromArray($titleStyle);
        $sheet->getStyle('A2')->applyFromArray($titleStyle);
        $sheet->getStyle('A3')->applyFromArray($titleStyle);
        $sheet->getStyle('A4')->applyFromArray($titleStyle);

        // Style header
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A6:I6')->applyFromArray($headerStyle);

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(40);
        $sheet->getColumnDimension('D')->setWidth(30);
        $sheet->getColumnDimension('E')->setWidth(30);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(15);
        $sheet->getColumnDimension('H')->setWidth(18);
        $sheet->getColumnDimension('I')->setWidth(15);

        // Center align number columns
        $sheet->getStyle('A7:A'.($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F7:I'.($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $filename = 'laporan_pengisian_nilai_'.date('YmdHis').'.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment;filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Laporan pelunasan tagihan per mahasiswa: total tagihan vs total pembayaran disetujui (approved_at).
     * Filter opsional: id_prodi (mahasiswa), id_semester (semester tagihan).
     */
    public function getPelunasanTagihan(Request $request): JsonResponse
    {
        $prodiId = $request->get('id_prodi') ? (int) $request->get('id_prodi') : null;
        $semesterId = $request->get('id_semester') ? (int) $request->get('id_semester') : null;
        $search = $request->get('search');
        $perPage = (int) $request->get('per_page', 15);
        $perPage = max(1, min(100, $perPage));

        $query = $this->buildPelunasanTagihanQuery($prodiId, $semesterId, $search);
        $paginator = $query->paginate($perPage);
        $data = $this->mapPelunasanTagihanToArray($paginator->getCollection(), $semesterId);

        return response()->json([
            'data' => $data,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    /**
     * Ekspor laporan pelunasan tagihan ke Excel (semua baris sesuai filter, bukan halaman saja).
     */
    public function exportPelunasanTagihan(Request $request): StreamedResponse
    {
        $prodiId = $request->get('id_prodi') ? (int) $request->get('id_prodi') : null;
        $semesterId = $request->get('id_semester') ? (int) $request->get('id_semester') : null;
        $search = $request->get('search');

        $semesterLabel = 'Semua semester';
        if ($semesterId) {
            $sem = Semester::whereNull('deleted_at')->find($semesterId);
            if ($sem) {
                $semesterLabel = $sem->kode ? ($sem->kode.' — '.$sem->nama) : $sem->nama;
            }
        }

        $prodiLabel = 'Semua prodi';
        if ($prodiId) {
            $prodiRow = Prodi::whereNull('deleted_at')->find($prodiId);
            if ($prodiRow) {
                $prodiLabel = $prodiRow->kode ? ($prodiRow->kode.' — '.$prodiRow->nama) : $prodiRow->nama;
            }
        }

        $query = $this->buildPelunasanTagihanQuery($prodiId, $semesterId, $search);
        $rows = $this->mapPelunasanTagihanToArray($query->get(), $semesterId);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pelunasan Tagihan');

        $sheet->setCellValue('A1', 'LAPORAN PELUNASAN TAGIHAN');
        $sheet->setCellValue('A2', 'Filter semester (tagihan): '.$semesterLabel);
        $sheet->setCellValue('A3', 'Filter program studi: '.$prodiLabel);
        $sheet->setCellValue('A4', 'Tanggal ekspor: '.date('d/m/Y H:i:s'));
        if ($search) {
            $sheet->setCellValue('A5', 'Pencarian: '.$search);
        }

        $headerRow = $search ? 7 : 6;
        $headers = [
            'No',
            'NIM',
            'Nama',
            'Program Studi',
            'Total Tagihan (Rp)',
            'Pembayaran Disetujui (Rp)',
            'Keringanan Disetujui (Rp)',
            'Sisa Tunggakan (Rp)',
            'Pencapaian (%)',
        ];
        $sheet->fromArray([$headers], null, 'A'.$headerRow);

        $dataStart = $headerRow + 1;
        $row = $dataStart;
        $no = 1;
        $sumTagihan = 0.0;
        $sumPembayaran = 0.0;
        $sumKeringanan = 0.0;

        foreach ($rows as $item) {
            $prodiText = '—';
            if (! empty($item['prodi'])) {
                $prodiText = ($item['prodi']['kode'] ? $item['prodi']['kode'].' · ' : '').$item['prodi']['nama'];
            }
            $keringanan = (float) ($item['total_keringanan_disetujui'] ?? 0);
            $sheet->setCellValue('A'.$row, $no);
            $sheet->setCellValue('B'.$row, $item['nim'] ?? '');
            $sheet->setCellValue('C'.$row, $item['nama']);
            $sheet->setCellValue('D'.$row, $prodiText);
            $sheet->setCellValue('E'.$row, $item['total_tagihan']);
            $sheet->setCellValue('F'.$row, $item['total_pembayaran_disetujui']);
            $sheet->setCellValue('G'.$row, $keringanan);
            $sheet->setCellValue('H'.$row, max(0, $item['total_tagihan'] - $item['total_pembayaran_disetujui'] - $keringanan));
            $sheet->setCellValue('I'.$row, $item['persentase_pembayaran']);

            $sumTagihan += $item['total_tagihan'];
            $sumPembayaran += $item['total_pembayaran_disetujui'];
            $sumKeringanan += $keringanan;
            $row++;
            $no++;
        }

        // Persentase total dulu keliru ditulis ke kolom G (kolom sisa tunggakan), bukan ke kolom
        // pencapaian — ikut dirapikan karena susunan kolomnya memang berubah di sini.
        $totalPersen = $sumTagihan > 0 ? round(100.0 * ($sumPembayaran + $sumKeringanan) / $sumTagihan, 2) : 0.0;
        $sheet->setCellValue('C'.$row, 'TOTAL');
        $sheet->setCellValue('E'.$row, $sumTagihan);
        $sheet->setCellValue('F'.$row, $sumPembayaran);
        $sheet->setCellValue('G'.$row, $sumKeringanan);
        $sheet->setCellValue('H'.$row, max(0, $sumTagihan - $sumPembayaran - $sumKeringanan));
        $sheet->setCellValue('I'.$row, $totalPersen);

        $titleStyle = ['font' => ['bold' => true, 'size' => 14]];
        $sheet->getStyle('A1')->applyFromArray($titleStyle);

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A'.$headerRow.':I'.$headerRow)->applyFromArray($headerStyle);

        $totalStyle = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E7E6E6'],
            ],
        ];
        $sheet->getStyle('C'.$row.':I'.$row)->applyFromArray($totalStyle);

        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(16);
        $sheet->getColumnDimension('C')->setWidth(28);
        $sheet->getColumnDimension('D')->setWidth(36);
        $sheet->getColumnDimension('E')->setWidth(22);
        $sheet->getColumnDimension('F')->setWidth(26);
        $sheet->getColumnDimension('G')->setWidth(26);
        $sheet->getColumnDimension('H')->setWidth(20);
        $sheet->getColumnDimension('I')->setWidth(16);

        $lastDataRow = $row - 1;
        if ($lastDataRow >= $dataStart) {
            $sheet->getStyle('A'.$dataStart.':A'.$lastDataRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('E'.$dataStart.':I'.$lastDataRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        $filename = 'laporan_pelunasan_tagihan_'.date('YmdHis').'.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment;filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function buildPelunasanTagihanQuery(?int $prodiId, ?int $semesterId, ?string $search): Builder
    {
        $tagihanSub = Tagihan::query()
            ->when($semesterId, fn ($q) => $q->where('id_semester', $semesterId))
            ->selectRaw('id_mahasiswa, SUM(total) as total_tagihan')
            ->groupBy('id_mahasiswa');

        $pembayaranSub = Pembayaran::query()
            ->join('tagihan', function ($join) {
                $join->on('pembayaran.id_tagihan', '=', 'tagihan.id')
                    ->whereNull('tagihan.deleted_at');
            })
            ->whereNotNull('pembayaran.approved_at')
            ->when($semesterId, fn ($q) => $q->where('tagihan.id_semester', $semesterId))
            ->selectRaw('tagihan.id_mahasiswa, SUM(pembayaran.nominal) as total_pembayaran')
            ->groupBy('tagihan.id_mahasiswa');

        $query = Mahasiswa::query()
            ->select(['mahasiswa.id', 'mahasiswa.nim', 'mahasiswa.nama', 'mahasiswa.id_prodi'])
            ->joinSub($tagihanSub, 'tt', 'mahasiswa.id', '=', 'tt.id_mahasiswa')
            ->leftJoinSub($pembayaranSub, 'tp', 'mahasiswa.id', '=', 'tp.id_mahasiswa')
            ->addSelect([
                DB::raw('tt.total_tagihan as agg_total_tagihan'),
                DB::raw('COALESCE(tp.total_pembayaran, 0) as agg_total_pembayaran'),
            ])
            ->whereNull('mahasiswa.deleted_at')
            ->when($prodiId, fn ($q) => $q->where('mahasiswa.id_prodi', $prodiId));

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('mahasiswa.nama', 'like', "%{$search}%")
                    ->orWhere('mahasiswa.nim', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('mahasiswa.nim');
    }

    /**
     * @param  Collection<int, Mahasiswa>  $collection
     * @return array<int, array<string, mixed>>
     */
    private function mapPelunasanTagihanToArray($collection, ?int $semesterId = null): array
    {
        $prodiIds = $collection->pluck('id_prodi')->filter()->unique()->values();
        $prodiMap = $prodiIds->isNotEmpty()
            ? Prodi::whereIn('id', $prodiIds)->whereNull('deleted_at')->get()->keyBy('id')
            : collect();

        // Keringanan yang sudah disetujui mengurangi kewajiban, jadi ikut menutup pelunasan.
        // Di level agregat per mahasiswa tidak perlu alokasi per tagihan — cukup dibatasi agar
        // tidak melebihi sisa yang belum terbayar supaya persentase tidak pernah > 100%.
        $kreditMap = KeringananBiayaKreditService::kreditPerMahasiswa(
            $collection->pluck('id')->all(),
            $semesterId
        );

        return $collection->map(function ($row) use ($prodiMap, $kreditMap) {
            $totalTagihan = (float) ($row->agg_total_tagihan ?? 0);
            $totalPembayaran = (float) ($row->agg_total_pembayaran ?? 0);
            $keringanan = min(
                (float) ($kreditMap[$row->id] ?? 0),
                max(0.0, $totalTagihan - $totalPembayaran)
            );
            $persentase = $totalTagihan > 0
                ? round(100.0 * ($totalPembayaran + $keringanan) / $totalTagihan, 2)
                : 0.0;
            $prodi = $prodiMap->get($row->id_prodi);

            return [
                'id' => $row->id,
                'nim' => $row->nim,
                'nama' => $row->nama,
                'prodi' => $prodi ? [
                    'id' => $prodi->id,
                    'kode' => $prodi->kode,
                    'nama' => $prodi->nama,
                ] : null,
                'total_tagihan' => $totalTagihan,
                'total_pembayaran_disetujui' => $totalPembayaran,
                'total_keringanan_disetujui' => $keringanan,
                'persentase_pembayaran' => $persentase,
            ];
        })->values()->all();
    }
}
