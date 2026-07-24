<?php

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Models\PmbUser;
use App\Models\PmbCamaba;
use App\Models\Agama;
use App\Models\Negara;
use App\Models\Provinsi;
use App\Models\Kota;
use App\Models\Kecamatan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PenggunaController extends Controller
{
    /**
     * Menampilkan daftar pengguna dengan pagination dan search.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');
        $role = $request->get('role');

        $query = PmbUser::query();

        if ($role) {
            $query->where('role', $role);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $data = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Menyimpan pengguna baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:pmb_users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:admin,camaba'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['status'] = $validated['status'] ?? 'active';

        $pengguna = PmbUser::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pengguna berhasil ditambahkan',
            'data' => $pengguna,
        ], 201);
    }

    /**
     * Menampilkan detail pengguna.
     */
    public function show(PmbUser $pengguna): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $pengguna,
        ]);
    }

    /**
     * Mengupdate pengguna.
     */
    public function update(Request $request, PmbUser $pengguna): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('pmb_users', 'email')->ignore($pengguna->id),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'role' => ['sometimes', 'required', 'string', 'in:admin,camaba'],
            'status' => ['sometimes', 'nullable', 'string', 'in:active,inactive'],
        ]);

        if (isset($validated['password']) && $validated['password']) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $pengguna->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pengguna berhasil diupdate',
            'data' => $pengguna,
        ]);
    }

    /**
     * Reset password pengguna.
     */
    public function resetPassword(Request $request, PmbUser $pengguna): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        $pengguna->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil direset',
        ]);
    }

    /**
     * Menghapus pengguna (soft delete).
     */
    public function destroy(Request $request, PmbUser $pengguna): JsonResponse
    {
        // Jangan izinkan menghapus diri sendiri
        $currentUser = $request->user();
        if ($currentUser && $currentUser->id === $pengguna->id) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus akun sendiri',
            ], 403);
        }

        $pengguna->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pengguna berhasil dihapus',
        ]);
    }

    /**
     * Download template Excel untuk import data camaba.
     */
    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set header
        $headers = [
            'Nama*',
            'Email*',
            'Password (Kosongkan untuk default: password123)',
            'Tempat Lahir (Nama Kota)',
            'Tanggal Lahir (YYYY-MM-DD)',
            'Jenis Kelamin (L/P)',
            'No. HP',
            'No. WA',
            'Alamat',
            'RT',
            'RW',
            'Dusun',
            'Kelurahan',
            'Kode Pos',
            'Provinsi (Nama Provinsi)',
            'Kota (Nama Kota)',
            'Kecamatan (Nama Kecamatan)',
            'Negara (Nama Negara)',
            'No. KTP',
            'No. KK',
            'No. NPWP',
            'No. SIM',
            'No. KPS',
            'Nama Ayah',
            'No. HP Ayah',
            'Alamat Ayah',
            'Nama Ibu',
            'No. HP Ibu',
            'Alamat Ibu',
            'Nama Wali',
            'No. HP Wali',
            'Alamat Wali',
            'Agama (Nama Agama)',
            'Status Perkawinan (Tidak Kawin/Kawin)',
            'Kewarganegaraan (WNI/WNA)',
            'Asal Sekolah',
        ];

        $sheet->fromArray([$headers], null, 'A1');

        // Set column widths
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI'];
        $widths = [25, 30, 40, 20, 20, 20, 15, 15, 30, 10, 10, 15, 20, 12, 20, 20, 20, 20, 20, 20, 20, 15, 15, 25, 15, 30, 25, 15, 30, 25, 15, 30, 20, 20, 20, 25];
        
        foreach ($columns as $index => $col) {
            if (isset($widths[$index])) {
                $sheet->getColumnDimension($col)->setWidth($widths[$index]);
            }
        }

        // Style header
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];
        $headerCount = count($headers);
        $lastColumn = $columns[$headerCount - 1] ?? 'AI';
        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray($headerStyle);

        // Add example row
        $exampleRow = [
            'John Doe',
            'john.doe@example.com',
            '',
            'Jakarta',
            '2000-01-15',
            'L',
            '081234567890',
            '081234567890',
            'Jl. Contoh No. 123',
            '001',
            '002',
            'Dusun A',
            'Kelurahan B',
            '12345',
            'DKI Jakarta',
            'Jakarta Pusat',
            'Kecamatan C',
            'Indonesia',
            '1234567890123456',
            '1234567890123456',
            '12.345.678.9-000.000',
            '1234567890',
            '1234567890',
            'Ayah Contoh',
            '081234567891',
            'Jl. Ayah No. 123',
            'Ibu Contoh',
            '081234567892',
            'Jl. Ibu No. 123',
            'Wali Contoh',
            '081234567893',
            'Jl. Wali No. 123',
            'Islam',
            'Tidak Kawin',
            'WNI',
            'SMA Negeri 1',
        ];
        $sheet->fromArray([$exampleRow], null, 'A2');

        $filename = 'template_import_camaba_' . date('YmdHis') . '.xlsx';

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
     * Import data camaba dari file Excel.
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
                    'nama' => trim($row[0] ?? ''),
                    'email' => trim($row[1] ?? ''),
                    'password' => trim($row[2] ?? ''),
                    'tempat_lahir' => trim($row[3] ?? ''),
                    'tanggal_lahir' => trim($row[4] ?? ''),
                    'jenis_kelamin' => trim($row[5] ?? ''),
                    'no_hp' => trim($row[6] ?? ''),
                    'no_wa' => trim($row[7] ?? ''),
                    'alamat' => trim($row[8] ?? ''),
                    'rt' => trim($row[9] ?? ''),
                    'rw' => trim($row[10] ?? ''),
                    'dusun' => trim($row[11] ?? ''),
                    'kelurahan' => trim($row[12] ?? ''),
                    'kode_pos' => trim($row[13] ?? ''),
                    'provinsi_nama' => trim($row[14] ?? ''),
                    'kota_nama' => trim($row[15] ?? ''),
                    'kecamatan_nama' => trim($row[16] ?? ''),
                    'negara_nama' => trim($row[17] ?? ''),
                    'no_ktp' => trim($row[18] ?? ''),
                    'no_kk' => trim($row[19] ?? ''),
                    'no_npwp' => trim($row[20] ?? ''),
                    'no_sim' => trim($row[21] ?? ''),
                    'no_kps' => trim($row[22] ?? ''),
                    'nama_ayah' => trim($row[23] ?? ''),
                    'no_hp_ayah' => trim($row[24] ?? ''),
                    'alamat_ayah' => trim($row[25] ?? ''),
                    'nama_ibu' => trim($row[26] ?? ''),
                    'no_hp_ibu' => trim($row[27] ?? ''),
                    'alamat_ibu' => trim($row[28] ?? ''),
                    'nama_wali' => trim($row[29] ?? ''),
                    'no_hp_wali' => trim($row[30] ?? ''),
                    'alamat_wali' => trim($row[31] ?? ''),
                    'agama_nama' => trim($row[32] ?? ''),
                    'status_perkawinan' => trim($row[33] ?? ''),
                    'kewarganegaraan' => trim($row[34] ?? ''),
                    'asal_sekolah' => trim($row[35] ?? ''),
                ];

                // Validate required fields
                if (empty($data['nama'])) {
                    $errors[] = "Baris {$rowNumber}: Nama wajib diisi.";
                    $skipCount++;
                    continue;
                }

                if (empty($data['email'])) {
                    $errors[] = "Baris {$rowNumber}: Email wajib diisi.";
                    $skipCount++;
                    continue;
                }

                // Validate email format
                if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Baris {$rowNumber}: Format email tidak valid.";
                    $skipCount++;
                    continue;
                }

                // Check if email already exists in users
                if (PmbUser::where('email', $data['email'])->exists()) {
                    $errors[] = "Baris {$rowNumber}: Email '{$data['email']}' sudah terdaftar di sistem.";
                    $skipCount++;
                    continue;
                }

                // Check for duplicates in current import batch
                foreach ($processedRows as $processed) {
                    if ($data['email'] === $processed['email']) {
                        $errors[] = "Baris {$rowNumber}: Email '{$data['email']}' duplikat dalam file.";
                        $skipCount++;
                        continue 2;
                    }
                }

                // Find Negara by nama
                $id_negara = null;
                if (!empty($data['negara_nama'])) {
                    $negara = Negara::where('nama', 'like', '%' . $data['negara_nama'] . '%')->first();
                    if (!$negara) {
                        $errors[] = "Baris {$rowNumber}: Negara '{$data['negara_nama']}' tidak ditemukan di sistem.";
                        $skipCount++;
                        continue;
                    }
                    $id_negara = $negara->id;
                }

                // Find Provinsi by nama (and negara if provided)
                $id_provinsi = null;
                if (!empty($data['provinsi_nama'])) {
                    $provinsiQuery = Provinsi::where('nama', 'like', '%' . $data['provinsi_nama'] . '%');
                    if ($id_negara) {
                        $provinsiQuery->where('id_negara', $id_negara);
                    }
                    $provinsi = $provinsiQuery->first();
                    if ($provinsi) {
                        $id_provinsi = $provinsi->id;
                    }
                    // Jika tidak ditemukan, tetap lanjut dengan null (tidak skip)
                }

                // Find Kota by nama (and provinsi if provided)
                $id_kota = null;
                if (!empty($data['kota_nama'])) {
                    $kotaQuery = Kota::where('nama', 'like', '%' . $data['kota_nama'] . '%');
                    if ($id_provinsi) {
                        $kotaQuery->where('id_provinsi', $id_provinsi);
                    }
                    $kota = $kotaQuery->first();
                    if ($kota) {
                        $id_kota = $kota->id;
                    }
                    // Jika tidak ditemukan, tetap lanjut dengan null (tidak skip)
                }

                // Find Kota Lahir by nama
                $id_kota_lahir = null;
                if (!empty($data['tempat_lahir'])) {
                    $kotaLahir = Kota::where('nama', 'like', '%' . $data['tempat_lahir'] . '%')->first();
                    if ($kotaLahir) {
                        $id_kota_lahir = $kotaLahir->id;
                    }
                }

                // Find Kecamatan by nama (and kota if provided)
                $id_kecamatan = null;
                if (!empty($data['kecamatan_nama'])) {
                    $kecamatanQuery = Kecamatan::where('nama', 'like', '%' . $data['kecamatan_nama'] . '%');
                    if ($id_kota) {
                        $kecamatanQuery->where('id_kota', $id_kota);
                    }
                    $kecamatan = $kecamatanQuery->first();
                    if ($kecamatan) {
                        $id_kecamatan = $kecamatan->id;
                    }
                    // Jika tidak ditemukan, tetap lanjut dengan null (tidak skip)
                }

                // Find Agama by nama
                $id_agama = null;
                if (!empty($data['agama_nama'])) {
                    $agama = Agama::where('nama', 'like', '%' . $data['agama_nama'] . '%')->first();
                    if (!$agama) {
                        $errors[] = "Baris {$rowNumber}: Agama '{$data['agama_nama']}' tidak ditemukan di sistem.";
                        $skipCount++;
                        continue;
                    }
                    $id_agama = $agama->id;
                }

                // Validate dan konversi jenis kelamin (L/P ke Laki-laki/Perempuan)
                $jenis_kelamin = null;
                if (!empty($data['jenis_kelamin'])) {
                    $jk = strtoupper(trim($data['jenis_kelamin']));
                    if ($jk === 'L' || $jk === 'LAKI-LAKI') {
                        $jenis_kelamin = 'Laki-laki';
                    } elseif ($jk === 'P' || $jk === 'PEREMPUAN') {
                        $jenis_kelamin = 'Perempuan';
                    } else {
                        $errors[] = "Baris {$rowNumber}: Jenis Kelamin harus 'L' atau 'P'.";
                        $skipCount++;
                        continue;
                    }
                }

                // Validate status perkawinan
                $status_perkawinan = null;
                if (!empty($data['status_perkawinan'])) {
                    $sp = trim($data['status_perkawinan']);
                    if (!in_array($sp, ['Tidak Kawin', 'Kawin'])) {
                        $errors[] = "Baris {$rowNumber}: Status Perkawinan harus 'Tidak Kawin' atau 'Kawin'.";
                        $skipCount++;
                        continue;
                    }
                    $status_perkawinan = $sp;
                }

                // Validate kewarganegaraan
                $kewarganegaraan = null;
                if (!empty($data['kewarganegaraan'])) {
                    $kw = trim($data['kewarganegaraan']);
                    if (!in_array($kw, ['WNI', 'WNA'])) {
                        $errors[] = "Baris {$rowNumber}: Kewarganegaraan harus 'WNI' atau 'WNA'.";
                        $skipCount++;
                        continue;
                    }
                    $kewarganegaraan = $kw;
                }

                // Validate tanggal lahir
                $tanggal_lahir = null;
                if (!empty($data['tanggal_lahir'])) {
                    try {
                        $tanggal_lahir = \Carbon\Carbon::createFromFormat('Y-m-d', $data['tanggal_lahir'])->format('Y-m-d');
                    } catch (\Exception $e) {
                        $errors[] = "Baris {$rowNumber}: Format tanggal lahir tidak valid. Gunakan format YYYY-MM-DD.";
                        $skipCount++;
                        continue;
                    }
                }

                // Create user
                $password = !empty($data['password']) ? $data['password'] : 'password123';
                $user = PmbUser::create([
                    'name' => $data['nama'],
                    'email' => $data['email'],
                    'password' => Hash::make($password),
                    'role' => 'camaba',
                    'status' => 'active',
                ]);

                // Create camaba
                $camabaData = [
                    'id_user' => $user->id,
                    'nama' => $data['nama'],
                    'email' => $data['email'],
                    'id_kota_lahir' => $id_kota_lahir,
                    'tanggal_lahir' => $tanggal_lahir,
                    'jenis_kelamin' => $jenis_kelamin,
                    'no_hp' => $data['no_hp'] ?: null,
                    'no_wa' => $data['no_wa'] ?: null,
                    'alamat' => $data['alamat'] ?: null,
                    'kode_pos' => $data['kode_pos'] ?: null,
                    'rt' => $data['rt'] ?: null,
                    'rw' => $data['rw'] ?: null,
                    'dusun' => $data['dusun'] ?: null,
                    'kelurahan' => $data['kelurahan'] ?: null,
                    'id_kota' => $id_kota,
                    'id_kecamatan' => $id_kecamatan,
                    'id_provinsi' => $id_provinsi,
                    'id_negara' => $id_negara,
                    'no_ktp' => $data['no_ktp'] ?: null,
                    'no_kk' => $data['no_kk'] ?: null,
                    'no_npwp' => $data['no_npwp'] ?: null,
                    'no_sim' => $data['no_sim'] ?: null,
                    'no_kps' => $data['no_kps'] ?: null,
                    'nama_ayah' => $data['nama_ayah'] ?: null,
                    'nama_ibu' => $data['nama_ibu'] ?: null,
                    'nama_wali' => $data['nama_wali'] ?: null,
                    'no_hp_ayah' => $data['no_hp_ayah'] ?: null,
                    'no_hp_ibu' => $data['no_hp_ibu'] ?: null,
                    'no_hp_wali' => $data['no_hp_wali'] ?: null,
                    'alamat_ayah' => $data['alamat_ayah'] ?: null,
                    'alamat_ibu' => $data['alamat_ibu'] ?: null,
                    'alamat_wali' => $data['alamat_wali'] ?: null,
                    'id_agama' => $id_agama,
                    'status_perkawinan' => $status_perkawinan,
                    'kewarganegaraan' => $kewarganegaraan,
                    'asal_sekolah' => $data['asal_sekolah'] ?: null,
                    'status' => 'active',
                ];

                PmbCamaba::create($camabaData);

                $processedRows[] = ['email' => $data['email']];
                $successCount++;
            }

            DB::commit();

            $message = "Import selesai. Berhasil: {$successCount}, Gagal: {$skipCount}.";
            if (!empty($errors)) {
                $message .= " Detail error: " . implode(' ', array_slice($errors, 0, 5));
                if (count($errors) > 5) {
                    $message .= " dan " . (count($errors) - 5) . " error lainnya.";
                }
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'success_count' => $successCount,
                    'skip_count' => $skipCount,
                    'errors' => $errors,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat import: ' . $e->getMessage(),
            ], 500);
        }
    }
}

