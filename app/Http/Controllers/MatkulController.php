<?php

namespace App\Http\Controllers;

use App\Models\Matkul;
use App\Models\JenisMatkul;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\KurikulumMatkul;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MatkulController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');
        $prodiId = $request->get('id_prodi');
        $semester = $request->get('semester');
        $status = $request->get('status');
        $idJenisMatkul = $request->get('id_jenis_matkul');

        $query = Matkul::with(['prodi.jenjang', 'jenisMatkul'])
            ->withCount('matkulPrasyaratLinks');

        // Hak akses scope: hanya tampilkan mata kuliah dari prodi yang sesuai scope user (fakultas dan/atau prodi)
        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereIn('id_prodi', $allowedProdiIds);
                if ($prodiId && !in_array((int) $prodiId, $allowedProdiIds, true)) {
                    $prodiId = null;
                }
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('kode', 'like', "%{$search}%")
                  ->orWhere('nama', 'like', "%{$search}%")
                  ->orWhere('nama_en', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('id_prodi_null')) {
            $query->whereNull('id_prodi');
        } elseif ($prodiId) {
            $query->where('id_prodi', (int) $prodiId);
        }

        if ($semester) {
            $query->where('semester', $semester);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($idJenisMatkul) {
            $query->where('id_jenis_matkul', (int) $idJenisMatkul);
        }

        $query->orderBy('kode');

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        $data = $query->paginate($perPage);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode' => [
                'required',
                'string',
                'max:50',
                Rule::unique('matkul', 'kode')->where('id_prodi', $request->input('id_prodi')),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'nama_en' => ['nullable', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'sks' => ['nullable', 'integer', 'min:1', 'max:10'],
            'semester' => ['nullable', 'integer', 'min:1', 'max:14'],
            'id_prodi' => ['nullable', 'integer', 'exists:prodi,id'],
            'id_jenis_matkul' => ['nullable', 'integer', 'exists:jenis_matkul,id'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $matkul = Matkul::create($validated);

        return response()->json($matkul->load(['prodi.jenjang', 'jenisMatkul']), 201);
    }

    public function show(Request $request, Matkul $matkul): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && !in_array((int) $matkul->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke mata kuliah ini.');
            }
        }

        return response()->json($matkul->load(['prodi.jenjang', 'jenisMatkul']));
    }

    public function update(Request $request, Matkul $matkul): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && !in_array((int) $matkul->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke mata kuliah ini.');
            }
        }

        $validated = $request->validate([
            'kode' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('matkul', 'kode')
                    ->ignore($matkul->id)
                    ->where('id_prodi', $request->input('id_prodi', $matkul->id_prodi)),
            ],
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'nama_en' => ['nullable', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'sks' => ['nullable', 'integer', 'min:1', 'max:10'],
            'semester' => ['nullable', 'integer', 'min:1', 'max:14'],
            'id_prodi' => ['nullable', 'integer', 'exists:prodi,id'],
            'id_jenis_matkul' => ['nullable', 'integer', 'exists:jenis_matkul,id'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ]);

        if ($user && $user->hasScopeRestriction() && array_key_exists('id_prodi', $validated)) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && !in_array((int) $validated['id_prodi'], $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke program studi ini.');
            }
        }

        DB::beginTransaction();
        try {
            // Update mata kuliah
            $matkul->update($validated);

            // Ambil semester aktif
            $activeSemester = Semester::where('is_active', true)->first();

            if ($activeSemester) {
                // Cari semua kurikulum_matkul yang digunakan di kelas pada semester aktif
                // dan memiliki id_matkul yang sama dengan mata kuliah yang diupdate
                $kurikulumMatkulIds = DB::table('kelas')
                    ->join('kurikulum_matkul', 'kelas.id_kurikulum_matkul', '=', 'kurikulum_matkul.id')
                    ->where('kelas.id_semester', $activeSemester->id)
                    ->where('kurikulum_matkul.id_matkul', $matkul->id)
                    ->whereNull('kelas.deleted_at')
                    ->whereNull('kurikulum_matkul.deleted_at')
                    ->pluck('kurikulum_matkul.id')
                    ->unique()
                    ->toArray();

                // Update kurikulum_matkul yang terkait dengan semester aktif
                if (!empty($kurikulumMatkulIds)) {
                    KurikulumMatkul::whereIn('id', $kurikulumMatkulIds)
                        ->update([
                            'kode_matkul' => $matkul->kode,
                            'nama_matkul' => $matkul->nama,
                            'nama_matkul_en' => $matkul->nama_en,
                            'sks' => $matkul->sks,
                        ]);
                }
            }

            DB::commit();

            return response()->json($matkul->load(['prodi.jenjang', 'jenisMatkul']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan saat memperbarui mata kuliah: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, Matkul $matkul): JsonResponse
    {
        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && !in_array((int) $matkul->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke mata kuliah ini.');
            }
        }

        $matkul->delete();

        return response()->json(['message' => 'Mata kuliah dihapus']);
    }

    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set header
        $headers = [
            'Kode*',
            'Nama*',
            'Nama (English)',
            'Deskripsi',
            'SKS',
            'Semester',
            'Kode Prodi',
            'Kode Jns MK',
            'Status (active/inactive)',
        ];

        $sheet->fromArray([$headers], null, 'A1');

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(30);
        $sheet->getColumnDimension('D')->setWidth(40);
        $sheet->getColumnDimension('E')->setWidth(10);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(15);
        $sheet->getColumnDimension('H')->setWidth(25);
        $sheet->getColumnDimension('I')->setWidth(20);

        // Style header
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:I1')->applyFromArray($headerStyle);

        // Add example row
        $exampleRow = [
            'MK001',
            'Algoritma dan Struktur Data',
            'Algorithm and Data Structure',
            'Mata kuliah tentang algoritma dan struktur data',
            '3',
            '3',
            'TI',
            'Mata Kuliah Wajib',
            'active',
        ];
        $sheet->fromArray([$exampleRow], null, 'A2');

        $filename = 'template_import_mata_kuliah_' . date('YmdHis') . '.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment;filename="' . $filename . '"',
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

        // Remove header row
        array_shift($rows);

        $errors = [];
        $successCount = 0;
        $skipCount = 0;
        $processedRows = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $rowIndex => $row) {
                $rowNumber = $rowIndex + 2; // +2 karena header di row 1 dan array 0-indexed

                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                $data = [
                    'kode' => $row[0] ?? null,
                    'nama' => $row[1] ?? null,
                    'nama_en' => $row[2] ?? null,
                    'deskripsi' => $row[3] ?? null,
                    'sks' => $row[4] ?? null,
                    'semester' => $row[5] ?? null,
                    'prodi_kode' => $row[6] ?? null,
                    'jenis_matkul_kode' => $row[7] ?? null,
                    'status' => $row[8] ?? 'active',
                ];

                // Validate required fields
                if (empty($data['kode'])) {
                    $errors[] = "Baris {$rowNumber}: Kode wajib diisi.";
                    continue;
                }

                if (empty($data['nama'])) {
                    $errors[] = "Baris {$rowNumber}: Nama wajib diisi.";
                    continue;
                }

                // Find Prodi by kode and get ID (needed for unique check and scope)
                $id_prodi = null;
                if (!empty($data['prodi_kode'])) {
                    $prodi_kode = trim($data['prodi_kode']);
                    $prodi = Prodi::where('kode', $prodi_kode)->first();
                    if (!$prodi) {
                        $errors[] = "Baris {$rowNumber}: Kode Prodi '{$prodi_kode}' tidak ditemukan di sistem.";
                        $skipCount++;
                        continue;
                    }
                    $id_prodi = $prodi->id;
                }

                // Validate unique kode per prodi
                if (Matkul::where('kode', $data['kode'])->where('id_prodi', $id_prodi)->exists()) {
                    $errors[] = "Baris {$rowNumber}: Kode '{$data['kode']}' sudah ada untuk prodi ini di sistem.";
                    $skipCount++;
                    continue;
                }

                // Check for duplicates in current import batch (kode + id_prodi)
                foreach ($processedRows as $processed) {
                    if ($data['kode'] === $processed['kode'] && $id_prodi === $processed['id_prodi']) {
                        $errors[] = "Baris {$rowNumber}: Kode '{$data['kode']}' duplikat dalam file untuk prodi yang sama.";
                        $skipCount++;
                        continue 2;
                    }
                }

                if ($allowedProdiIds !== null) {
                    if ($id_prodi === null) {
                        $errors[] = "Baris {$rowNumber}: Program studi wajib diisi dan harus dalam scope Anda.";
                        $skipCount++;
                        continue;
                    }
                    if (!in_array((int) $id_prodi, $allowedProdiIds, true)) {
                        $errors[] = "Baris {$rowNumber}: Anda tidak memiliki akses ke program studi ini.";
                        $skipCount++;
                        continue;
                    }
                }

                // Find jenis mata kuliah by name
                $id_jenis_matkul = null;
                if (!empty($data['jenis_matkul_kode'])) {
                    $jenisMatkul = JenisMatkul::where('kode', trim($data['jenis_matkul_kode']))->first();
                    if ($jenisMatkul) {
                        $id_jenis_matkul = $jenisMatkul->id;
                    }
                }

                // Validate SKS
                $sks = null;
                if (!empty($data['sks'])) {
                    $sks = is_numeric($data['sks']) ? (int) $data['sks'] : null;
                    if ($sks !== null && $sks < 1) {
                        $errors[] = "Baris {$rowNumber}: SKS harus lebih dari 0.";
                        $skipCount++;
                        continue;
                    }
                }

                // Validate semester
                $semester = null;
                if (!empty($data['semester'])) {
                    $semester = is_numeric($data['semester']) ? (int) $data['semester'] : null;
                    if ($semester !== null && ($semester < 1 || $semester > 14)) {
                        $errors[] = "Baris {$rowNumber}: Semester harus antara 1-14.";
                        $skipCount++;
                        continue;
                    }
                }

                // Validate status
                $status = 'active';
                if (!empty($data['status'])) {
                    $status = strtolower(trim($data['status']));
                    if (!in_array($status, ['active', 'inactive'])) {
                        $errors[] = "Baris {$rowNumber}: Status harus 'active' atau 'inactive'.";
                        $skipCount++;
                        continue;
                    }
                }

                // Prepare data for insert
                $matkulData = [
                    'kode' => trim($data['kode']),
                    'nama' => trim($data['nama']),
                    'nama_en' => !empty($data['nama_en']) ? trim($data['nama_en']) : null,
                    'deskripsi' => !empty($data['deskripsi']) ? trim($data['deskripsi']) : null,
                    'sks' => $sks,
                    'semester' => $semester,
                    'id_prodi' => $id_prodi,
                    'id_jenis_matkul' => $id_jenis_matkul,
                    'status' => $status,
                ];

                Matkul::create($matkulData);
                $successCount++;
                $processedRows[] = ['kode' => $data['kode'], 'id_prodi' => $id_prodi];
            }

            DB::commit();

            $message = "Import selesai. Berhasil: {$successCount}, Dilewati: {$skipCount}";
            if (count($errors) > 0) {
                $message .= ". Terdapat " . count($errors) . " error.";
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
                'message' => 'Terjadi kesalahan saat mengimport data: ' . $e->getMessage(),
            ], 500);
        }
    }
}

