<?php

namespace App\Http\Controllers;

use App\Models\Mahasiswa;
use App\Models\Notifikasi;
use App\Models\Pembayaran;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\Tagihan;
use App\Services\KeringananBiayaKreditService;
use App\Services\PelakuAksi;
use App\Services\PenomoranDokumen;
use App\Services\StatusPembayaranTagihan;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PembayaranController extends Controller
{
    /**
     * Query daftar pembayaran (sama untuk index & ekspor Excel).
     */
    private function buildPembayaranListQuery(Request $request): Builder
    {
        $search = $request->get('search');
        $mahasiswaId = $request->get('id_mahasiswa');
        $tagihanId = $request->get('id_tagihan');
        $accStatus = $request->get('acc_status');

        $query = Pembayaran::with([
            'tagihan.mahasiswa.prodi',
            'tagihan.semester',
        ]);

        if ($accStatus === 'acc') {
            $query->whereNotNull('approved_at');
        } elseif ($accStatus === 'belum_acc') {
            $query->whereNull('approved_at');
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('no_pembayaran', 'like', "%{$search}%")
                    ->orWhereHas('tagihan.mahasiswa', function ($q) use ($search) {
                        $q->where('nama', 'like', "%{$search}%")
                            ->orWhere('nim', 'like', "%{$search}%");
                    });
            });
        }

        if ($mahasiswaId) {
            $query->whereHas('tagihan', function ($q) use ($mahasiswaId) {
                $q->where('id_mahasiswa', $mahasiswaId);
            });
        }

        $prodiId = $request->get('id_prodi');
        if ($prodiId !== null && $prodiId !== '') {
            $query->whereHas('tagihan.mahasiswa', function ($q) use ($prodiId) {
                $q->where('id_prodi', $prodiId);
            });
        }

        if ($tagihanId) {
            $query->where('id_tagihan', $tagihanId);
        }

        // Periode tagihan (id_semester pada tagihan): param tidak ada = semester berstatus aktif; id_semester kosong = semua periode
        $querySemester = $request->query->all();
        if (array_key_exists('id_semester', $querySemester)) {
            $semRaw = $querySemester['id_semester'];
            if ($semRaw !== null && $semRaw !== '') {
                $query->whereHas('tagihan', function ($q) use ($semRaw) {
                    $q->where('id_semester', $semRaw);
                });
            }
        } else {
            $activeSemesterId = Semester::where('is_active', true)->value('id');
            if ($activeSemesterId) {
                $query->whereHas('tagihan', function ($q) use ($activeSemesterId) {
                    $q->where('id_semester', $activeSemesterId);
                });
            }
        }

        $tglDari = $request->get('tanggal_pembayaran_dari');
        $tglSampai = $request->get('tanggal_pembayaran_sampai');
        if ($tglDari) {
            $query->whereDate('tanggal_pembayaran', '>=', $tglDari);
        }
        if ($tglSampai) {
            $query->whereDate('tanggal_pembayaran', '<=', $tglSampai);
        }

        return $query;
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);

        $query = $this->buildPembayaranListQuery($request);

        $data = $query->orderBy('tanggal_pembayaran', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($data);
    }

    /**
     * Ringkasan agregat keuangan untuk dashboard admin: total tagihan, total terbayar, piutang
     * berjalan, dan tren pembayaran 6 bulan terakhir. Filter periode selaras `buildPembayaranListQuery`:
     * param `id_semester` tidak dikirim = semester aktif; dikirim kosong = semua periode.
     */
    public function dashboardStats(Request $request): JsonResponse
    {
        $semRaw = $request->query('id_semester');
        $semesterId = ($semRaw !== null && $semRaw !== '') ? (int) $semRaw : null;
        $semester = $semesterId ? Semester::find($semesterId, ['id', 'nama']) : null;

        $tagihanQuery = function () use ($semesterId) {
            $q = Tagihan::query();
            if ($semesterId) {
                $q->where('id_semester', $semesterId);
            }

            return $q;
        };

        $totalTagihan = (float) $tagihanQuery()->sum('total');
        $jumlahTagihan = $tagihanQuery()->count();
        // Diturunkan dari pembayaran yang disetujui + kredit keringanan, bukan dari kolom
        // `tagihan.status` — supaya angka ringkasan ini tidak pernah berbeda dari status yang
        // tampil di halaman daftar tagihan (dulu berbeda, mis. pada tagihan bernilai Rp0).
        $jumlahPerStatusAcc = StatusPembayaranTagihan::hitungPerStatus($tagihanQuery());

        $approvedPembayaranQuery = function () use ($semesterId) {
            $q = Pembayaran::query()->whereNotNull('approved_at');
            if ($semesterId) {
                $q->whereHas('tagihan', fn ($tq) => $tq->where('id_semester', $semesterId));
            }

            return $q;
        };
        $totalTerbayar = (float) $approvedPembayaranQuery()->sum('nominal');
        $totalKeringanan = KeringananBiayaKreditService::totalKreditDisetujui($semesterId);
        $totalPiutang = max(0.0, $totalTagihan - $totalTerbayar - $totalKeringanan);

        $pendingPembayaranQuery = function () use ($semesterId) {
            $q = Pembayaran::query()->whereNull('approved_at');
            if ($semesterId) {
                $q->whereHas('tagihan', fn ($tq) => $tq->where('id_semester', $semesterId));
            }

            return $q;
        };
        $pendingCount = $pendingPembayaranQuery()->count();
        $pendingTotal = (float) $pendingPembayaranQuery()->sum('nominal');

        $trenBulanan = [];
        for ($i = 5; $i >= 0; $i--) {
            $bulan = now()->subMonths($i);
            $awal = $bulan->copy()->startOfMonth();
            $akhir = $bulan->copy()->endOfMonth();
            $total = (float) $approvedPembayaranQuery()
                ->whereBetween('tanggal_pembayaran', [$awal, $akhir])
                ->sum('nominal');
            $trenBulanan[] = [
                'bulan' => $bulan->format('Y-m'),
                'label' => $bulan->translatedFormat('M Y'),
                'total' => $total,
            ];
        }

        return response()->json([
            'semester' => $semester,
            'ringkasan' => [
                'total_tagihan' => $totalTagihan,
                'total_terbayar' => $totalTerbayar,
                'total_keringanan' => $totalKeringanan,
                'total_piutang' => $totalPiutang,
                'jumlah_tagihan' => $jumlahTagihan,
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
        ]);
    }

    /**
     * Ekspor daftar pembayaran ke Excel sesuai filter index (semua baris, bukan halaman saja).
     */
    public function exportExcel(Request $request): StreamedResponse
    {
        $query = $this->buildPembayaranListQuery($request);
        $rows = $query->orderBy('tanggal_pembayaran', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pembayaran');

        $sheet->setCellValue('A1', 'DAFTAR PEMBAYARAN');
        $sheet->setCellValue('A2', 'Tanggal ekspor: '.date('d/m/Y H:i:s'));

        $rowMeta = 3;
        if ($request->get('search')) {
            $sheet->setCellValue('A'.$rowMeta, 'Pencarian: '.$request->get('search'));
            $rowMeta++;
        }

        $querySemester = $request->query->all();
        if (array_key_exists('id_semester', $querySemester)) {
            $semRaw = $querySemester['id_semester'];
            if ($semRaw !== null && $semRaw !== '') {
                $s = Semester::whereNull('deleted_at')->find((int) $semRaw);
                $sheet->setCellValue('A'.$rowMeta, 'Periode tagihan: '.($s ? (($s->kode ? $s->kode.' — ' : '').$s->nama) : '-'));
            } else {
                $sheet->setCellValue('A'.$rowMeta, 'Periode tagihan: semua periode');
            }
            $rowMeta++;
        } else {
            $active = Semester::where('is_active', true)->whereNull('deleted_at')->first();
            $sheet->setCellValue('A'.$rowMeta, 'Periode tagihan: '.($active ? (($active->kode ? $active->kode.' — ' : '').$active->nama).' (semester aktif, bawaan)' : '—'));
            $rowMeta++;
        }

        if ($request->get('id_prodi') !== null && $request->get('id_prodi') !== '') {
            $p = Prodi::whereNull('deleted_at')->find((int) $request->get('id_prodi'));
            $sheet->setCellValue('A'.$rowMeta, 'Program studi: '.($p ? (($p->kode ? $p->kode.' — ' : '').$p->nama) : '-'));
            $rowMeta++;
        }

        $acc = $request->get('acc_status');
        if ($acc === 'acc') {
            $sheet->setCellValue('A'.$rowMeta, 'Status ACC: sudah disetujui');
            $rowMeta++;
        } elseif ($acc === 'belum_acc') {
            $sheet->setCellValue('A'.$rowMeta, 'Status ACC: belum disetujui');
            $rowMeta++;
        }

        if ($request->get('tanggal_pembayaran_dari')) {
            $sheet->setCellValue('A'.$rowMeta, 'Tanggal pembayaran dari: '.$request->get('tanggal_pembayaran_dari'));
            $rowMeta++;
        }
        if ($request->get('tanggal_pembayaran_sampai')) {
            $sheet->setCellValue('A'.$rowMeta, 'Tanggal pembayaran sampai: '.$request->get('tanggal_pembayaran_sampai'));
            $rowMeta++;
        }

        $headerRow = $rowMeta + 1;
        $headers = [
            'No',
            'No. Pembayaran',
            'No. Tagihan',
            'NIM',
            'Nama Mahasiswa',
            'Program Studi',
            'Periode (semester tagihan)',
            'Nominal (Rp)',
            'Tanggal pembayaran',
            'Metode',
            'Status ACC',
            'Disetujui pada',
            'Keterangan',
        ];
        $sheet->fromArray([$headers], null, 'A'.$headerRow);

        $dataStart = $headerRow + 1;
        $row = $dataStart;
        $no = 1;
        $sumNominal = 0.0;

        foreach ($rows as $p) {
            $t = $p->tagihan;
            $m = $t?->mahasiswa;
            $prodiNama = $m?->prodi ? (($m->prodi->kode ? $m->prodi->kode.' · ' : '').$m->prodi->nama) : '';
            $sem = $t?->semester;
            $semLabel = $sem ? (($sem->kode ? $sem->kode.' — ' : '').$sem->nama) : '';

            $sheet->setCellValue('A'.$row, $no);
            $sheet->setCellValue('B'.$row, $p->no_pembayaran ?? '');
            $sheet->setCellValue('C'.$row, $t?->no_tagihan ?? '');
            $sheet->setCellValue('D'.$row, $m?->nim ?? '');
            $sheet->setCellValue('E'.$row, $m?->nama ?? '');
            $sheet->setCellValue('F'.$row, $prodiNama);
            $sheet->setCellValue('G'.$row, $semLabel);
            $nominal = (float) $p->nominal;
            $sheet->setCellValue('H'.$row, $nominal);
            $sheet->setCellValue('I'.$row, $p->tanggal_pembayaran ? Carbon::parse($p->tanggal_pembayaran)->format('Y-m-d') : '');
            $sheet->setCellValue('J'.$row, $p->metode_pembayaran ?? '');
            $sheet->setCellValue('K'.$row, $p->approved_at ? 'Sudah ACC' : 'Belum ACC');
            $sheet->setCellValue('L'.$row, $p->approved_at ? Carbon::parse($p->approved_at)->format('Y-m-d H:i') : '');
            $sheet->setCellValue('M'.$row, $p->keterangan ?? '');

            $sumNominal += $nominal;
            $row++;
            $no++;
        }

        $totalRow = $row;
        $sheet->setCellValue('G'.$totalRow, 'TOTAL');
        $sheet->setCellValue('H'.$totalRow, $sumNominal);

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
        $sheet->getStyle('A'.$headerRow.':M'.$headerRow)->applyFromArray($headerStyle);

        if ($rows->isNotEmpty()) {
            $totalStyle = [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E7E6E6'],
                ],
            ];
            $sheet->getStyle('G'.$totalRow.':H'.$totalRow)->applyFromArray($totalStyle);
        }

        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'daftar_pembayaran_'.date('YmdHis').'.xlsx';

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
     * Get unpaid tagihan by NIM
     */
    public function getUnpaidTagihanByNim(Request $request): JsonResponse
    {
        $request->validate([
            'nim' => ['required', 'string'],
        ]);

        $mahasiswa = Mahasiswa::where('nim', $request->nim)->first();

        if (! $mahasiswa) {
            return response()->json([
                'message' => 'Mahasiswa dengan NIM tersebut tidak ditemukan.',
            ], 404);
        }

        $tagihan = Tagihan::where('id_mahasiswa', $mahasiswa->id)
            ->whereIn('status', ['unpaid', 'expired'])
            ->with(['semester', 'tagihanRinci.komponenBiaya'])
            ->orderBy('tanggal_tagihan', 'desc')
            ->get();

        // Calculate total pembayaran yang sudah ada untuk setiap tagihan
        $kredit = KeringananBiayaKreditService::kreditUntukTagihanIds($tagihan->pluck('id')->all());

        $tagihanWithPembayaran = $tagihan->map(function ($t) use ($kredit) {
            $totalPembayaran = Pembayaran::approvedQueryForTagihan($t->id)->sum('nominal');
            $kreditBaris = (float) ($kredit[$t->id] ?? 0);
            $sisaTagihan = (float) $t->total - (float) $totalPembayaran - $kreditBaris;

            return [
                'id' => $t->id,
                'no_tagihan' => $t->no_tagihan,
                'total' => $t->total,
                'tahap' => $t->tahap,
                'status' => $t->status,
                'tanggal_tagihan' => $t->tanggal_tagihan,
                'tanggal_jatuh_tempo' => $t->tanggal_jatuh_tempo,
                'tanggal_pembayaran' => $t->tanggal_pembayaran,
                'keterangan' => $t->keterangan,
                'semester' => $t->semester,
                'tagihan_rinci' => $t->tagihanRinci,
                'total_pembayaran' => $totalPembayaran,
                'kredit_keringanan' => $kreditBaris,
                'sisa_tagihan' => $sisaTagihan,
            ];
        })->filter(function ($t) {
            // Filter out tagihan yang sudah lunas (sisa_tagihan <= 0)
            return $t['sisa_tagihan'] > 0;
        })->values();

        return response()->json([
            'mahasiswa' => $mahasiswa,
            'tagihan' => $tagihanWithPembayaran,
        ]);
    }

    /**
     * Get total pembayaran yang sudah ada untuk suatu tagihan
     */
    public function getTotalPembayaranByTagihan(Request $request, int $tagihanId): JsonResponse
    {
        $tagihan = Tagihan::findOrFail($tagihanId);
        $totalPembayaran = Pembayaran::approvedQueryForTagihan($tagihanId)->sum('nominal');
        $kredit = KeringananBiayaKreditService::kreditUntukTagihan($tagihan);
        $sisaTagihan = (float) $tagihan->total - (float) $totalPembayaran - $kredit;

        return response()->json([
            'id_tagihan' => $tagihanId,
            'total_tagihan' => $tagihan->total,
            'total_pembayaran' => $totalPembayaran,
            'kredit_keringanan' => $kredit,
            'sisa_tagihan' => $sisaTagihan,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_tagihan' => ['required', 'integer', 'exists:tagihan,id'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'tanggal_pembayaran' => ['nullable', 'date'],
            'metode_pembayaran' => ['nullable', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string'],
        ], [
            'id_tagihan.required' => 'Tagihan harus dipilih.',
            'id_tagihan.exists' => 'Tagihan tidak valid.',
            'nominal.required' => 'Nominal harus diisi.',
            'nominal.numeric' => 'Nominal harus berupa angka.',
            'nominal.min' => 'Nominal tidak boleh negatif.',
        ]);

        // Get tagihan
        $tagihan = Tagihan::findOrFail($validated['id_tagihan']);

        // Hanya pembayaran tersetujui yang mengurangi sisa; keringanan yang sudah disetujui juga
        // mengurangi kewajiban, jadi tidak boleh ada tagihan yang tetap ditagih penuh setelah
        // keringanannya diberikan.
        $totalPembayaran = Pembayaran::approvedQueryForTagihan($tagihan->id)->sum('nominal');
        $kredit = KeringananBiayaKreditService::kreditUntukTagihan($tagihan);
        $sisaTagihan = (float) $tagihan->total - (float) $totalPembayaran - $kredit;

        // Check if tagihan is already fully paid
        if ($sisaTagihan <= 0) {
            return response()->json([
                'message' => $kredit > 0
                    ? 'Tagihan ini sudah tertutup oleh pembayaran dan keringanan biaya.'
                    : 'Tagihan ini sudah lunas.',
            ], 422);
        }

        // Validate nominal tidak melebihi sisa tagihan
        if ($validated['nominal'] > $sisaTagihan) {
            return response()->json([
                'message' => 'Nominal pembayaran tidak boleh melebihi sisa tagihan yang belum dibayar.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Generate nomor pembayaran
            $noPembayaran = PenomoranDokumen::pembayaran();

            // Create pembayaran
            $pembayaran = Pembayaran::create([
                'id_tagihan' => $validated['id_tagihan'],
                'no_pembayaran' => $noPembayaran,
                'nominal' => $validated['nominal'],
                'tanggal_pembayaran' => $validated['tanggal_pembayaran'] ?? now(),
                'metode_pembayaran' => $validated['metode_pembayaran'] ?? null,
                'keterangan' => $validated['keterangan'] ?? null,
                'approved_at' => now(),
                'approved_by' => PelakuAksi::sekarang(),
            ]);

            // Update status tagihan jika sudah lunas
            if ($tagihan->lunasMenurutPembayaranDisetujui()) {
                $tagihan->update([
                    'status' => 'paid',
                    'tanggal_pembayaran' => $validated['tanggal_pembayaran'] ?? now(),
                ]);
            }

            DB::commit();
            $pembayaran->load(['tagihan.mahasiswa.prodi', 'tagihan.semester']);

            return response()->json($pembayaran, 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Terjadi kesalahan saat menyimpan pembayaran: '.$e->getMessage(),
            ], 500);
        }
    }

    public function show(Pembayaran $pembayaran): JsonResponse
    {
        $pembayaran->load([
            'tagihan.mahasiswa.prodi',
            'tagihan.semester',
            'tagihan.tagihanRinci.komponenBiaya',
        ]);
        $payload = $pembayaran->toArray();
        $base = rtrim((string) config('app.url'), '/');
        if (! empty($pembayaran->bukti_bayar)) {
            $payload['bukti_bayar_url'] = $base.'/storage/'.ltrim($pembayaran->bukti_bayar, '/');
        }

        return response()->json($payload);
    }

    /**
     * Setujui pembayaran (mengisi approved_at / approved_by) dan perbarui status tagihan jika lunas.
     */
    public function approve(Request $request, Pembayaran $pembayaran): JsonResponse
    {
        if ($pembayaran->approved_at !== null) {
            return response()->json([
                'message' => 'Pembayaran sudah disetujui sebelumnya.',
            ], 422);
        }

        $tagihan = $pembayaran->tagihan;
        $approver = PelakuAksi::sekarang();

        DB::beginTransaction();
        try {
            $pembayaran->update([
                'approved_at' => now(),
                'approved_by' => $approver,
            ]);

            if ($tagihan->lunasMenurutPembayaranDisetujui()) {
                $tagihan->update([
                    'status' => 'paid',
                    'tanggal_pembayaran' => $pembayaran->tanggal_pembayaran ?? now(),
                ]);
            }

            DB::commit();
            $pembayaran->refresh();
            $pembayaran->load([
                'tagihan.mahasiswa.prodi',
                'tagihan.semester',
                'tagihan.tagihanRinci.komponenBiaya',
            ]);

            $mahasiswa = $pembayaran->tagihan?->mahasiswa;
            if ($mahasiswa && $mahasiswa->id_user) {
                Notifikasi::kirim(
                    idUser: $mahasiswa->id_user,
                    tipe: 'pembayaran_acc',
                    judul: 'Pembayaran disetujui',
                    pesan: "Pembayaran {$pembayaran->no_pembayaran} untuk tagihan {$tagihan->no_tagihan} sudah disetujui.",
                    url: '/mahasiswa/tagihan',
                );
            }

            $payload = $pembayaran->toArray();
            $base = rtrim((string) config('app.url'), '/');
            if (! empty($pembayaran->bukti_bayar)) {
                $payload['bukti_bayar_url'] = $base.'/storage/'.ltrim($pembayaran->bukti_bayar, '/');
            }

            return response()->json($payload);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal menyetujui pembayaran: '.$e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Pembayaran $pembayaran): JsonResponse
    {
        $validated = $request->validate([
            'nominal' => ['sometimes', 'required', 'numeric', 'min:0'],
            'tanggal_pembayaran' => ['nullable', 'date'],
            'metode_pembayaran' => ['nullable', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string'],
        ], [
            'nominal.required' => 'Nominal harus diisi.',
            'nominal.numeric' => 'Nominal harus berupa angka.',
            'nominal.min' => 'Nominal tidak boleh negatif.',
        ]);

        // Get tagihan
        $tagihan = $pembayaran->tagihan;

        // Validate nominal jika diubah
        if (isset($validated['nominal'])) {
            $totalApprovedLain = Pembayaran::approvedQueryForTagihan($tagihan->id)
                ->where('id', '!=', $pembayaran->id)
                ->sum('nominal');
            $kredit = KeringananBiayaKreditService::kreditUntukTagihan($tagihan);

            if ((float) $tagihan->total - $kredit < (float) $validated['nominal'] + (float) $totalApprovedLain) {
                return response()->json([
                    'message' => 'Nominal pembayaran tidak boleh melebihi total tagihan.',
                ], 422);
            }
        }

        // Nominal yang sudah disetujui tidak boleh bergeser diam-diam: begitu diubah,
        // persetujuannya gugur dan pembayaran wajib di-ACC ulang oleh yang berwenang.
        $nominalBerubah = isset($validated['nominal'])
            && abs((float) $validated['nominal'] - (float) $pembayaran->nominal) > 0.001;
        $persetujuanDireset = $nominalBerubah && $pembayaran->approved_at !== null;

        DB::beginTransaction();
        try {
            // Update pembayaran
            $pembayaran->update([
                'nominal' => $validated['nominal'] ?? $pembayaran->nominal,
                'tanggal_pembayaran' => $validated['tanggal_pembayaran'] ?? $pembayaran->tanggal_pembayaran,
                'metode_pembayaran' => $validated['metode_pembayaran'] ?? $pembayaran->metode_pembayaran,
                'keterangan' => $validated['keterangan'] ?? $pembayaran->keterangan,
            ]);

            if ($persetujuanDireset) {
                $pembayaran->update(['approved_at' => null, 'approved_by' => null]);
            }

            if ($tagihan->lunasMenurutPembayaranDisetujui()) {
                $tagihan->update([
                    'status' => 'paid',
                    'tanggal_pembayaran' => $pembayaran->tanggal_pembayaran,
                ]);
            } else {
                $tagihan->update([
                    'status' => 'unpaid',
                    'tanggal_pembayaran' => null,
                ]);
            }

            DB::commit();
            $pembayaran->load(['tagihan.mahasiswa.prodi', 'tagihan.semester']);

            $payload = $pembayaran->toArray();
            $payload['persetujuan_direset'] = $persetujuanDireset;
            if ($persetujuanDireset) {
                $payload['message'] = 'Nominal berubah, sehingga persetujuan dicabut. Pembayaran ini perlu di-ACC ulang.';
            }

            return response()->json($payload);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Terjadi kesalahan saat memperbarui pembayaran: '.$e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Pembayaran $pembayaran): JsonResponse
    {
        DB::beginTransaction();
        try {
            $tagihan = $pembayaran->tagihan;

            // Delete pembayaran
            $pembayaran->delete();

            if ($tagihan->lunasMenurutPembayaranDisetujui()) {
                $tagihan->update([
                    'status' => 'paid',
                ]);
            } else {
                $tagihan->update([
                    'status' => 'unpaid',
                    'tanggal_pembayaran' => null,
                ]);
            }

            DB::commit();

            return response()->json(['message' => 'Pembayaran dihapus']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Terjadi kesalahan saat menghapus pembayaran: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download template import pembayaran
     * Template berisi tagihan yang belum lunas
     */
    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pembayaran');

        // Header
        $sheet->setCellValue('A1', 'No. Tagihan');
        $sheet->setCellValue('B1', 'NIM');
        $sheet->setCellValue('C1', 'Nama Mahasiswa');
        $sheet->setCellValue('D1', 'Prodi');
        $sheet->setCellValue('E1', 'Nominal');
        $sheet->setCellValue('F1', 'Tanggal Pembayaran');
        $sheet->setCellValue('G1', 'Metode Pembayaran');
        $sheet->setCellValue('H1', 'Keterangan');

        // Style header
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

        // Get all unpaid tagihan with pembayaran info
        $tagihan = Tagihan::whereIn('status', ['unpaid', 'expired'])
            ->with(['mahasiswa.prodi', 'semester'])
            ->orderBy('tanggal_tagihan', 'desc')
            ->get();

        $row = 2;
        foreach ($tagihan as $t) {
            $totalPembayaran = Pembayaran::approvedQueryForTagihan($t->id)->sum('nominal');
            $sisaTagihan = $t->total - $totalPembayaran;

            // Only include tagihan with remaining balance
            if ($sisaTagihan > 0) {
                $sheet->setCellValue('A'.$row, $t->no_tagihan);
                $sheet->setCellValue('B'.$row, $t->mahasiswa->nim ?? '');
                $sheet->setCellValue('C'.$row, $t->mahasiswa->nama ?? '');
                $sheet->setCellValue('D'.$row, $t->mahasiswa->prodi->nama ?? '');
                $sheet->setCellValue('E'.$row, $sisaTagihan); // Pre-fill with remaining balance
                $sheet->setCellValue('F'.$row, date('Y-m-d')); // Default to today
                $sheet->setCellValue('G'.$row, 'tunai'); // Default method
                $sheet->setCellValue('H'.$row, '');

                $row++;
            }
        }

        // Auto-size columns
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'template_import_pembayaran_'.date('YmdHis').'.xlsx';
        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment;filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Import pembayaran from XLSX file
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        if (count($rows) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'File kosong atau tidak valid.',
            ], 400);
        }

        // Remove header row
        array_shift($rows);

        $errors = [];
        $successCount = 0;
        $skipCount = 0;

        // Get the last pembayaran number for today before starting
        $date = date('Ymd');
        $prefix = "PAY-{$date}-";
        $lastPembayaran = Pembayaran::where('no_pembayaran', 'like', "{$prefix}%")
            ->orderBy('no_pembayaran', 'desc')
            ->first();
        $lastNumber = $lastPembayaran ? (int) substr($lastPembayaran->no_pembayaran, -4) : 0;
        $pembayaranCounter = 0;

        $importApprover = PelakuAksi::sekarang();

        DB::beginTransaction();
        try {
            foreach ($rows as $rowIndex => $row) {
                $rowNumber = $rowIndex + 2; // +2 because we removed header and array is 0-indexed

                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Kolom: A=No.Tagihan, B=NIM, C=Nama, D=Prodi, E=Nominal, F=Tanggal, G=Metode, H=Keterangan
                // Skip kolom NIM, Nama Mahasiswa, dan Prodi (kolom B, C, D) - hanya untuk informasi
                $noTagihan = trim($row[0] ?? '');
                $nominal = $row[4] ?? ''; // Kolom E (setelah NIM, Nama, Prodi)
                $tanggalPembayaran = ! empty(trim($row[5] ?? '')) ? trim($row[5]) : date('Y-m-d'); // Kolom F
                $metodePembayaran = ! empty(trim($row[6] ?? '')) ? trim($row[6]) : null; // Kolom G
                $keterangan = ! empty(trim($row[7] ?? '')) ? trim($row[7]) : null; // Kolom H

                // Validate required fields
                if (empty($noTagihan)) {
                    $errors[] = "Baris {$rowNumber}: No. Tagihan wajib diisi.";
                    $skipCount++;

                    continue;
                }

                if (empty($nominal) || ! is_numeric($nominal) || $nominal <= 0) {
                    $errors[] = "Baris {$rowNumber}: Nominal harus diisi dan berupa angka positif.";
                    $skipCount++;

                    continue;
                }

                // Find tagihan by no_tagihan
                $tagihan = Tagihan::where('no_tagihan', $noTagihan)->first();
                if (! $tagihan) {
                    $errors[] = "Baris {$rowNumber}: Tagihan dengan nomor '{$noTagihan}' tidak ditemukan.";
                    $skipCount++;

                    continue;
                }

                $totalPembayaran = Pembayaran::approvedQueryForTagihan($tagihan->id)->sum('nominal');
                $sisaTagihan = $tagihan->total - $totalPembayaran;

                if ($nominal > $sisaTagihan) {
                    $errors[] = "Baris {$rowNumber}: Nominal ({$nominal}) melebihi sisa tagihan (".number_format($sisaTagihan, 2, '.', '').').';
                    $skipCount++;

                    continue;
                }

                // Check if tagihan is already fully paid
                if ($sisaTagihan <= 0) {
                    $errors[] = "Baris {$rowNumber}: Tagihan '{$noTagihan}' sudah lunas.";
                    $skipCount++;

                    continue;
                }

                // Validate tanggal
                if (! empty($tanggalPembayaran)) {
                    $dateObj = \DateTime::createFromFormat('Y-m-d', $tanggalPembayaran);
                    if (! $dateObj || $dateObj->format('Y-m-d') !== $tanggalPembayaran) {
                        $errors[] = "Baris {$rowNumber}: Format tanggal pembayaran tidak valid. Gunakan format YYYY-MM-DD.";
                        $skipCount++;

                        continue;
                    }
                }

                // Generate nomor pembayaran (increment counter untuk setiap pembayaran dalam transaksi)
                $pembayaranCounter++;
                $newNumber = $lastNumber + $pembayaranCounter;
                $noPembayaran = $prefix.str_pad($newNumber, 4, '0', STR_PAD_LEFT);

                // Create pembayaran
                $pembayaran = Pembayaran::create([
                    'id_tagihan' => $tagihan->id,
                    'no_pembayaran' => $noPembayaran,
                    'nominal' => $nominal,
                    'tanggal_pembayaran' => $tanggalPembayaran,
                    'metode_pembayaran' => $metodePembayaran,
                    'keterangan' => $keterangan,
                    'approved_at' => now(),
                    'approved_by' => $importApprover,
                ]);

                // Update status tagihan jika sudah lunas
                if ($tagihan->lunasMenurutPembayaranDisetujui()) {
                    $tagihan->update([
                        'status' => 'paid',
                        'tanggal_pembayaran' => $tanggalPembayaran,
                    ]);
                }

                $successCount++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Import selesai. Berhasil: {$successCount}, Gagal: {$skipCount}.",
                'success_count' => $successCount,
                'error_count' => $skipCount,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat import: '.$e->getMessage(),
                'errors' => $errors,
            ], 500);
        }
    }

    /**
     * Mahasiswa mengunggah pembayaran (menunggu persetujuan admin).
     */
    public function storeByMahasiswa(Request $request): JsonResponse
    {
        $user = $request->user();
        $mahasiswa = Mahasiswa::where('id_user', $user->id)->first();

        if (! $mahasiswa) {
            return response()->json([
                'message' => 'Data mahasiswa tidak ditemukan',
            ], 404);
        }

        $validated = $request->validate([
            'id_tagihan' => ['required', 'integer', 'exists:tagihan,id'],
            'tipe_pembayaran' => ['required', 'string', Rule::in(['penuh', 'sebagian'])],
            'nominal' => ['required_if:tipe_pembayaran,sebagian', 'nullable', 'numeric', 'min:0.01'],
            'bukti_bayar' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ], [
            'id_tagihan.required' => 'Tagihan harus dipilih.',
            'tipe_pembayaran.required' => 'Pilih jenis pembayaran.',
            'bukti_bayar.required' => 'Bukti pembayaran wajib diunggah.',
        ]);

        $tagihan = Tagihan::whereNull('deleted_at')->findOrFail($validated['id_tagihan']);

        if ((int) $tagihan->id_mahasiswa !== (int) $mahasiswa->id) {
            return response()->json(['message' => 'Tagihan tidak ditemukan'], 404);
        }

        if ($tagihan->tanggal_tagihan) {
            $tgl = Carbon::parse($tagihan->tanggal_tagihan)->startOfDay();
            if ($tgl->gt(Carbon::today())) {
                return response()->json([
                    'message' => 'Tagihan ini belum berlaku.',
                ], 422);
            }
        }

        $totalSemua = (float) Pembayaran::where('id_tagihan', $tagihan->id)
            ->whereNull('deleted_at')
            ->sum('nominal');
        $kredit = KeringananBiayaKreditService::kreditUntukTagihan($tagihan);
        $sisaDapatDialokasikan = (float) $tagihan->total - $totalSemua - $kredit;

        if ($sisaDapatDialokasikan <= 0) {
            return response()->json([
                'message' => $kredit > 0
                    ? 'Tagihan ini sudah tidak memiliki sisa yang dapat dibayarkan setelah keringanan biaya (termasuk pembayaran yang menunggu verifikasi).'
                    : 'Tagihan ini sudah tidak memiliki sisa yang dapat dibayarkan (termasuk pembayaran yang menunggu verifikasi).',
            ], 422);
        }

        if ($validated['tipe_pembayaran'] === 'penuh') {
            $nominal = $sisaDapatDialokasikan;
        } else {
            $nominal = (float) $validated['nominal'];
            if ($nominal > $sisaDapatDialokasikan) {
                return response()->json([
                    'message' => 'Nominal melebihi sisa yang dapat dibayarkan.',
                ], 422);
            }
        }

        $path = null;
        DB::beginTransaction();
        try {
            $file = $request->file('bukti_bayar');
            $filename = 'bukti_tagihan_'.$tagihan->id.'_'.time().'_'.uniqid('', true).'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs('pembayaran/bukti', $filename, 'public');

            $noPembayaran = PenomoranDokumen::pembayaran();
            $pembayaran = Pembayaran::create([
                'id_tagihan' => $tagihan->id,
                'no_pembayaran' => $noPembayaran,
                'nominal' => $nominal,
                'tanggal_pembayaran' => now(),
                'metode_pembayaran' => 'upload_mahasiswa',
                'bukti_bayar' => $path,
                'keterangan' => null,
                'approved_at' => null,
                'approved_by' => null,
            ]);

            DB::commit();
            $pembayaran->load(['tagihan.semester']);

            return response()->json($pembayaran, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            if ($path) {
                Storage::disk('public')->delete($path);
            }

            return response()->json([
                'message' => 'Terjadi kesalahan saat menyimpan pembayaran: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get pembayaran untuk mahasiswa yang sedang login
     */
    public function getPembayaranMahasiswa(Request $request): JsonResponse
    {
        $user = $request->user();

        // Ambil data mahasiswa dari user
        $mahasiswa = Mahasiswa::where('id_user', $user->id)->first();

        if (! $mahasiswa) {
            return response()->json([
                'message' => 'Data mahasiswa tidak ditemukan',
            ], 404);
        }

        // Ambil semua pembayaran untuk tagihan mahasiswa ini
        $pembayaranList = Pembayaran::with([
            'tagihan.semester',
            'tagihan.tagihanRinci.komponenBiaya',
        ])
            ->whereHas('tagihan', function ($q) use ($mahasiswa) {
                $q->where('id_mahasiswa', $mahasiswa->id);
            })
            ->whereNull('deleted_at')
            ->orderBy('tanggal_pembayaran', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Format data pembayaran
        $baseUrl = rtrim((string) config('app.url'), '/');

        $formattedPembayaran = $pembayaranList->map(function ($pembayaran) use ($baseUrl) {
            $buktiPath = $pembayaran->bukti_bayar;

            return [
                'id' => $pembayaran->id,
                'no_pembayaran' => $pembayaran->no_pembayaran,
                'nominal' => $pembayaran->nominal,
                'tanggal_pembayaran' => $pembayaran->tanggal_pembayaran ? $pembayaran->tanggal_pembayaran->format('Y-m-d') : null,
                'metode_pembayaran' => $pembayaran->metode_pembayaran,
                'bukti_bayar' => $buktiPath,
                'bukti_bayar_url' => $buktiPath ? $baseUrl.'/storage/'.$buktiPath : null,
                'approved_at' => $pembayaran->approved_at ? $pembayaran->approved_at->format('Y-m-d H:i:s') : null,
                'approved_by' => $pembayaran->approved_by,
                'keterangan' => $pembayaran->keterangan,
                'tagihan' => [
                    'id' => $pembayaran->tagihan->id ?? null,
                    'no_tagihan' => $pembayaran->tagihan->no_tagihan ?? null,
                    'total' => $pembayaran->tagihan->total ?? null,
                    'status' => $pembayaran->tagihan->status ?? null,
                    'semester' => [
                        'id' => $pembayaran->tagihan->semester->id ?? null,
                        'kode' => $pembayaran->tagihan->semester->kode ?? null,
                        'nama' => $pembayaran->tagihan->semester->nama ?? null,
                    ],
                ],
            ];
        });

        return response()->json([
            'mahasiswa' => [
                'id' => $mahasiswa->id,
                'nim' => $mahasiswa->nim,
                'nama' => $mahasiswa->nama,
            ],
            'data' => $formattedPembayaran,
        ]);
    }
}
