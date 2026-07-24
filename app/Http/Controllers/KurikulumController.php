<?php

namespace App\Http\Controllers;

use App\Models\BobotPenilaian;
use App\Models\Kurikulum;
use App\Models\KurikulumMatkul;
use App\Models\Matkul;
use App\Models\Prodi;
use App\Models\Semester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KurikulumController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');
        $prodiId = $request->get('id_prodi');
        $status = $request->get('status');

        $query = Kurikulum::with(['prodi.jenjang', 'matkuls', 'tahunBerlaku']);

        // Hak akses scope: hanya tampilkan kurikulum dari prodi yang sesuai scope user (fakultas dan/atau prodi)
        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereIn('id_prodi', $allowedProdiIds);
                if ($prodiId && ! in_array((int) $prodiId, $allowedProdiIds, true)) {
                    $prodiId = null;
                }
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('kode', 'like', "%{$search}%")
                    ->orWhere('nama', 'like', "%{$search}%");
            });
        }

        if ($prodiId) {
            $query->where('id_prodi', (int) $prodiId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $data = $query->orderBy('kode')->paginate($perPage);

        $data->getCollection()->transform(function (Kurikulum $kurikulum): Kurikulum {
            $kurikulum->total_sks_kurikulum = (int) $kurikulum->matkuls->sum(function (Matkul $matkul): int {
                $pivotSks = $matkul->pivot->sks;

                return (int) ($pivotSks ?? $matkul->sks ?? 0);
            });

            return $kurikulum;
        });

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_prodi' => ['required', 'integer', 'exists:prodi,id'],
            'kode' => ['required', 'string', 'max:50', 'unique:kurikulum,kode'],
        ]);

        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $validated['id_prodi'], $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke program studi ini.');
            }
        }

        $validated = array_merge($validated, $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'sks_wajib_minimal' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'id_tahun_berlaku' => ['required', 'integer', 'exists:semester,id'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'matkuls' => ['nullable', 'array'],
            'matkuls.*.id_matkul' => ['required', 'integer', 'exists:matkul,id'],
            'matkuls.*.semester_rekomendasi' => ['nullable', 'integer', 'min:1', 'max:14'],
            'matkuls.*.is_wajib' => ['nullable', 'boolean'],
        ]));

        DB::beginTransaction();
        try {
            $kurikulum = Kurikulum::create([
                'id_prodi' => $validated['id_prodi'],
                'kode' => $validated['kode'],
                'nama' => $validated['nama'],
                'sks_wajib_minimal' => $validated['sks_wajib_minimal'] ?? null,
                'deskripsi' => $validated['deskripsi'] ?? null,
                'id_tahun_berlaku' => $validated['id_tahun_berlaku'],
                'status' => $validated['status'] ?? 'active',
            ]);

            if (isset($validated['matkuls']) && is_array($validated['matkuls'])) {
                $syncData = [];
                foreach ($validated['matkuls'] as $matkulData) {
                    // Ambil data matkul dari database
                    $matkul = Matkul::find($matkulData['id_matkul']);
                    if ($matkul) {
                        $syncData[$matkulData['id_matkul']] = [
                            'kode_matkul' => $matkul->kode,
                            'nama_matkul' => $matkul->nama,
                            'nama_matkul_en' => $matkul->nama_en,
                            'sks' => $matkul->sks,
                            'semester_rekomendasi' => $matkulData['semester_rekomendasi'] ?? null,
                            'is_wajib' => $matkulData['is_wajib'] ?? true,
                        ];
                    }
                }
                $kurikulum->matkuls()->sync($syncData);
            }

            DB::commit();

            return response()->json($kurikulum->load(['prodi.jenjang', 'matkuls', 'tahunBerlaku']), 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Terjadi kesalahan saat menyimpan kurikulum: '.$e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, Kurikulum $kurikulum): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $kurikulum->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke kurikulum ini.');
            }
        }

        $kurikulum->load(['prodi.jenjang', 'matkuls', 'tahunBerlaku']);
        $pivotIds = $kurikulum->matkuls->pluck('pivot.id')->filter()->values()->all();
        $matkulBobotTotals = [];
        if (! empty($pivotIds)) {
            $totals = BobotPenilaian::query()
                ->whereIn('id_kurikulum_matkul', $pivotIds)
                ->selectRaw('id_kurikulum_matkul, SUM(bobot) as total')
                ->groupBy('id_kurikulum_matkul')
                ->pluck('total', 'id_kurikulum_matkul');
            $matkulBobotTotals = $totals->all();
        }

        return response()->json(array_merge($kurikulum->toArray(), [
            'matkul_bobot_totals' => $matkulBobotTotals,
        ]));
    }

    /**
     * Terapkan bobot penilaian secara massal ke semua mata kuliah kurikulum
     * yang belum memiliki bobot penilaian.
     * Body: { items: [ { id_jenis_penilaian: number, bobot: number }, ... ] }
     * Total bobot tidak boleh melebihi 100%.
     */
    public function applyBobotPenilaianMassal(Request $request, Kurikulum $kurikulum): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $kurikulum->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke kurikulum ini.');
            }
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id_jenis_penilaian' => ['required', 'integer', 'exists:jenis_penilaian,id'],
            'items.*.bobot' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $items = $validated['items'];
        $total = array_sum(array_column($items, 'bobot'));
        if ($total > 100) {
            return response()->json([
                'message' => 'Total bobot penilaian tidak boleh melebihi 100%.',
                'errors' => [
                    'items' => ['Total saat ini: '.round($total, 2).'%. Maksimal 100%.'],
                ],
            ], 422);
        }

        $matkulsWithoutBobot = KurikulumMatkul::where('id_kurikulum', $kurikulum->id)
            ->whereDoesntHave('bobotPenilaian')
            ->get();

        if ($matkulsWithoutBobot->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada mata kuliah yang belum memiliki bobot nilai. Semua mata kuliah sudah diisi.',
                'updated_count' => 0,
            ]);
        }

        DB::beginTransaction();
        try {
            foreach ($matkulsWithoutBobot as $km) {
                foreach ($items as $row) {
                    $bobot = (float) $row['bobot'];
                    if ($bobot > 0) {
                        $km->bobotPenilaian()->create([
                            'id_jenis_penilaian' => (int) $row['id_jenis_penilaian'],
                            'bobot' => $bobot,
                        ]);
                    }
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['message' => 'Gagal menerapkan bobot penilaian massal.'], 500);
        }

        return response()->json([
            'message' => 'Bobot penilaian berhasil diterapkan ke '.$matkulsWithoutBobot->count().' mata kuliah.',
            'updated_count' => $matkulsWithoutBobot->count(),
        ]);
    }

    public function update(Request $request, Kurikulum $kurikulum): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $kurikulum->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke kurikulum ini.');
            }
        }

        $validated = $request->validate([
            'id_prodi' => ['sometimes', 'required', 'integer', 'exists:prodi,id'],
            'kode' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('kurikulum', 'kode')->ignore($kurikulum->id),
            ],
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'sks_wajib_minimal' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'id_tahun_berlaku' => ['sometimes', 'required', 'integer', 'exists:semester,id'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'matkuls' => ['nullable', 'array'],
            'matkuls.*.id_matkul' => ['required', 'integer', 'exists:matkul,id'],
            'matkuls.*.semester_rekomendasi' => ['nullable', 'integer', 'min:1', 'max:14'],
            'matkuls.*.is_wajib' => ['nullable', 'boolean'],
        ]);

        if ($user && $user->hasScopeRestriction() && array_key_exists('id_prodi', $validated)) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $validated['id_prodi'], $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke program studi ini.');
            }
        }

        DB::beginTransaction();
        try {
            $kurikulum->update([
                'id_prodi' => $validated['id_prodi'] ?? $kurikulum->id_prodi,
                'kode' => $validated['kode'] ?? $kurikulum->kode,
                'nama' => $validated['nama'] ?? $kurikulum->nama,
                'sks_wajib_minimal' => $validated['sks_wajib_minimal'] ?? $kurikulum->sks_wajib_minimal,
                'deskripsi' => $validated['deskripsi'] ?? $kurikulum->deskripsi,
                'id_tahun_berlaku' => $validated['id_tahun_berlaku'] ?? $kurikulum->id_tahun_berlaku,
                'status' => $validated['status'] ?? $kurikulum->status,
            ]);

            if (isset($validated['matkuls']) && is_array($validated['matkuls'])) {
                $syncData = [];
                foreach ($validated['matkuls'] as $matkulData) {
                    // Ambil data matkul dari database
                    $matkul = Matkul::find($matkulData['id_matkul']);
                    if ($matkul) {
                        $syncData[$matkulData['id_matkul']] = [
                            'kode_matkul' => $matkul->kode,
                            'nama_matkul' => $matkul->nama,
                            'nama_matkul_en' => $matkul->nama_en,
                            'sks' => $matkul->sks,
                            'semester_rekomendasi' => $matkulData['semester_rekomendasi'] ?? null,
                            'is_wajib' => $matkulData['is_wajib'] ?? true,
                        ];
                    }
                }
                $kurikulum->matkuls()->sync($syncData);
            }

            DB::commit();

            return response()->json($kurikulum->load(['prodi.jenjang', 'matkuls', 'tahunBerlaku']));
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Terjadi kesalahan saat memperbarui kurikulum: '.$e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, Kurikulum $kurikulum): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $kurikulum->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke kurikulum ini.');
            }
        }

        $kurikulum->delete();

        return response()->json(['message' => 'Kurikulum dihapus']);
    }

    /**
     * Normalisasi isi sel Excel untuk kode semester (angka 20241.0 → "20241").
     */
    private static function normalizeKodeSemesterImport(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_numeric($value)) {
            $f = (float) $value;
            if ($f == floor($f)) {
                return (string) (int) $f;
            }
        }

        return trim((string) $value);
    }

    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data');

        $headers = [
            'Kode Prodi*',
            'Kode Kurikulum*',
            'Nama Kurikulum*',
            'Deskripsi',
            'SKS Wajib Minimal',
            'Kode Semester (tahun berlaku)*',
            'Status',
        ];
        $sheet->fromArray([$headers], null, 'A1');

        $sheet->getColumnDimension('A')->setWidth(16);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(38);
        $sheet->getColumnDimension('D')->setWidth(42);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(30);
        $sheet->getColumnDimension('G')->setWidth(18);

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ];
        $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(36);

        $exampleRow = [
            'TI',
            'KUR-2024',
            'Kurikulum Teknik Informatika 2024',
            'Deskripsi singkat kurikulum',
            '144',
            '20241',
            'active',
        ];
        $sheet->fromArray([$exampleRow], null, 'A2');
        $sheet->freezePane('A2');

        $petunjuk = $spreadsheet->createSheet();
        $petunjuk->setTitle('Petunjuk');
        $petunjuk->getColumnDimension('A')->setWidth(100);
        $lines = [
            ['PANDUAN IMPORT KURIKULUM'],
            [''],
            ['Kolom F (Kode Semester — tahun berlaku) mengacu ke relasi id_tahun_berlaku → tabel semester.'],
            ['Isi harus SAMA PERSIS dengan nilai kolom "kode" pada master Semester (bukan tahun saja, mis. 20241 bukan 2024).'],
            [''],
            ['A — Kode Prodi*     : kode program studi yang sudah ada di sistem.'],
            ['B — Kode Kurikulum* : unik secara global.'],
            ['C — Nama Kurikulum*'],
            ['D — Deskripsi       : opsional.'],
            ['E — SKS wajib min.  : opsional, angka 0–1000.'],
            ['F — Kode semester*  : wajib; contoh 20241, 20242 (sesuai data semester Anda).'],
            ['G — Status          : active atau inactive (default active).'],
            [''],
            ['Baris 1 sheet "Data" = header. Mulai isi data dari baris 2.'],
        ];
        $row = 1;
        foreach ($lines as $line) {
            $petunjuk->setCellValue('A'.$row, $line[0]);
            $row++;
        }
        $petunjuk->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'template_import_kurikulum_'.date('YmdHis').'.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment;filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $user = $request->user();
        $allowedProdiIds = ($user && $user->hasScopeRestriction()) ? $user->getAllowedProdiIds() : null;

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getRealPath());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        if (count($rows) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'File Excel kosong atau tidak valid.',
            ], 400);
        }

        array_shift($rows);

        $errors = [];
        $successCount = 0;
        $skipCount = 0;
        $processedRows = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $rowIndex => $row) {
                $rowNumber = $rowIndex + 2;

                if (empty(array_filter($row))) {
                    continue;
                }

                $data = [
                    'prodi_kode' => $row[0] ?? null,
                    'kode' => $row[1] ?? null,
                    'nama' => $row[2] ?? null,
                    'deskripsi' => $row[3] ?? null,
                    'sks_wajib_minimal' => $row[4] ?? null,
                    'kode_semester_tahun' => self::normalizeKodeSemesterImport($row[5] ?? null),
                    'status' => $row[6] ?? 'active',
                ];

                if (empty(trim((string) $data['kode']))) {
                    $errors[] = "Baris {$rowNumber}: Kode wajib diisi.";

                    continue;
                }

                if (empty(trim((string) $data['nama']))) {
                    $errors[] = "Baris {$rowNumber}: Nama wajib diisi.";

                    continue;
                }

                if (Kurikulum::withTrashed()->where('kode', trim($data['kode']))->exists()) {
                    $errors[] = "Baris {$rowNumber}: Kode '{$data['kode']}' sudah ada di sistem.";
                    $skipCount++;

                    continue;
                }

                foreach ($processedRows as $processed) {
                    if (trim((string) $data['kode']) === $processed['kode']) {
                        $errors[] = "Baris {$rowNumber}: Kode '{$data['kode']}' duplikat dalam file.";
                        $skipCount++;

                        continue 2;
                    }
                }

                $id_prodi = null;
                if (! empty($data['prodi_kode'])) {
                    $prodiKode = trim((string) $data['prodi_kode']);
                    $prodi = Prodi::where('kode', $prodiKode)->first();
                    if (! $prodi) {
                        $errors[] = "Baris {$rowNumber}: Kode Prodi '{$prodiKode}' tidak ditemukan di sistem.";
                        $skipCount++;

                        continue;
                    }
                    $id_prodi = $prodi->id;
                }

                if ($allowedProdiIds !== null) {
                    if ($id_prodi === null) {
                        $errors[] = "Baris {$rowNumber}: Program studi wajib diisi dan harus dalam scope Anda.";
                        $skipCount++;

                        continue;
                    }
                    if (! in_array((int) $id_prodi, $allowedProdiIds, true)) {
                        $errors[] = "Baris {$rowNumber}: Anda tidak memiliki akses ke program studi ini.";
                        $skipCount++;

                        continue;
                    }
                }

                $sksWajibMinimal = null;
                if ($data['sks_wajib_minimal'] !== null && $data['sks_wajib_minimal'] !== '') {
                    $sksWajibMinimal = is_numeric($data['sks_wajib_minimal']) ? (int) $data['sks_wajib_minimal'] : null;
                    if ($sksWajibMinimal !== null && ($sksWajibMinimal < 0 || $sksWajibMinimal > 1000)) {
                        $errors[] = "Baris {$rowNumber}: SKS Wajib Minimal harus antara 0-1000.";
                        $skipCount++;

                        continue;
                    }
                }

                if ($data['kode_semester_tahun'] === '') {
                    $errors[] = "Baris {$rowNumber}: Kolom F (kode semester tahun berlaku) wajib diisi — harus sama dengan kode di master Semester, contoh 20241.";
                    $skipCount++;

                    continue;
                }
                $semesterTh = Semester::where('kode', $data['kode_semester_tahun'])->first();
                if (! $semesterTh) {
                    $hint = '';
                    if (preg_match('/^\d{4}$/', $data['kode_semester_tahun'])) {
                        $hint = ' Jangan hanya tahun (mis. 2024); gunakan kode semester lengkap seperti 20241.';
                    }
                    $errors[] = "Baris {$rowNumber}: Semester dengan kode '{$data['kode_semester_tahun']}' tidak ditemukan di tabel semester.{$hint}";
                    $skipCount++;

                    continue;
                }

                $status = 'active';
                if (! empty($data['status'])) {
                    $status = strtolower(trim((string) $data['status']));
                    if (! in_array($status, ['active', 'inactive'])) {
                        $errors[] = "Baris {$rowNumber}: Status harus 'active' atau 'inactive'.";
                        $skipCount++;

                        continue;
                    }
                }

                if ($id_prodi === null) {
                    $errors[] = "Baris {$rowNumber}: Kode Prodi wajib diisi.";
                    $skipCount++;

                    continue;
                }

                Kurikulum::create([
                    'id_prodi' => $id_prodi,
                    'kode' => trim((string) $data['kode']),
                    'nama' => trim((string) $data['nama']),
                    'deskripsi' => ! empty($data['deskripsi']) ? trim((string) $data['deskripsi']) : null,
                    'sks_wajib_minimal' => $sksWajibMinimal,
                    'id_tahun_berlaku' => $semesterTh->id,
                    'status' => $status,
                ]);
                $successCount++;
                $processedRows[] = ['kode' => trim((string) $data['kode'])];
            }

            DB::commit();

            $message = "Import selesai. Berhasil: {$successCount}, Dilewati: {$skipCount}";
            if (count($errors) > 0) {
                $message .= '. Terdapat '.count($errors).' error.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'success_count' => $successCount,
                    'skip_count' => $skipCount,
                    'error_count' => count($errors),
                    'errors' => $errors,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengimport data: '.$e->getMessage(),
            ], 500);
        }
    }
}
