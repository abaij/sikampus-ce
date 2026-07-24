<?php

namespace App\Http\Controllers;

use App\Models\Dosen;
use App\Models\DosenWali;
use App\Models\Mahasiswa;
use App\Models\Semester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DosenWaliController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $idDosen = $request->get('id_dosen');
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');
        
        $query = DosenWali::with(['mahasiswa.prodi', 'dosen']);

        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereHas('mahasiswa', function ($q) use ($allowedProdiIds) {
                    $q->whereIn('id_prodi', $allowedProdiIds);
                });
            }
        }

        if ($idDosen) {
            $query->where('id_dosen', $idDosen);
        }

        if ($search) {
            $query->whereHas('mahasiswa', function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('nim', 'like', "%{$search}%");
            });
        }

        $data = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_dosen' => ['required', 'integer', 'exists:dosen,id'],
            'id_mahasiswa' => ['required', 'integer', 'exists:mahasiswa,id'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $mahasiswaTarget = Mahasiswa::find($validated['id_mahasiswa']);
                if (! $mahasiswaTarget || ! in_array((int) $mahasiswaTarget->id_prodi, $allowedProdiIds, true)) {
                    abort(403, 'Anda tidak memiliki akses ke mahasiswa ini.');
                }
            }
        }

        $dosen = Dosen::find($validated['id_dosen']);
        $kuota = (int) ($dosen->kuota_bimbingan_akademik ?? 0);
        if ($kuota > 0) {
            $jumlahAktif = DosenWali::where('id_dosen', $validated['id_dosen'])
                ->whereNull('deleted_at')
                ->where('status', 'active')
                ->count();
            $setelahTambah = $jumlahAktif + 1;
            if ($setelahTambah > $kuota) {
                return response()->json([
                    'message' => 'Kuota bimbingan akademik dosen ini sudah tercapai. Jumlah mahasiswa bimbingan aktif (' . $jumlahAktif . ') tidak boleh melebihi kuota (' . $kuota . ').',
                ], 422);
            }
        }

        // Check if combination already exists (termasuk yang soft-deleted agar tidak duplikat)
        $existing = DosenWali::withTrashed()
            ->where('id_dosen', $validated['id_dosen'])
            ->where('id_mahasiswa', $validated['id_mahasiswa'])
            ->first();

        if ($existing) {
            $existing->restore();
            return response()->json($existing);
        }

        try {
            $dosenWali = DosenWali::create([
                'id_dosen' => $validated['id_dosen'],
                'id_mahasiswa' => $validated['id_mahasiswa'],
                'status' => $validated['status'] ?? 'active',
            ]);
        } catch (\Throwable $e) {
            Log::error('DosenWaliController@store: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Gagal menambah mahasiswa bimbingan.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }

        // Return model saja; frontend refetch daftar setelah sukses (menghindari error serialisasi relasi)
        return response()->json($dosenWali, 201);
    }

    public function show(Request $request, DosenWali $dosenWali): JsonResponse
    {
        $dosenWali->loadMissing('mahasiswa');
        $this->assertDosenWaliInUserScope($request, $dosenWali);

        return response()->json($dosenWali->load(['mahasiswa.prodi', 'dosen']));
    }

    /**
     * Pastikan baris dosen_wali (lewat mahasiswa terkait) berada dalam scope prodi user.
     */
    private function assertDosenWaliInUserScope(Request $request, DosenWali $dosenWali): void
    {
        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && (! $dosenWali->mahasiswa || ! in_array((int) $dosenWali->mahasiswa->id_prodi, $allowedProdiIds, true))) {
                abort(403, 'Anda tidak memiliki akses ke data bimbingan ini.');
            }
        }
    }

    public function update(Request $request, DosenWali $dosenWali): JsonResponse
    {
        $dosenWali->loadMissing('mahasiswa');
        $this->assertDosenWaliInUserScope($request, $dosenWali);

        $validated = $request->validate([
            'id_mahasiswa' => ['sometimes', 'required', 'integer', 'exists:mahasiswa,id'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ]);

        // Check if new combination already exists (if id_mahasiswa changed)
        if (isset($validated['id_mahasiswa']) && $validated['id_mahasiswa'] != $dosenWali->id_mahasiswa) {
            $user = $request->user();
            if ($user && $user->hasScopeRestriction()) {
                $allowedProdiIds = $user->getAllowedProdiIds();
                if ($allowedProdiIds !== null) {
                    $mahasiswaBaru = Mahasiswa::find($validated['id_mahasiswa']);
                    if (! $mahasiswaBaru || ! in_array((int) $mahasiswaBaru->id_prodi, $allowedProdiIds, true)) {
                        abort(403, 'Anda tidak memiliki akses ke mahasiswa ini.');
                    }
                }
            }

            $existing = DosenWali::where('id_dosen', $dosenWali->id_dosen)
                ->where('id_mahasiswa', $validated['id_mahasiswa'])
                ->where('id', '!=', $dosenWali->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Mahasiswa sudah terdaftar sebagai bimbingan dosen ini.',
                ], 422);
            }
        }

        $dosenWali->update($validated);

        return response()->json($dosenWali->load(['mahasiswa.prodi', 'dosen']));
    }

    public function destroy(Request $request, DosenWali $dosenWali): JsonResponse
    {
        $dosenWali->loadMissing('mahasiswa');
        $this->assertDosenWaliInUserScope($request, $dosenWali);

        $dosenWali->delete();

        return response()->json(['message' => 'Data bimbingan dihapus']);
    }

    /**
     * Get list of supervised students for the logged-in lecturer
     */
    public function getMyBimbingan(Request $request): JsonResponse
    {
        $user = $request->user();
        $dosen = \App\Models\Dosen::where('id_user', $user->id)->first();
        
        if (!$dosen) {
            return response()->json([
                'message' => 'Data dosen tidak ditemukan'
            ], 404);
        }

        $perPage = (int) $request->get('per_page', 10);
        $page = (int) $request->get('page', 1);
        $search = $request->get('search');

        // Query mahasiswa bimbingan dosen
        $query = DosenWali::with([
            'mahasiswa.prodi',
            'mahasiswa.prodi.jenjang',
            'mahasiswa.semester_masuk',
            'mahasiswa.status_akademik',
        ])
        ->where('id_dosen', $dosen->id)
        ->where('status', 'active')
        ->whereNull('deleted_at');

        // Filter berdasarkan pencarian nama atau nim
        if ($search) {
            $query->whereHas('mahasiswa', function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('nim', 'like', "%{$search}%");
            });
        }

        // Hitung total sebelum pagination
        $total = $query->count();

        // Pagination
        $offset = ($page - 1) * $perPage;
        $mahasiswaBimbingan = $query->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        // Format data
        $data = $mahasiswaBimbingan->map(function ($dosenWali) {
            $mahasiswa = $dosenWali->mahasiswa;
            return [
                'id_mahasiswa' => $mahasiswa->id,
                'nim' => $mahasiswa->nim,
                'nama' => $mahasiswa->nama,
                'email' => $mahasiswa->email,
                'prodi' => $mahasiswa->prodi ? [
                    'id' => $mahasiswa->prodi->id,
                    'nama' => $mahasiswa->prodi->nama,
                    'jenjang' => $mahasiswa->prodi->jenjang ? [
                        'id' => $mahasiswa->prodi->jenjang->id,
                        'nama' => $mahasiswa->prodi->jenjang->nama,
                    ] : null,
                ] : null,
                'semester_masuk' => $mahasiswa->semester_masuk ? [
                    'id' => $mahasiswa->semester_masuk->id,
                    'kode' => $mahasiswa->semester_masuk->kode,
                    'nama' => $mahasiswa->semester_masuk->nama,
                ] : null,
                'status_akademik' => $mahasiswa->status_akademik ? [
                    'id' => $mahasiswa->status_akademik->id,
                    'nama' => $mahasiswa->status_akademik->nama,
                ] : null,
            ];
        });

        $lastPage = (int) ceil($total / $perPage);

        return response()->json([
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $total),
            ],
        ]);
    }

    /**
     * Biodata mahasiswa bimbingan untuk dosen wali yang sedang login (read-only).
     */
    public function getMyBimbinganMahasiswaBiodata(Request $request, int $idMahasiswa): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();

        if (! $dosen) {
            return response()->json([
                'message' => 'Data dosen tidak ditemukan',
            ], 404);
        }

        $dosenWali = DosenWali::where('id_dosen', $dosen->id)
            ->where('id_mahasiswa', $idMahasiswa)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();

        if (! $dosenWali) {
            return response()->json([
                'message' => 'Mahasiswa bukan bimbingan Anda atau tidak aktif.',
            ], 404);
        }

        $mahasiswa = Mahasiswa::with([
            'user',
            'prodi.jenjang',
            'status_akademik',
            'kelompok_kelas',
            'jalur_masuk',
            'jenis_daftar',
            'negara',
            'provinsi',
            'kota',
            'semester_masuk',
            'kategori_biaya_mahasiswa' => function ($q) {
                $q->where('status', 'active')->with('kategori_biaya');
            },
            'pendidikan_ayah',
            'pekerjaan_ayah',
            'penghasilan_ayah',
            'pendidikan_ibu',
            'pekerjaan_ibu',
            'penghasilan_ibu',
            'pendidikan_wali',
            'pekerjaan_wali',
            'penghasilan_wali',
        ])->find($idMahasiswa);

        if (! $mahasiswa) {
            return response()->json([
                'message' => 'Mahasiswa tidak ditemukan.',
            ], 404);
        }

        return response()->json($mahasiswa);
    }

    /**
     * Riwayat bimbingan akademik dosen login dengan satu mahasiswa bimbingan (filter semester opsional).
     */
    public function getMyBimbinganRiwayat(Request $request, int $idMahasiswa): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();

        if (! $dosen) {
            return response()->json([
                'message' => 'Data dosen tidak ditemukan',
            ], 404);
        }

        $dosenWali = DosenWali::with(['mahasiswa.prodi', 'mahasiswa.prodi.jenjang'])
            ->where('id_dosen', $dosen->id)
            ->where('id_mahasiswa', $idMahasiswa)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();

        if (! $dosenWali) {
            return response()->json([
                'message' => 'Mahasiswa bukan bimbingan Anda atau tidak aktif.',
            ], 404);
        }

        $perPage = (int) $request->get('per_page', 100);
        $query = $dosenWali->bimbingan()->with('semester');

        if ($request->filled('id_semester')) {
            $query->where('id_semester', (int) $request->get('id_semester'));
        }

        $bimbingan = $query
            ->orderByDesc('tanggal_bimbingan')
            ->orderByDesc('id')
            ->paginate($perPage);

        $m = $dosenWali->mahasiswa;

        return response()->json([
            'mahasiswa' => [
                'id' => $m->id,
                'nim' => $m->nim,
                'nama' => $m->nama,
                'prodi' => $m->prodi ? [
                    'id' => $m->prodi->id,
                    'nama' => $m->prodi->nama,
                    'jenjang' => $m->prodi->jenjang ? [
                        'id' => $m->prodi->jenjang->id,
                        'nama' => $m->prodi->jenjang->nama,
                    ] : null,
                ] : null,
            ],
            'bimbingan' => $bimbingan,
        ]);
    }

    /**
     * Riwayat bimbingan akademik untuk mahasiswa yang login (dosen_wali + dosen_wali_bimbingan).
     * Filter id_semester; default semester aktif.
     */
    public function getBimbinganAkademikMahasiswa(Request $request): JsonResponse
    {
        $user = $request->user();
        $mahasiswa = Mahasiswa::where('id_user', $user->id)->first();

        if (! $mahasiswa) {
            return response()->json([
                'message' => 'Data mahasiswa tidak ditemukan',
            ], 404);
        }

        $dosenWali = DosenWali::with(['dosen:id,nama,kode_dosen,nidn'])
            ->where('id_mahasiswa', $mahasiswa->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();

        $idSemester = $request->query('id_semester');
        if ($idSemester === null || $idSemester === '') {
            $active = Semester::where('is_active', true)->first();
            $idSemester = $active?->id;
        } else {
            $idSemester = (int) $idSemester;
        }

        $selectedSemester = $idSemester ? Semester::find($idSemester) : null;

        $bimbinganRows = collect();
        if ($dosenWali) {
            $q = $dosenWali->bimbingan()->with('semester');
            if ($idSemester) {
                $q->where('id_semester', $idSemester);
            }
            $bimbinganRows = $q->orderByDesc('tanggal_bimbingan')->orderByDesc('id')->get();
        }

        $data = $bimbinganRows->map(function ($b) {
            return [
                'id' => $b->id,
                'id_semester' => $b->id_semester,
                'semester' => $b->semester ? [
                    'id' => $b->semester->id,
                    'kode' => $b->semester->kode,
                    'nama' => $b->semester->nama,
                ] : null,
                'tanggal_bimbingan' => $b->tanggal_bimbingan ? $b->tanggal_bimbingan->format('Y-m-d') : null,
                'catatan_dosen' => $b->catatan_dosen,
                'catatan_mhs' => $b->catatan_mhs,
                'file' => $b->file,
                'file_url' => $b->file_url,
                'waktu_validasi_dosen' => $b->waktu_validasi_dosen?->toIso8601String(),
                'waktu_validasi_mhs' => $b->waktu_validasi_mhs?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'dosen_wali' => $dosenWali && $dosenWali->dosen ? [
                'id' => $dosenWali->id,
                'dosen' => [
                    'id' => $dosenWali->dosen->id,
                    'nama' => $dosenWali->dosen->nama,
                    'kode_dosen' => $dosenWali->dosen->kode_dosen ?? null,
                    'nidn' => $dosenWali->dosen->nidn ?? null,
                ],
            ] : null,
            'semester' => $selectedSemester ? [
                'id' => $selectedSemester->id,
                'kode' => $selectedSemester->kode,
                'nama' => $selectedSemester->nama,
            ] : null,
            'data' => $data,
        ]);
    }

    /**
     * Daftar mahasiswa untuk dropdown tambah bimbingan.
     * Mengembalikan mahasiswa dengan flag has_dosen_wali (sudah punya dosen wali = tidak bisa dipilih).
     */
    public function getMahasiswaOptions(Request $request): JsonResponse
    {
        $search = $request->get('search', '');
        $perPage = (int) $request->get('per_page', 50);

        $query = Mahasiswa::with(['prodi:id,nama,kode'])
            ->orderBy('nama');

        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereIn('id_prodi', $allowedProdiIds);
            }
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', '%' . $search . '%')
                    ->orWhere('nim', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $mahasiswaList = $query->limit($perPage)->get();

        // Id mahasiswa yang sudah punya dosen wali (aktif, belum dihapus)
        $dosenWaliMap = DosenWali::with('dosen:id,nama,kode_dosen')
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->get()
            ->keyBy('id_mahasiswa');

        $data = $mahasiswaList->map(function ($m) use ($dosenWaliMap) {
            $dosenWali = $dosenWaliMap->get($m->id);
            $hasDosenWali = $dosenWali !== null;
            $dosenWaliNama = $hasDosenWali && $dosenWali->dosen
                ? $dosenWali->dosen->nama . ($dosenWali->dosen->kode_dosen ? ' (' . $dosenWali->dosen->kode_dosen . ')' : '')
                : null;

            return [
                'id' => $m->id,
                'nama' => $m->nama,
                'nim' => $m->nim,
                'prodi' => $m->prodi ? ['id' => $m->prodi->id, 'nama' => $m->prodi->nama, 'kode' => $m->prodi->kode] : null,
                'has_dosen_wali' => $hasDosenWali,
                'dosen_wali_nama' => $dosenWaliNama,
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Set kuota bimbingan akademik untuk semua dosen yang belum memiliki kuota (null atau 0).
     */
    public function setKuotaBulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kuota_bimbingan_akademik' => ['required', 'integer', 'min:0'],
        ]);

        $kuota = (int) $validated['kuota_bimbingan_akademik'];
        $updated = Dosen::where(function ($q) {
            $q->whereNull('kuota_bimbingan_akademik')
                ->orWhere('kuota_bimbingan_akademik', 0);
        })->update(['kuota_bimbingan_akademik' => $kuota]);

        return response()->json([
            'message' => 'Kuota bimbingan akademik berhasil diterapkan.',
            'updated_count' => $updated,
        ]);
    }

    /**
     * Download template Excel untuk import dosen wali (kolom: Kode Dosen, NIM).
     */
    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['Kode Dosen', 'NIM'];
        $sheet->fromArray([$headers], null, 'A1');

        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(20);

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:B1')->applyFromArray($headerStyle);

        $exampleRow = ['KD001', '2021001'];
        $sheet->fromArray([$exampleRow], null, 'A2');

        $filename = 'template_import_dosen_wali_' . date('YmdHis') . '.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment;filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Import data dosen wali dari file Excel (kolom: Kode Dosen, NIM).
     * Mengisi id_dosen dari kode_dosen dan id_mahasiswa dari nim.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getRealPath());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        if (count($rows) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'File Excel kosong atau tidak valid. Minimal harus ada baris header dan satu baris data.',
            ], 400);
        }

        array_shift($rows); // remove header

        $user = $request->user();
        $allowedProdiIds = ($user && $user->hasScopeRestriction()) ? $user->getAllowedProdiIds() : null;

        $errors = [];
        $successCount = 0;
        $skipCount = 0;

        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 2;

            if (empty(array_filter($row))) {
                continue;
            }

            $kodeDosen = isset($row[0]) ? trim((string) $row[0]) : '';
            $nim = isset($row[1]) ? trim((string) $row[1]) : '';

            if ($kodeDosen === '' || $nim === '') {
                $errors[] = "Baris {$rowNumber}: Kode Dosen dan NIM wajib diisi.";
                $skipCount++;
                continue;
            }

            $dosen = Dosen::where('kode_dosen', $kodeDosen)->first();
            if (!$dosen) {
                $errors[] = "Baris {$rowNumber}: Kode Dosen '{$kodeDosen}' tidak ditemukan.";
                $skipCount++;
                continue;
            }

            $mahasiswa = Mahasiswa::where('nim', $nim)->first();
            if (!$mahasiswa) {
                $errors[] = "Baris {$rowNumber}: NIM '{$nim}' tidak ditemukan.";
                $skipCount++;
                continue;
            }

            if ($allowedProdiIds !== null && !in_array((int) $mahasiswa->id_prodi, $allowedProdiIds, true)) {
                $errors[] = "Baris {$rowNumber}: Anda tidak memiliki akses ke mahasiswa dengan NIM '{$nim}'.";
                $skipCount++;
                continue;
            }

            $existing = DosenWali::where('id_dosen', $dosen->id)
                ->where('id_mahasiswa', $mahasiswa->id)
                ->first();

            if ($existing) {
                $errors[] = "Baris {$rowNumber}: Pasangan Kode Dosen '{$kodeDosen}' dan NIM '{$nim}' sudah terdaftar.";
                $skipCount++;
                continue;
            }

            DosenWali::create([
                'id_dosen' => $dosen->id,
                'id_mahasiswa' => $mahasiswa->id,
                'status' => 'active',
            ]);
            $successCount++;
        }

        return response()->json([
            'success' => true,
            'message' => 'Import selesai.',
            'data' => [
                'success_count' => $successCount,
                'skip_count' => $skipCount,
                'error_count' => count($errors),
                'errors' => $errors,
            ],
        ]);
    }
}

