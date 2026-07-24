<?php

namespace App\Http\Controllers;

use App\Models\Agama;
use App\Models\Dosen;
use App\Models\Kota;
use App\Models\Provinsi;
use App\Models\Negara;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DosenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');

        $query = Dosen::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('kode_dosen', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%")
                  ->orWhere('nidn', 'like', "%{$search}%");
            });
        }
        $query->withCount('mahasiswaBimbingan');
        $query->with('provinsi', 'kota', 'negara', 'prodiAsKaprodi');
        $data = $query->orderBy('nama')->paginate($perPage);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_user' => ['nullable', 'integer', 'exists:users,id'],
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:dosen,email'],
            'kode_dosen' => ['nullable', 'string', 'max:50', 'unique:dosen,kode_dosen'],
            'nip' => ['nullable', 'string', 'max:50', 'unique:dosen,nip'],
            'nidn' => ['nullable', 'string', 'max:50', 'unique:dosen,nidn'],
            'gelar_depan' => ['nullable', 'string', 'max:50'],
            'gelar_belakang' => ['nullable', 'string', 'max:50'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'jenis_kelamin' => ['nullable', 'string', Rule::in(['L', 'P'])],
            'agama' => ['nullable', 'integer'],
            'status_perkawinan' => ['nullable', 'string', 'max:50'],
            'kewarganegaraan' => ['nullable', 'string', 'max:50'],
            'no_hp' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string'],
            'kode_pos' => ['nullable', 'string', 'max:20'],
            'id_kota' => ['nullable', 'string', 'max:50'],
            'id_provinsi' => ['nullable', 'string', 'max:50'],
            'id_negara' => ['nullable', 'string', 'max:50'],
            'foto' => ['nullable', 'string', 'max:255'],
            'nidk' => ['nullable', 'string', 'max:50'],
            'nupn' => ['nullable', 'string', 'max:50'],
            'kuota_bimbingan_akademik' => ['nullable', 'integer', 'min:0'],
            'kuota_bimbingan_ta' => ['nullable', 'integer', 'min:0'],
        ]);

        $dosen = Dosen::create($validated);

        return response()->json($dosen, 201);
    }

    public function show(Dosen $dosen): JsonResponse
    {
        $dosen->load('provinsi', 'kota', 'negara');
        return response()->json($dosen);
    }

    public function update(Request $request, Dosen $dosen): JsonResponse
    {
        $validated = $request->validate([
            'id_user' => ['nullable', 'integer', 'exists:users,id'],
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('dosen', 'email')->ignore($dosen->id)],
            'kode_dosen' => ['nullable', 'string', 'max:50', Rule::unique('dosen', 'kode_dosen')->ignore($dosen->id)],
            'nip' => ['nullable', 'string', 'max:50', Rule::unique('dosen', 'nip')->ignore($dosen->id)],
            'nidn' => ['nullable', 'string', 'max:50', Rule::unique('dosen', 'nidn')->ignore($dosen->id)],
            'gelar_depan' => ['nullable', 'string', 'max:50'],
            'gelar_belakang' => ['nullable', 'string', 'max:50'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'jenis_kelamin' => ['nullable', 'string', Rule::in(['L', 'P'])],
            'agama' => ['nullable', 'integer'],
            'status_perkawinan' => ['nullable', 'string', 'max:50'],
            'kewarganegaraan' => ['nullable', 'string', 'max:50'],
            'no_hp' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string'],
            'kode_pos' => ['nullable', 'string', 'max:20'],
            'id_kota' => ['nullable', 'string', 'max:50'],
            'id_provinsi' => ['nullable', 'string', 'max:50'],
            'id_negara' => ['nullable', 'string', 'max:50'],
            'foto' => ['nullable', 'string', 'max:255'],
            'nidk' => ['nullable', 'string', 'max:50'],
            'nupn' => ['nullable', 'string', 'max:50'],
            'kuota_bimbingan_akademik' => ['nullable', 'integer', 'min:0'],
            'kuota_bimbingan_ta' => ['nullable', 'integer', 'min:0'],
        ]);

        $dosen->update($validated);

        return response()->json($dosen);
    }

    public function destroy(Dosen $dosen): JsonResponse
    {
        $dosen->delete();

        return response()->json(['message' => 'Dosen dihapus']);
    }

    /**
     * Get profile dosen yang sedang login
     */
    public function getMyProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::with(['provinsi', 'kota', 'negara'])
            ->where('id_user', $user->id)
            ->first();

        if (!$dosen) {
            return response()->json([
                'message' => 'Data dosen tidak ditemukan'
            ], 404);
        }

        return response()->json($dosen);
    }

    /**
     * Update profile dosen yang sedang login
     */
    public function updateMyProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();

        if (!$dosen) {
            return response()->json([
                'message' => 'Data dosen tidak ditemukan'
            ], 404);
        }

        $validated = $request->validate([
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('dosen', 'email')->ignore($dosen->id)],
            'kode_dosen' => ['nullable', 'string', 'max:50', Rule::unique('dosen', 'kode_dosen')->ignore($dosen->id)],
            'nip' => ['nullable', 'string', 'max:50', Rule::unique('dosen', 'nip')->ignore($dosen->id)],
            'nidn' => ['nullable', 'string', 'max:50', Rule::unique('dosen', 'nidn')->ignore($dosen->id)],
            'gelar_depan' => ['nullable', 'string', 'max:50'],
            'gelar_belakang' => ['nullable', 'string', 'max:50'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'jenis_kelamin' => ['nullable', 'string', Rule::in(['L', 'P'])],
            'agama' => ['nullable', 'integer'],
            'status_perkawinan' => ['nullable', 'string', 'max:50'],
            'kewarganegaraan' => ['nullable', 'string', 'max:50'],
            'no_hp' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string'],
            'kode_pos' => ['nullable', 'string', 'max:20'],
            'id_kota' => ['nullable', 'string', 'max:50'],
            'id_provinsi' => ['nullable', 'string', 'max:50'],
            'id_negara' => ['nullable', 'string', 'max:50'],
        ]);

        $dosen->update($validated);
        $dosen->load('provinsi', 'kota', 'negara');

        return response()->json($dosen);
    }

    /**
     * Update password dosen yang sedang login
     */
    public function updateMyPassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'password_lama' => ['required', 'string'],
            'password_baru' => ['required', 'string', 'min:8'],
            'password_baru_confirmation' => ['required', 'string', 'same:password_baru'],
        ]);

        if (!\Illuminate\Support\Facades\Hash::check($validated['password_lama'], $user->password)) {
            return response()->json([
                'message' => 'Password lama tidak sesuai'
            ], 422);
        }

        $user->password = \Illuminate\Support\Facades\Hash::make($validated['password_baru']);
        $user->save();

        return response()->json([
            'message' => 'Password berhasil diubah'
        ]);
    }

    /**
     * Upload foto dosen yang sedang login
     */
    public function uploadMyFoto(Request $request): JsonResponse
    {
        $user = $request->user();
        $dosen = Dosen::where('id_user', $user->id)->first();

        if (!$dosen) {
            return response()->json([
                'message' => 'Data dosen tidak ditemukan'
            ], 404);
        }

        $validated = $request->validate([
            'foto' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:2048'],
        ]);

        // Hapus foto lama jika ada
        if ($dosen->foto) {
            Storage::disk('public')->delete($dosen->foto);
        }

        // Simpan foto baru
        $foto = $request->file('foto');
        $filename = 'dosen_' . $dosen->id . '_' . time() . '.' . $foto->getClientOriginalExtension();
        $path = $foto->storeAs('dosen/foto', $filename, 'public');

        $dosen->foto = $path;
        $dosen->save();

        return response()->json([
            'message' => 'Foto berhasil diupload',
            'foto' => $path,
            'foto_url' => asset('storage/' . $path),
        ]);
    }

    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set header
        $headers = [
            'Nama*',
            'Email*',
            'Kode Dosen',
            'NIP',
            'NIDN',
            'Gelar Depan',
            'Gelar Belakang',
            'Jenis Kelamin (L/P)',
            'Tanggal Lahir (YYYY-MM-DD)',
            'Tempat Lahir (Nama Kota)',
            'Provinsi (Nama Provinsi)',
            'Kode Pos',
            'Negara (Nama Negara)',
            'Agama (Nama Agama)',
            'No. HP',
            'Alamat',
        ];

        $sheet->fromArray([$headers], null, 'A1');

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(28);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(20);
        $sheet->getColumnDimension('H')->setWidth(20);
        $sheet->getColumnDimension('I')->setWidth(25);
        $sheet->getColumnDimension('J')->setWidth(25);
        $sheet->getColumnDimension('K')->setWidth(25);
        $sheet->getColumnDimension('L')->setWidth(12);
        $sheet->getColumnDimension('M')->setWidth(20);
        $sheet->getColumnDimension('N')->setWidth(18);
        $sheet->getColumnDimension('O')->setWidth(15);
        $sheet->getColumnDimension('P')->setWidth(30);

        // Style header
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:P1')->applyFromArray($headerStyle);

        // Add example row (Nama, Email, Kode Dosen, NIP, NIDN, Gelar Depan, Gelar Belakang, Jenis Kelamin, Tanggal Lahir, Tempat Lahir, Provinsi, Kode Pos, Negara, Agama, No. HP, Alamat)
        $exampleRow = [
            'Dr. John Doe',
            'john.doe@example.com',
            'KD001',
            '123456789012345678',
            '0012345678',
            'Dr.',
            'M.Sc.',
            'L',
            '1990-01-15',
            'Jakarta Pusat',
            'DKI Jakarta',
            '10110',
            'Indonesia',
            'Islam',
            '081234567890',
            'Jl. Contoh No. 123',
        ];
        $sheet->fromArray([$exampleRow], null, 'A2');

        $filename = 'template_import_dosen_' . date('YmdHis') . '.xlsx';

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

        $file = $request->file('file');
        try {
            $rows = $this->readDosenImportWorksheetRows($file);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membaca file Excel. Pastikan format .xlsx/.xls valid; hindari rumus error (#NAME?, #REF!). Salin data ke template lalu tempel sebagai nilai saja jika perlu. Detail: '.$e->getMessage(),
            ], 400);
        }

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
                    'nama' => isset($row[0]) ? trim((string) $row[0]) : null,
                    'email' => isset($row[1]) ? trim((string) $row[1]) : null,
                    'kode_dosen' => isset($row[2]) ? trim((string) $row[2]) : null,
                    'nip' => isset($row[3]) ? trim((string) $row[3]) : null,
                    'nidn' => isset($row[4]) ? trim((string) $row[4]) : null,
                    'gelar_depan' => isset($row[5]) ? trim((string) $row[5]) : null,
                    'gelar_belakang' => isset($row[6]) ? trim((string) $row[6]) : null,
                    'jenis_kelamin' => isset($row[7]) ? trim((string) $row[7]) : null,
                    'tanggal_lahir' => isset($row[8]) ? trim((string) $row[8]) : null,
                    'tempat_lahir' => isset($row[9]) ? trim((string) $row[9]) : null,
                    'provinsi_nama' => isset($row[10]) ? trim((string) $row[10]) : null,
                    'kode_pos' => isset($row[11]) ? trim((string) $row[11]) : null,
                    'negara_nama' => isset($row[12]) ? trim((string) $row[12]) : null,
                    'agama_nama' => isset($row[13]) ? trim((string) $row[13]) : null,
                    'no_hp' => isset($row[14]) ? trim((string) $row[14]) : null,
                    'alamat' => isset($row[15]) ? trim((string) $row[15]) : null,
                ];

                // Validate required fields
                if (empty($data['nama'])) {
                    $errors[] = "Baris {$rowNumber}: Nama wajib diisi.";
                    $skipCount++;
                    continue;
                }

                /*
                if (empty($data['email'])) {
                    $errors[] = "Baris {$rowNumber}: Email wajib diisi.";
                    $skipCount++;
                    continue;
                }
                */

                // Validate unique fields
                $uniqueChecks = [];
                if (!empty($data['kode_dosen'])) {
                    if (Dosen::where('kode_dosen', $data['kode_dosen'])->exists()) {
                        $errors[] = "Baris {$rowNumber}: Kode Dosen '{$data['kode_dosen']}' sudah ada di sistem.";
                        $skipCount++;
                        continue;
                    }
                    $uniqueChecks['kode_dosen'] = $data['kode_dosen'];
                }

                if (!empty($data['nip'])) {
                    if (Dosen::where('nip', $data['nip'])->exists()) {
                        $errors[] = "Baris {$rowNumber}: NIP '{$data['nip']}' sudah ada di sistem.";
                        $skipCount++;
                        continue;
                    }
                    $uniqueChecks['nip'] = $data['nip'] ?? null;
                }

                if (!empty($data['nidn'])) {
                    if (Dosen::where('nidn', $data['nidn'])->exists()) {
                        //$errors[] = "Baris {$rowNumber}: NIDN '{$data['nidn']}' sudah ada di sistem.";
                        //$skipCount++;
                        //continue;
                        $data['nidn'] = null;
                    }
                    else {
                        $uniqueChecks['nidn'] = $data['nidn'] ?? null;
                    }
                }

                // Email bentrok (sudah ada di DB atau baris sebelumnya dalam impor yang sama): simpan dengan email null
                if (!empty($data['email'])) {
                    if (Dosen::where('email', $data['email'])->exists()) {
                        $data['email'] = null;
                    } else {
                        $uniqueChecks['email'] = $data['email'] ?? null;
                    }
                }

                // Check for duplicates in current import batch (kode_dosen, nip, nidn)
                foreach ($processedRows as $processed) {
                    if (!empty($data['kode_dosen']) && !empty($processed['kode_dosen']) && $data['kode_dosen'] === $processed['kode_dosen']) {
                        $errors[] = "Baris {$rowNumber}: Kode Dosen '{$data['kode_dosen']}' duplikat dalam file.";
                        $skipCount++;
                        continue 2;
                    }
                    if (!empty($data['nip']) && !empty($processed['nip']) && $data['nip'] === $processed['nip']) {
                        $errors[] = "Baris {$rowNumber}: NIP '{$data['nip']}' duplikat dalam file.";
                        $skipCount++;
                        continue 2;
                    }
                    if (!empty($data['nidn']) && !empty($processed['nidn']) && $data['nidn'] === $processed['nidn']) {
                        $errors[] = "Baris {$rowNumber}: NIDN '{$data['nidn']}' duplikat dalam file.";
                        $skipCount++;
                        continue 2;
                    }
                }

                // Find location IDs by name
                $id_negara = null;
                $id_provinsi = null;
                $id_kota = null;
                $id_agama = null;

                if (!empty($data['negara_nama'])) {
                    $negara = Negara::where('nama', 'like', '%' . $data['negara_nama'] . '%')->first();
                    if ($negara) {
                        $id_negara = $negara->id;
                    }
                }

                if (!empty($data['provinsi_nama'])) {
                    $provinsiQuery = Provinsi::query();
                    if ($id_negara) {
                        $provinsiQuery->where('id_negara', $id_negara);
                    }
                    $provinsi = $provinsiQuery->where('nama', 'like', '%' . $data['provinsi_nama'] . '%')->first();
                    if ($provinsi) {
                        $id_provinsi = $provinsi->id;
                    }
                }

                if (!empty($data['tempat_lahir'])) {
                    $kotaQuery = Kota::query();
                    if ($id_provinsi) {
                        $kotaQuery->where('id_provinsi', $id_provinsi);
                    }
                    $kota = $kotaQuery->where('nama', 'like', '%' . $data['tempat_lahir'] . '%')->first();
                    if ($kota) {
                        $id_kota = $kota->id;
                    }
                }

                // Resolve agama by nama
                if (!empty($data['agama_nama'])) {
                    $agama = Agama::where('nama', 'like', '%' . $data['agama_nama'] . '%')->first();
                    if ($agama) {
                        $id_agama = $agama->id;
                    }
                }

                // Validate jenis kelamin
                if (!empty($data['jenis_kelamin']) && !in_array(strtoupper($data['jenis_kelamin']), ['L', 'P'])) {
                    $jenis_kelamin = null;
                } else {
                    $jenis_kelamin = !empty($data['jenis_kelamin']) ? strtoupper($data['jenis_kelamin']) : null;
                }

                // Prepare data for insert
                $dosenData = [
                    'nama' => $data['nama'],
                    'email' => $data['email'] ?: null,
                    'kode_dosen' => $data['kode_dosen'] ?: null,
                    'nip' => $data['nip'] ?: null,
                    'nidn' => $data['nidn'] ?: null,
                    'gelar_depan' => $data['gelar_depan'] ?: null,
                    'gelar_belakang' => $data['gelar_belakang'] ?: null,
                    'jenis_kelamin' => $jenis_kelamin,
                    'tanggal_lahir' => $data['tanggal_lahir'] ?: null,
                    'tempat_lahir' => $data['tempat_lahir'] ?: null,
                    'no_hp' => $data['no_hp'] ?: null,
                    'alamat' => $data['alamat'] ?: null,
                    'kode_pos' => $data['kode_pos'] ?: null,
                    'agama' => $id_agama,
                    'id_kota' => $id_kota,
                    'id_provinsi' => $id_provinsi,
                    'id_negara' => $id_negara,
                ];

                Dosen::create($dosenData);
                $successCount++;
                $processedRows[] = $uniqueChecks;
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

    /**
     * Membaca baris dari lembar aktif untuk impor dosen (kolom A–P).
     * Perhitungan rumus Excel dimatikan agar sel error (#NAME?, #REF!, dll.) tidak memicu exception
     * di PhpSpreadsheet saat mengonversi ke array (getCalculatedValue).
     *
     * @return array<int, array<int, mixed>>
     */
    private function readDosenImportWorksheetRows(\Illuminate\Http\UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            throw new \RuntimeException('Berkas tidak dapat dibaca.');
        }

        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        $spreadsheet = $reader->load($path);
        $worksheet = $spreadsheet->getActiveSheet();

        $highestRow = (int) $worksheet->getHighestDataRow();
        if ($highestRow < 1) {
            return [];
        }

        // Template impor: 16 kolom (indeks 0–15), hindari memproses sheet luas di luar kolom data.
        $maxColIndex = 16;
        $lastColLetter = Coordinate::stringFromColumnIndex($maxColIndex);
        $cappedRow = min($highestRow, 100000);
        $range = 'A1:'.$lastColLetter.$cappedRow;

        // calculateFormulas=false, formatData=false: nilai mentah / rumus sebagai string, tanpa mesin hitung.
        $rows = $worksheet->rangeToArray($range, null, false, false, false);

        return array_values($rows);
    }
}
