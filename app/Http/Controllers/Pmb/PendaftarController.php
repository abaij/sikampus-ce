<?php

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Models\PmbCamaba;
use App\Models\PmbPendaftaran;
use App\Models\PmbPeriode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PendaftarController extends Controller
{
    /**
     * Menampilkan daftar pendaftar dengan pagination, search, dan filter.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $ctx = $this->resolveListContext($request);

        $search = $request->get('search');
        $accountStatus = $request->get('status');

        $with = ['user', 'kotaLahir', 'kota', 'kecamatan', 'provinsi', 'negara', 'agama'];
        $eagerPid = $ctx['eagerPeriodeId'];
        $with['pendaftarans'] = static function ($q) use ($eagerPid): void {
            if ($eagerPid) {
                $q->where('id_periode', $eagerPid)
                    ->with([
                        'prodiPilih' => static function ($pq): void {
                            $pq->orderBy('id');
                        },
                        'prodiPilih.prodi:id,nama,kode',
                        'jalurMasuk:id,nama',
                        'jenisDaftar:id,nama',
                    ])
                    ->select([
                        'id',
                        'id_camaba',
                        'id_periode',
                        'status',
                        'no_pendaftaran',
                        'tanggal_pendaftaran',
                        'id_jalur_masuk',
                        'id_jenis_daftar',
                    ]);
            } else {
                $q->whereRaw('1 = 0');
            }
        };

        $query = PmbCamaba::query()->with($with);
        $this->applyListConstraints($query, $ctx);

        if ($accountStatus) {
            $query->where('status', $accountStatus);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('no_hp', 'like', "%{$search}%")
                    ->orWhere('no_wa', 'like', "%{$search}%");
            });
        }

        $data = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Ekspor daftar pendaftar (Excel) sesuai filter halaman indeks — tanpa paginasi.
     */
    public function export(Request $request): StreamedResponse
    {
        $ctx = $this->resolveListContext($request);
        $search = $request->get('search');
        $accountStatus = $request->get('status');

        $eagerPid = $ctx['eagerPeriodeId'];
        $with = [
            'pendaftarans' => static function ($q) use ($eagerPid): void {
                if ($eagerPid) {
                    $q->where('id_periode', $eagerPid)
                        ->with([
                            'prodiPilih' => static function ($pq): void {
                                $pq->orderBy('id');
                            },
                            'prodiPilih.prodi:id,nama,kode',
                            'jalurMasuk:id,nama',
                            'jenisDaftar:id,nama',
                        ])
                        ->select([
                            'id',
                            'id_camaba',
                            'id_periode',
                            'status',
                            'no_pendaftaran',
                            'tanggal_pendaftaran',
                            'id_jalur_masuk',
                            'id_jenis_daftar',
                        ]);
                } else {
                    $q->whereRaw('1 = 0');
                }
            },
        ];

        $query = PmbCamaba::query()->with($with);
        $this->applyListConstraints($query, $ctx);

        if ($accountStatus) {
            $query->where('status', $accountStatus);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('no_hp', 'like', "%{$search}%")
                    ->orWhere('no_wa', 'like', "%{$search}%");
            });
        }

        $rows = $query->orderBy('nama')->orderBy('id')->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pendaftar');

        $headers = [
            'No',
            'Nama',
            'Email',
            'NIM',
            'No. HP',
            'No. WA',
            'Jenis Kelamin',
            'Tanggal Lahir',
            'Alamat',
            'NISN',
            'NPSN',
            'Status Akun',
            'No. Pendaftaran',
            'Status Pendaftaran',
            'Program Studi Pilihan',
            'Jalur Masuk',
            'Jenis Daftar',
        ];
        $sheet->fromArray([$headers], null, 'A1');

        $dataRows = [];
        $num = 1;
        foreach ($rows as $pendaftar) {
            /** @var PmbPendaftaran|null $pendaftaran */
            $pendaftaran = ($eagerPid ? $pendaftar->pendaftarans->firstWhere('id_periode', $eagerPid) : null)
                ?? $pendaftar->pendaftarans->first();

            $dataRows[] = [
                $num++,
                $pendaftar->nama ?? '',
                $pendaftar->email ?? '',
                $pendaftar->nim ?? '',
                $pendaftar->no_hp ?? '',
                $pendaftar->no_wa ?? '',
                $pendaftar->jenis_kelamin ?? '',
                $pendaftar->tanggal_lahir?->format('Y-m-d') ?? '',
                $pendaftar->alamat ?? '',
                $pendaftar->nisn ?? '',
                $pendaftar->npsn ?? '',
                $pendaftar->status ?? '',
                $pendaftaran?->no_pendaftaran ?? '',
                $pendaftaran?->status ?? '',
                $this->formatProdiPilihanPlain($pendaftaran),
                $pendaftaran?->jalurMasuk->nama ?? '',
                $pendaftaran?->jenisDaftar->nama ?? '',
            ];
        }
        if ($dataRows !== []) {
            $sheet->fromArray($dataRows, null, 'A2');
        }

        $filename = 'pendaftar-pmb-'.date('Y-m-d-His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Menampilkan detail pendaftar.
     */
    public function show(PmbCamaba $pendaftar): JsonResponse
    {
        $periodeAktifId = PmbPeriode::query()->where('is_active', true)->value('id');

        $with = ['user', 'kotaLahir', 'kota', 'kecamatan', 'provinsi', 'negara', 'agama'];
        $with['pendaftarans'] = static function ($q) use ($periodeAktifId): void {
            if ($periodeAktifId) {
                $q->where('id_periode', $periodeAktifId)->select(['id', 'id_camaba', 'id_periode', 'status', 'no_pendaftaran', 'tanggal_pendaftaran']);
            } else {
                $q->whereRaw('1 = 0');
            }
        };
        $with['emailLogs'] = static function ($q): void {
            $q->orderByDesc('created_at')
                ->limit(100)
                ->select([
                    'id',
                    'id_camaba',
                    'email',
                    'subject',
                    'body',
                    'status',
                    'error',
                    'sent_at',
                    'created_at',
                ]);
        };

        $pendaftar->load($with);

        return response()->json([
            'success' => true,
            'data' => $pendaftar,
        ]);
    }

    /**
     * Update biodata pendaftar.
     */
    public function update(Request $request, PmbCamaba $pendaftar): JsonResponse
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'email' => 'required|email|unique:pmb_camaba,email,'.$pendaftar->id,
            'id_kota_lahir' => 'nullable|string',
            'tanggal_lahir' => 'nullable|date',
            'jenis_kelamin' => 'nullable|in:Laki-laki,Perempuan',
            'no_hp' => 'nullable|string|max:20',
            'no_wa' => 'nullable|string|max:20',
            'alamat' => 'nullable|string',
            'kode_pos' => 'nullable|string|max:10',
            'rt' => 'nullable|string|max:10',
            'rw' => 'nullable|string|max:10',
            'dusun' => 'nullable|string|max:100',
            'kelurahan' => 'nullable|string|max:100',
            'id_kota' => 'nullable|string',
            'id_kecamatan' => 'nullable|string',
            'id_provinsi' => 'nullable|string',
            'id_negara' => 'nullable|integer|exists:negara,id',
            'no_ktp' => 'nullable|string|max:20',
            'no_kk' => 'nullable|string|max:20',
            'no_npwp' => 'nullable|string|max:20',
            'no_sim' => 'nullable|string|max:20',
            'no_kps' => 'nullable|string|max:20',
            'nama_ayah' => 'nullable|string|max:255',
            'nama_ibu' => 'nullable|string|max:255',
            'nama_wali' => 'nullable|string|max:255',
            'no_hp_ayah' => 'nullable|string|max:20',
            'no_hp_ibu' => 'nullable|string|max:20',
            'no_hp_wali' => 'nullable|string|max:20',
            'alamat_ayah' => 'nullable|string',
            'alamat_ibu' => 'nullable|string',
            'alamat_wali' => 'nullable|string',
            'id_agama' => 'nullable|integer|exists:agama,id',
            'status_perkawinan' => 'nullable|in:Tidak Kawin,Kawin',
            'kewarganegaraan' => 'nullable|in:WNI,WNA',
            'asal_sekolah' => 'nullable|string|max:255',
            'nisn' => 'nullable|string|max:20',
            'npsn' => 'nullable|string|max:20',
            'status' => 'nullable|in:active,inactive',
        ]);

        $pendaftar->update($validated);
        $pendaftar->load(['user', 'kotaLahir', 'kota', 'kecamatan', 'provinsi', 'negara', 'agama']);

        return response()->json([
            'success' => true,
            'message' => 'Biodata pendaftar berhasil diperbarui',
            'data' => $pendaftar,
        ]);
    }

    /**
     * @return array{eagerPeriodeId: ?int, filterPeriodeId: ?int, pendaftaranStatus: ?string, idProdi: ?int, idJalurMasuk: ?int}
     */
    private function resolveListContext(Request $request): array
    {
        $periodeAktifId = PmbPeriode::query()->where('is_active', true)->value('id');

        $pendaftaranStatusParam = $request->get('pendaftaran_status');
        $pendaftaranStatus = is_string($pendaftaranStatusParam) && $pendaftaranStatusParam !== ''
            ? $pendaftaranStatusParam
            : null;

        $idPeriodeParam = $request->get('id_periode');
        $filterPeriodeId = null;
        if ($idPeriodeParam !== null && $idPeriodeParam !== '') {
            $filterPeriodeId = (int) $idPeriodeParam;
            if ($filterPeriodeId <= 0) {
                $filterPeriodeId = null;
            }
        }

        $idProdiParam = $request->get('id_prodi');
        $idProdi = null;
        if ($idProdiParam !== null && $idProdiParam !== '') {
            $idProdi = (int) $idProdiParam;
            if ($idProdi <= 0) {
                $idProdi = null;
            }
        }

        $idJalurMasukParam = $request->get('id_jalur_masuk');
        $idJalurMasuk = null;
        if ($idJalurMasukParam !== null && $idJalurMasukParam !== '') {
            $idJalurMasuk = (int) $idJalurMasukParam;
            if ($idJalurMasuk <= 0) {
                $idJalurMasuk = null;
            }
        }

        return [
            'filterPeriodeId' => $filterPeriodeId,
            'periodeAktifId' => $periodeAktifId,
            'eagerPeriodeId' => $filterPeriodeId ?? $periodeAktifId,
            'pendaftaranStatus' => $pendaftaranStatus,
            'idProdi' => $idProdi,
            'idJalurMasuk' => $idJalurMasuk,
        ];
    }

    private function applyListConstraints(Builder $query, array $ctx): void
    {
        $filterPeriodeId = $ctx['filterPeriodeId'];
        $pendaftaranStatus = $ctx['pendaftaranStatus'];
        $idProdi = $ctx['idProdi'];
        $idJalurMasuk = $ctx['idJalurMasuk'];

        if ($filterPeriodeId || $pendaftaranStatus !== null || $idProdi !== null || $idJalurMasuk !== null) {
            $query->whereHas('pendaftarans', static function ($q) use ($filterPeriodeId, $pendaftaranStatus, $idProdi, $idJalurMasuk): void {
                if ($filterPeriodeId) {
                    $q->where('id_periode', $filterPeriodeId);
                }
                if ($pendaftaranStatus !== null) {
                    $q->where('status', $pendaftaranStatus);
                }
                if ($idJalurMasuk !== null) {
                    $q->where('id_jalur_masuk', $idJalurMasuk);
                }
                if ($idProdi !== null) {
                    $q->whereHas('prodiPilih', static function ($pq) use ($idProdi): void {
                        $pq->where('id_prodi', $idProdi);
                    });
                }
            });
        }
    }

    private function formatProdiPilihanPlain(?PmbPendaftaran $pendaftaran): string
    {
        if (! $pendaftaran || ! $pendaftaran->relationLoaded('prodiPilih')) {
            return '';
        }

        $parts = [];
        foreach ($pendaftaran->prodiPilih->sortBy('id') as $pilih) {
            $prodi = $pilih->prodi;
            if ($prodi) {
                $label = trim(($prodi->kode ? $prodi->kode.' — ' : '').($prodi->nama ?? ''));
                $parts[] = $label !== '' ? $label : (string) $pilih->id_prodi;
            } else {
                $parts[] = (string) $pilih->id_prodi;
            }
        }

        return implode('; ', $parts);
    }
}
