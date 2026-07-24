<?php

namespace App\Http\Controllers;

use App\Models\KategoriBiaya;
use App\Models\KomponenBiaya;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\StrukturBiaya;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StrukturBiayaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');
        $kategoriBiayaId = $request->get('id_kategori_biaya');
        $prodiId = $request->get('id_prodi');
        $angkatanId = $request->get('id_angkatan');
        $komponenBiayaId = $request->get('id_komponen_biaya');

        $query = StrukturBiaya::with([
            'kategoriBiaya',
            'prodi',
            'angkatan',
            'periode',
            'komponenBiaya',
        ]);

        $allowedProdiIds = $this->allowedProdiIdsForUser($request->user());
        if ($allowedProdiIds !== null) {
            if ($allowedProdiIds === []) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereIn('id_prodi', $allowedProdiIds);
            }
            if ($prodiId !== null && $prodiId !== '' && ! in_array((int) $prodiId, $allowedProdiIds, true)) {
                $prodiId = null;
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('kategoriBiaya', function ($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%")
                        ->orWhere('kode', 'like', "%{$search}%");
                })
                    ->orWhereHas('prodi', function ($q) use ($search) {
                        $q->where('nama', 'like', "%{$search}%")
                            ->orWhere('kode', 'like', "%{$search}%");
                    })
                    ->orWhereHas('angkatan', function ($q) use ($search) {
                        $q->where('nama', 'like', "%{$search}%")
                            ->orWhere('kode', 'like', "%{$search}%");
                    })
                    ->orWhereHas('periode', function ($q) use ($search) {
                        $q->where('nama', 'like', "%{$search}%")
                            ->orWhere('kode', 'like', "%{$search}%");
                    })
                    ->orWhereHas('komponenBiaya', function ($q) use ($search) {
                        $q->where('nama', 'like', "%{$search}%")
                            ->orWhere('kode', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->has('id_kategori_biaya')) {
            $v = $request->get('id_kategori_biaya');
            if ($v === '' || $v === null) {
                $query->whereNull('id_kategori_biaya');
            } else {
                $query->where('id_kategori_biaya', $v);
            }
        } elseif ($kategoriBiayaId !== null && $kategoriBiayaId !== '') {
            $query->where('id_kategori_biaya', $kategoriBiayaId);
        }

        if ($request->has('id_prodi')) {
            $v = $request->get('id_prodi');
            if ($v === '' || $v === null) {
                if ($allowedProdiIds === null) {
                    $query->whereNull('id_prodi');
                }
            } else {
                $vid = (int) $v;
                if ($allowedProdiIds !== null && ! in_array($vid, $allowedProdiIds, true)) {
                    $query->whereRaw('0 = 1');
                } else {
                    $query->where('id_prodi', $vid);
                }
            }
        } elseif ($prodiId !== null && $prodiId !== '') {
            $query->where('id_prodi', (int) $prodiId);
        }

        if ($angkatanId !== null && $angkatanId !== '') {
            $query->where('id_angkatan', $angkatanId);
        }

        // id_periode: parameter tidak ada = default semester berstatus aktif (tabel semester.is_active);
        // id_periode dikirim kosong = tampilkan semua periode (tanpa filter id_periode).
        $queryPeriode = $request->query->all();
        if (array_key_exists('id_periode', $queryPeriode)) {
            $periodeRaw = $queryPeriode['id_periode'];
            if ($periodeRaw !== null && $periodeRaw !== '') {
                $query->where('id_periode', $periodeRaw);
            }
        } else {
            $activePeriodeId = Semester::where('is_active', true)->value('id');
            if ($activePeriodeId) {
                $query->where('id_periode', $activePeriodeId);
            }
        }

        if ($request->has('id_komponen_biaya')) {
            $v = $request->get('id_komponen_biaya');
            if ($v === '' || $v === null) {
                $query->whereNull('id_komponen_biaya');
            } else {
                $query->where('id_komponen_biaya', $v);
            }
        } elseif ($komponenBiayaId !== null && $komponenBiayaId !== '') {
            $query->where('id_komponen_biaya', $komponenBiayaId);
        }

        $tahapFilter = $request->get('tahap');
        if ($tahapFilter !== null && $tahapFilter !== '') {
            $query->where('tahap', (int) $tahapFilter);
        }

        $data = $query->orderBy('id_angkatan', 'desc')
            ->orderBy('id_periode', 'desc')
            ->orderBy('id_kategori_biaya')
            ->orderBy('id_prodi')
            ->orderBy('tahap')
            ->paginate($perPage);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_kategori_biaya' => ['nullable', 'integer', 'exists:kategori_biaya,id'],
            'id_prodi' => ['nullable', 'integer', 'exists:prodi,id'],
            'id_angkatan' => ['required', 'integer', 'exists:semester,id'],
            'id_periode' => ['required', 'integer', 'exists:semester,id'],
            'id_komponen_biaya' => ['nullable', 'integer', 'exists:komponen_biaya,id'],
            'tahap' => ['nullable', 'integer', 'min:1'],
            'nominal' => ['required', 'numeric', 'min:0'],
        ], [
            'id_angkatan.required' => 'Angkatan (semester masuk) harus dipilih.',
            'id_angkatan.exists' => 'Angkatan tidak valid.',
            'id_periode.required' => 'Periode berlaku harus dipilih.',
            'id_periode.exists' => 'Periode berlaku tidak valid.',
            'nominal.required' => 'Nominal harus diisi.',
            'nominal.numeric' => 'Nominal harus berupa angka.',
            'nominal.min' => 'Nominal tidak boleh negatif.',
        ]);

        $validated['tahap'] = $validated['tahap'] ?? 1;

        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowed = $user->getAllowedProdiIds();
            if ($allowed !== null) {
                $pid = $validated['id_prodi'] ?? null;
                if ($pid === null) {
                    return response()->json([
                        'message' => 'Program studi wajib dipilih dan harus dalam lingkup akses Anda.',
                    ], 422);
                }
                if (! in_array((int) $pid, $allowed, true)) {
                    return response()->json([
                        'message' => 'Anda tidak memiliki akses ke program studi ini.',
                    ], 403);
                }
            }
        }

        $exists = StrukturBiaya::where('id_kategori_biaya', $validated['id_kategori_biaya'] ?? null)
            ->where('id_prodi', $validated['id_prodi'] ?? null)
            ->where('id_angkatan', $validated['id_angkatan'])
            ->where('id_periode', $validated['id_periode'])
            ->where('id_komponen_biaya', $validated['id_komponen_biaya'] ?? null)
            ->where('tahap', $validated['tahap'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Struktur biaya dengan kombinasi kategori biaya, prodi, angkatan, periode, komponen biaya, dan tahap ini sudah ada.',
            ], 422);
        }

        $strukturBiaya = StrukturBiaya::create($validated);
        $strukturBiaya->load(['kategoriBiaya', 'prodi', 'angkatan', 'periode', 'komponenBiaya']);

        return response()->json($strukturBiaya, 201);
    }

    public function show(Request $request, StrukturBiaya $struktur_biaya): JsonResponse
    {
        $this->assertStrukturBiayaInUserScope($request, $struktur_biaya);
        $struktur_biaya->load(['kategoriBiaya', 'prodi', 'angkatan', 'periode', 'komponenBiaya']);

        return response()->json($struktur_biaya);
    }

    public function update(Request $request, StrukturBiaya $struktur_biaya): JsonResponse
    {
        $this->assertStrukturBiayaInUserScope($request, $struktur_biaya);

        $validated = $request->validate([
            'id_kategori_biaya' => ['nullable', 'integer', 'exists:kategori_biaya,id'],
            'id_prodi' => ['nullable', 'integer', 'exists:prodi,id'],
            'id_angkatan' => ['sometimes', 'required', 'integer', 'exists:semester,id'],
            'id_periode' => ['sometimes', 'required', 'integer', 'exists:semester,id'],
            'id_komponen_biaya' => ['nullable', 'integer', 'exists:komponen_biaya,id'],
            'tahap' => ['nullable', 'integer', 'min:1'],
            'nominal' => ['sometimes', 'required', 'numeric', 'min:0'],
        ], [
            'id_angkatan.required' => 'Angkatan (semester masuk) harus dipilih.',
            'id_angkatan.exists' => 'Angkatan tidak valid.',
            'id_periode.required' => 'Periode berlaku harus dipilih.',
            'id_periode.exists' => 'Periode berlaku tidak valid.',
            'nominal.required' => 'Nominal harus diisi.',
            'nominal.numeric' => 'Nominal harus berupa angka.',
            'nominal.min' => 'Nominal tidak boleh negatif.',
        ]);

        if (isset($validated['tahap']) && $validated['tahap'] === null) {
            $validated['tahap'] = 1;
        }

        $user = $request->user();
        if ($user && $user->hasScopeRestriction() && array_key_exists('id_prodi', $validated)) {
            $allowed = $user->getAllowedProdiIds();
            if ($allowed !== null) {
                $newPid = $validated['id_prodi'];
                if ($newPid === null) {
                    return response()->json([
                        'message' => 'Program studi wajib dipilih dan harus dalam lingkup akses Anda.',
                    ], 422);
                }
                if (! in_array((int) $newPid, $allowed, true)) {
                    return response()->json([
                        'message' => 'Anda tidak memiliki akses ke program studi ini.',
                    ], 403);
                }
            }
        }

        if (isset($validated['id_kategori_biaya']) || isset($validated['id_prodi']) || isset($validated['id_angkatan'])
            || isset($validated['id_periode'])
            || isset($validated['id_komponen_biaya']) || isset($validated['tahap'])) {
            $kategoriBiayaId = $validated['id_kategori_biaya'] ?? $struktur_biaya->id_kategori_biaya;
            $prodiId = $validated['id_prodi'] ?? $struktur_biaya->id_prodi;
            $angkatanId = $validated['id_angkatan'] ?? $struktur_biaya->id_angkatan;
            $periodeId = $validated['id_periode'] ?? $struktur_biaya->id_periode;
            $komponenBiayaId = array_key_exists('id_komponen_biaya', $validated)
                ? $validated['id_komponen_biaya']
                : $struktur_biaya->id_komponen_biaya;
            $tahap = $validated['tahap'] ?? $struktur_biaya->tahap;

            $exists = StrukturBiaya::where('id_kategori_biaya', $kategoriBiayaId)
                ->where('id_prodi', $prodiId)
                ->where('id_angkatan', $angkatanId)
                ->where('id_periode', $periodeId)
                ->where('id_komponen_biaya', $komponenBiayaId)
                ->where('tahap', $tahap)
                ->where('id', '!=', $struktur_biaya->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Struktur biaya dengan kombinasi kategori biaya, prodi, angkatan, periode, komponen biaya, dan tahap ini sudah ada.',
                ], 422);
            }
        }

        $struktur_biaya->update($validated);
        $struktur_biaya->load(['kategoriBiaya', 'prodi', 'angkatan', 'periode', 'komponenBiaya']);

        return response()->json($struktur_biaya);
    }

    public function destroy(Request $request, StrukturBiaya $struktur_biaya): JsonResponse
    {
        $this->assertStrukturBiayaInUserScope($request, $struktur_biaya);
        $struktur_biaya->delete();

        return response()->json(['message' => 'Struktur biaya dihapus']);
    }

    /**
     * Unduh template Excel impor struktur biaya.
     * Kolom: nama kategori biaya, nama komponen biaya, kode prodi, kode angkatan & periode (semester), tahap, nominal.
     */
    public function downloadImportTemplate(Request $request): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Struktur Biaya');

        $headers = [
            'nama_kategori_biaya',
            'nama_komponen_biaya',
            'kode_prodi',
            'kode_angkatan',
            'kode_periode',
            'tahap',
            'nominal',
        ];

        $headerLabels = [
            'Nama Kategori Biaya (opsional, kosongkan jika tidak dipakai)',
            'Nama Komponen Biaya (opsional)',
            'Kode Prodi (opsional, kosongkan untuk semua prodi)',
            'Kode Semester Angkatan Masuk*',
            'Kode Semester Periode Berlaku*',
            'Tahap (default 1)',
            'Nominal*',
        ];

        $sheet->fromArray([$headerLabels], null, 'A1');

        $widths = [38, 38, 22, 28, 28, 12, 16];
        foreach ($widths as $i => $w) {
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth($w);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '047857'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                'wrapText' => true,
            ],
        ];
        $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(36);

        // Baris kunci teknis (disembunyikan / dipakai sebagai petunjuk di baris 2)
        $sheet->fromArray([$headers], null, 'A2');
        $sheet->getStyle('A2:G2')->getFont()->setItalic(true)->getColor()->setRGB('64748B');
        $sheet->getRowDimension(2)->setVisible(false);

        $sheet->fromArray([
            [
                'Contoh: SPP',
                'Contoh: SPP',
                'TI',
                '20231',
                '20232',
                '1',
                '5000000',
            ],
        ], null, 'A3');

        $ref = $spreadsheet->createSheet();
        $ref->setTitle('Referensi');
        $ref->fromArray([['Nama Kategori Biaya']], null, 'A1');
        $ref->fromArray([['Nama Komponen Biaya']], null, 'C1');
        $ref->fromArray([['Kode Prodi', 'Nama Prodi']], null, 'E1');
        $ref->fromArray([['Kode Semester', 'Nama Semester']], null, 'H1');

        $katRows = KategoriBiaya::query()->orderBy('nama')->get(['nama']);
        $r = 2;
        foreach ($katRows as $k) {
            $ref->setCellValue("A{$r}", $k->nama);
            $r++;
        }

        $kompRows = KomponenBiaya::query()->orderBy('nama')->get(['nama']);
        $r = 2;
        foreach ($kompRows as $k) {
            $ref->setCellValue("C{$r}", $k->nama);
            $r++;
        }

        $prodiBase = Prodi::query()->orderBy('kode');
        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowed = $user->getAllowedProdiIds();
            if ($allowed !== null) {
                $prodiRows = $allowed === []
                    ? collect()
                    : $prodiBase->whereIn('id', $allowed)->get(['kode', 'nama']);
            } else {
                $prodiRows = $prodiBase->get(['kode', 'nama']);
            }
        } else {
            $prodiRows = $prodiBase->get(['kode', 'nama']);
        }
        $r = 2;
        foreach ($prodiRows as $p) {
            $ref->setCellValue("E{$r}", $p->kode);
            $ref->setCellValue("F{$r}", $p->nama);
            $r++;
        }

        $semRows = Semester::query()->orderByDesc('kode')->get(['kode', 'nama']);
        $r = 2;
        foreach ($semRows as $s) {
            $ref->setCellValue("H{$r}", $s->kode);
            $ref->setCellValue("I{$r}", $s->nama);
            $r++;
        }

        $ref->getStyle('A1:I1')->applyFromArray($headerStyle);
        foreach (['A', 'C', 'E', 'F', 'H', 'I'] as $col) {
            $ref->getColumnDimension($col)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'template_import_struktur_biaya_'.date('YmdHis').'.xlsx';

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
     * Impor struktur biaya dari Excel (sheet pertama "Struktur Biaya" atau sheet indeks 0).
     */
    public function importSpreadsheet(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getSheetByName('Struktur Biaya') ?? $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // Baris 1 = label; baris 2 (jika ada) = kunci teknis (nama kolom); data setelahnya
        $headerRows = 1;
        if (isset($rows[1][3]) && trim((string) $rows[1][3]) === 'kode_angkatan') {
            $headerRows = 2;
        }

        if (count($rows) <= $headerRows) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak berisi baris data. Tambahkan data di bawah baris header template.',
            ], 422);
        }

        $dataRows = array_slice($rows, $headerRows);

        $errors = [];
        $successCount = 0;
        $skipCount = 0;

        $user = $request->user();
        $importAllowedProdiIds = $this->allowedProdiIdsForUser($user);

        DB::beginTransaction();
        try {
            foreach ($dataRows as $rowIndex => $row) {
                $rowNumber = $rowIndex + $headerRows + 1;

                if (empty(array_filter($row, fn ($c) => $c !== null && $c !== ''))) {
                    continue;
                }

                $namaKategori = isset($row[0]) ? trim((string) $row[0]) : '';
                $namaKomponen = isset($row[1]) ? trim((string) $row[1]) : '';
                $kodeProdi = isset($row[2]) ? trim((string) $row[2]) : '';
                $kodeAngkatan = isset($row[3]) ? trim((string) $row[3]) : '';
                $kodePeriode = isset($row[4]) ? trim((string) $row[4]) : '';
                $tahapRaw = $row[5] ?? null;
                $nominalRaw = $row[6] ?? null;

                // Lewati baris contoh bila masih berisi teks petunjuk
                if (str_starts_with($namaKategori, 'Contoh:')) {
                    continue;
                }

                if ($kodeAngkatan === '' || $kodePeriode === '') {
                    $errors[] = "Baris {$rowNumber}: kode_angkatan dan kode_periode wajib diisi.";
                    $skipCount++;

                    continue;
                }

                if ($nominalRaw === null || $nominalRaw === '') {
                    $errors[] = "Baris {$rowNumber}: nominal wajib diisi.";
                    $skipCount++;

                    continue;
                }

                $idKategori = $this->resolveKategoriBiayaIdByNama($namaKategori);
                if ($namaKategori !== '' && $idKategori === null) {
                    $errors[] = "Baris {$rowNumber}: kategori biaya \"{$namaKategori}\" tidak ditemukan.";
                    $skipCount++;

                    continue;
                }

                $idKomponen = $this->resolveKomponenBiayaIdByNama($namaKomponen);
                if ($namaKomponen !== '' && $idKomponen === null) {
                    $errors[] = "Baris {$rowNumber}: komponen biaya \"{$namaKomponen}\" tidak ditemukan.";
                    $skipCount++;

                    continue;
                }

                $idProdi = $this->resolveProdiIdByKode($kodeProdi);
                if ($kodeProdi !== '' && $idProdi === null) {
                    $errors[] = "Baris {$rowNumber}: prodi dengan kode \"{$kodeProdi}\" tidak ditemukan.";
                    $skipCount++;

                    continue;
                }

                if ($importAllowedProdiIds !== null) {
                    if ($kodeProdi === '') {
                        $errors[] = "Baris {$rowNumber}: kode prodi wajib diisi (akun Anda terbatas per program studi).";
                        $skipCount++;

                        continue;
                    }
                    if ($idProdi === null || ! in_array((int) $idProdi, $importAllowedProdiIds, true)) {
                        $errors[] = "Baris {$rowNumber}: prodi \"{$kodeProdi}\" di luar lingkup akses Anda.";
                        $skipCount++;

                        continue;
                    }
                }

                $idAngkatan = $this->resolveSemesterIdByKode($kodeAngkatan);
                if ($idAngkatan === null) {
                    $errors[] = "Baris {$rowNumber}: semester angkatan (kode \"{$kodeAngkatan}\") tidak ditemukan.";
                    $skipCount++;

                    continue;
                }

                $idPeriode = $this->resolveSemesterIdByKode($kodePeriode);
                if ($idPeriode === null) {
                    $errors[] = "Baris {$rowNumber}: semester periode (kode \"{$kodePeriode}\") tidak ditemukan.";
                    $skipCount++;

                    continue;
                }

                $tahap = 1;
                if ($tahapRaw !== null && $tahapRaw !== '') {
                    $tahap = (int) $tahapRaw;
                    if ($tahap < 1) {
                        $errors[] = "Baris {$rowNumber}: tahap harus bilangan bulat >= 1.";
                        $skipCount++;

                        continue;
                    }
                }

                $nominal = is_numeric($nominalRaw) ? (float) $nominalRaw : null;
                if ($nominal === null || $nominal < 0) {
                    $errors[] = "Baris {$rowNumber}: nominal harus angka >= 0.";
                    $skipCount++;

                    continue;
                }

                $exists = StrukturBiaya::where('id_kategori_biaya', $idKategori)
                    ->where('id_prodi', $idProdi)
                    ->where('id_angkatan', $idAngkatan)
                    ->where('id_periode', $idPeriode)
                    ->where('id_komponen_biaya', $idKomponen)
                    ->where('tahap', $tahap)
                    ->exists();

                if ($exists) {
                    $errors[] = "Baris {$rowNumber}: kombinasi kategori, prodi, angkatan, periode, komponen, dan tahap sudah ada (dilewati).";
                    $skipCount++;

                    continue;
                }

                StrukturBiaya::create([
                    'id_kategori_biaya' => $idKategori,
                    'id_prodi' => $idProdi,
                    'id_angkatan' => $idAngkatan,
                    'id_periode' => $idPeriode,
                    'id_komponen_biaya' => $idKomponen,
                    'tahap' => $tahap,
                    'nominal' => $nominal,
                ]);
                $successCount++;
            }

            DB::commit();

            $message = "Import selesai. Berhasil: {$successCount}, Dilewati/gagal: {$skipCount}";
            if (! empty($errors)) {
                $message .= '. Lihat daftar errors untuk detail.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'success_count' => $successCount,
                'skip_count' => $skipCount,
                'errors' => $errors,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengimpor: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return array<int>|null null = tanpa batasan scope
     */
    private function allowedProdiIdsForUser(?User $user): ?array
    {
        if (! $user || ! $user->hasScopeRestriction()) {
            return null;
        }

        return $user->getAllowedProdiIds();
    }

    private function assertStrukturBiayaInUserScope(Request $request, StrukturBiaya $row): void
    {
        $allowed = $this->allowedProdiIdsForUser($request->user());
        if ($allowed === null) {
            return;
        }
        if ($row->id_prodi === null || ! in_array((int) $row->id_prodi, $allowed, true)) {
            abort(403, 'Anda tidak memiliki akses ke struktur biaya ini.');
        }
    }

    private function resolveKategoriBiayaIdByNama(string $nama): ?int
    {
        if ($nama === '') {
            return null;
        }

        $norm = mb_strtolower(trim($nama));

        return KategoriBiaya::query()
            ->whereRaw('LOWER(TRIM(nama)) = ?', [$norm])
            ->value('id');
    }

    private function resolveKomponenBiayaIdByNama(string $nama): ?int
    {
        if ($nama === '') {
            return null;
        }

        $norm = mb_strtolower(trim($nama));

        return KomponenBiaya::query()
            ->whereRaw('LOWER(TRIM(nama)) = ?', [$norm])
            ->value('id');
    }

    private function resolveProdiIdByKode(string $kode): ?int
    {
        if ($kode === '') {
            return null;
        }

        return Prodi::query()->where('kode', $kode)->value('id');
    }

    private function resolveSemesterIdByKode(string $kode): ?int
    {
        if ($kode === '') {
            return null;
        }

        return Semester::query()->where('kode', $kode)->value('id');
    }
}
