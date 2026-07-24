<?php

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Models\PmbPendaftaran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PendaftaranController extends Controller
{
    /**
     * Menampilkan daftar pendaftaran dengan pagination, search, dan filter.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');
        $status = $request->get('status');
        $idPeriode = $request->get('id_periode');
        $idJalurMasuk = $request->get('id_jalur_masuk');
        $idJenisDaftar = $request->get('id_jenis_daftar');
        $idCamaba = $request->get('id_camaba');
        $excludeHerregistrasiSelesai = $request->boolean('exclude_herregistrasi_selesai');

        $query = PmbPendaftaran::with([
            'camaba',
            'periode',
            'jalurMasuk',
            'jenisDaftar',
            'prodiPilih.prodi.jenjang',
        ]);

        // Filter by camaba
        if ($idCamaba) {
            $query->where('id_camaba', $idCamaba);
        }

        // Hanya pendaftaran yang camaba-nya belum menyelesai herregistrasi (kolom kosong atau bukan `herregistrasi`)
        if ($excludeHerregistrasiSelesai) {
            $query->whereHas('camaba', static function ($q): void {
                $q->where(static function ($inner): void {
                    $inner->whereNull('status_herregistrasi')
                        ->orWhere('status_herregistrasi', '!=', 'herregistrasi');
                });
            });
        }

        // Filter by status
        if ($status) {
            $query->where('status', $status);
        }

        // Filter by periode
        if ($idPeriode) {
            $query->where('id_periode', $idPeriode);
        }

        // Filter by jalur masuk
        if ($idJalurMasuk) {
            $query->where('id_jalur_masuk', $idJalurMasuk);
        }

        // Filter by jenis daftar
        if ($idJenisDaftar) {
            $query->where('id_jenis_daftar', $idJenisDaftar);
        }

        // Search by no_pendaftaran, nama camaba, atau email camaba
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('no_pendaftaran', 'like', "%{$search}%")
                    ->orWhereHas('camaba', function ($camabaQuery) use ($search) {
                        $camabaQuery->where('nama', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $data = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Menampilkan detail pendaftaran.
     */
    public function show(PmbPendaftaran $pendaftaran): JsonResponse
    {
        $pendaftaran->load([
            'camaba.user',
            'periode',
            'jalurMasuk',
            'jenisDaftar',
            'prodiPilih.prodi.jenjang',
        ]);

        // Load dokumen and pembayaran separately since they might not have direct relationships defined
        $dokumen = \App\Models\PmbDokumen::where('id_pendaftaran', $pendaftaran->id)
            ->with('persyaratan')
            ->get();

        $pembayaran = \App\Models\PmbPembayaran::where('id_pendaftaran', $pendaftaran->id)
            ->with('biaya')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'pendaftaran' => $pendaftaran,
                'dokumen' => $dokumen,
                'pembayaran' => $pembayaran,
            ],
        ]);
    }

    /**
     * Memperbarui status administrasi pendaftaran (ACC atau tidak / gagal).
     */
    public function updateStatus(Request $request, PmbPendaftaran $pendaftaran): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:acc,gagal'],
            'keterangan' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $updates = ['status' => $validated['status']];
        if (array_key_exists('keterangan', $validated)) {
            $updates['keterangan'] = $validated['keterangan'];
        }
        $pendaftaran->update($updates);
        $pendaftaran->refresh();

        $message = $validated['status'] === 'acc'
            ? 'Status pendaftaran diperbarui menjadi ACC.'
            : 'Status pendaftaran diperbarui (tidak ACC).';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $pendaftaran,
        ]);
    }

    /**
     * Menampilkan daftar pendaftaran untuk seleksi (yang pembayarannya sudah lunas).
     */
    public function seleksi(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $query = $this->buildSeleksiQuery($request);
        $data = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Ekspor daftar seleksi ke Excel (mengikuti filter endpoint seleksi).
     */
    public function exportSeleksi(Request $request): StreamedResponse
    {
        $rows = $this->buildSeleksiQuery($request)
            ->orderBy('created_at', 'desc')
            ->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Seleksi PMB');
        $sheet->fromArray([[
            'No',
            'No. Pendaftaran',
            'Nama Camaba',
            'Periode',
            'Jalur Masuk',
            'Jenis Daftar',
            'Prodi Pilihan',
            'Tanggal Pendaftaran',
            'Hasil Seleksi',
            'Keterangan Seleksi',
        ]], null, 'A1');

        $dataRows = [];
        $no = 1;
        foreach ($rows as $pendaftaran) {
            $dataRows[] = [
                $no++,
                $pendaftaran->no_pendaftaran ?? '',
                $pendaftaran->camaba?->nama ?? '',
                $pendaftaran->periode?->nama ?? '',
                $pendaftaran->jalurMasuk?->nama ?? '',
                $pendaftaran->jenisDaftar?->nama ?? '',
                $this->formatProdiPilihan($pendaftaran),
                $pendaftaran->tanggal_pendaftaran?->format('Y-m-d') ?? '',
                $pendaftaran->hasilSeleksi?->status ?? 'belum dinilai',
                $pendaftaran->hasilSeleksi?->keterangan ?? '',
            ];
        }
        if ($dataRows !== []) {
            $sheet->fromArray($dataRows, null, 'A2');
        }

        $filename = 'seleksi-pmb-'.date('Y-m-d-His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function buildSeleksiQuery(Request $request): Builder
    {
        $search = trim((string) $request->get('search', ''));
        $idPeriode = $request->get('id_periode');
        $idJalurMasuk = $request->get('id_jalur_masuk');
        $idJenisDaftar = $request->get('id_jenis_daftar');
        $idProdi = $request->get('id_prodi');

        $query = PmbPendaftaran::with([
            'camaba',
            'periode',
            'jalurMasuk',
            'jenisDaftar',
            'prodiPilih.prodi.jenjang',
            'hasilSeleksi:id,id_pendaftaran,status,nilai,peringkat,keterangan',
        ]);

        $query->where('status', 'acc');

        if ($idPeriode) {
            $query->where('id_periode', $idPeriode);
        }
        if ($idJalurMasuk) {
            $query->where('id_jalur_masuk', $idJalurMasuk);
        }
        if ($idJenisDaftar) {
            $query->where('id_jenis_daftar', $idJenisDaftar);
        }
        if ($idProdi) {
            $query->whereHas('prodiPilih', static function ($q) use ($idProdi): void {
                $q->where('id_prodi', $idProdi);
            });
        }

        // Lolos ke seleksi jika pembayaran sudah ACC ("paid"), ATAU jalur masuknya memang
        // dibebaskan dari biaya (`jalur_masuk.is_free_of_charge`) sehingga tidak butuh pembayaran.
        $query->where(function ($q): void {
            $q->whereHas('pembayaran', static function ($pembayaranQuery): void {
                $pembayaranQuery->where('status', 'paid');
            })->orWhereHas('jalurMasuk', static function ($jalurQuery): void {
                $jalurQuery->where('is_free_of_charge', true);
            });
        });

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('no_pendaftaran', 'like', "%{$search}%")
                    ->orWhereHas('camaba', function ($camabaQuery) use ($search): void {
                        $camabaQuery->where('nama', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }

    private function formatProdiPilihan(PmbPendaftaran $pendaftaran): string
    {
        if (! $pendaftaran->relationLoaded('prodiPilih') || $pendaftaran->prodiPilih->isEmpty()) {
            return '';
        }

        $labels = [];
        foreach ($pendaftaran->prodiPilih as $pilih) {
            $prodi = $pilih->prodi;
            if (! $prodi) {
                continue;
            }
            $labels[] = trim(($prodi->kode ? $prodi->kode.' — ' : '').$prodi->nama);
        }

        return implode('; ', $labels);
    }
}
