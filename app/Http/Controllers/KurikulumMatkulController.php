<?php

namespace App\Http\Controllers;

use App\Models\Kurikulum;
use App\Models\KurikulumMatkul;
use App\Models\Matkul;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KurikulumMatkulController extends Controller
{
    /**
     * Detail mata kuliah kurikulum (dari tabel kurikulum_matkul) + bobot penilaian.
     * Hanya untuk admin prodi; kurikulum harus dalam scope prodi user.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $km = KurikulumMatkul::with(['kurikulum', 'matkul', 'bobotPenilaian.jenisPenilaian'])
            ->find($id);

        if (!$km) {
            return response()->json(['message' => 'Mata kuliah kurikulum tidak ditemukan.'], 404);
        }

        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && !in_array((int) $km->kurikulum->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke mata kuliah kurikulum ini.');
            }
        }

        return response()->json($km);
    }

    /**
     * Salin kode, nama, nama EN, dan SKS dari tabel matkul ke baris kurikulum_matkul.
     * Semester rekomendasi & wajib/pilihan tidak diubah.
     */
    public function syncFromMatkul(Request $request, int $id): JsonResponse
    {
        $km = KurikulumMatkul::with('kurikulum')->find($id);
        if (!$km) {
            return response()->json(['message' => 'Mata kuliah kurikulum tidak ditemukan.'], 404);
        }

        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && !in_array((int) $km->kurikulum->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke mata kuliah kurikulum ini.');
            }
        }

        $matkul = Matkul::find($km->id_matkul);
        if (!$matkul) {
            return response()->json([
                'message' => 'Mata kuliah master tidak ditemukan atau sudah dihapus.',
            ], 422);
        }

        $km->update([
            'kode_matkul' => $matkul->kode ?? '',
            'nama_matkul' => $matkul->nama ?? '',
            'nama_matkul_en' => $matkul->nama_en,
            'sks' => $matkul->sks,
        ]);

        $km->refresh();
        $km->load(['kurikulum', 'matkul', 'bobotPenilaian.jenisPenilaian']);

        return response()->json($km);
    }

    /**
     * Update bobot penilaian untuk mata kuliah kurikulum.
     * Body: { items: [ { id_jenis_penilaian: number, bobot: number }, ... ] }
     * Validasi: total bobot maksimal 100%.
     */
    public function updateBobotPenilaian(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id_jenis_penilaian' => ['required', 'integer', 'exists:jenis_penilaian,id'],
            'items.*.bobot' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $km = KurikulumMatkul::with('kurikulum')->find($id);
        if (!$km) {
            return response()->json(['message' => 'Mata kuliah kurikulum tidak ditemukan.'], 404);
        }

        $user = $request->user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && !in_array((int) $km->kurikulum->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke mata kuliah kurikulum ini.');
            }
        }

        $items = $validated['items'];
        $total = array_sum(array_column($items, 'bobot'));
        if ($total > 100) {
            return response()->json([
                'message' => 'Total bobot penilaian tidak boleh melebihi 100%.',
                'errors' => [
                    'items' => ['Total saat ini: ' . round($total, 2) . '%. Maksimal 100%.'],
                ],
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Hapus fisik agar unique (id_kurikulum_matkul, id_jenis_penilaian) tidak bentrok saat create baru
            $km->bobotPenilaian()->forceDelete();
            foreach ($items as $row) {
                $bobot = (float) $row['bobot'];
                if ($bobot > 0) {
                    $km->bobotPenilaian()->create([
                        'id_jenis_penilaian' => (int) $row['id_jenis_penilaian'],
                        'bobot' => $bobot,
                    ]);
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menyimpan bobot penilaian.'], 500);
        }

        $km->load(['bobotPenilaian.jenisPenilaian']);
        return response()->json($km);
    }

    /**
     * Template impor relasi kurikulum ↔ mata kuliah (tabel kurikulum_matkul).
     * Kurikulum diacu dengan kode kurikulum; MK diacu dengan kode mata kuliah pada prodi yang sama dengan kurikulum.
     */
    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data');

        $headers = [
            'Kode Kurikulum*',
            'Kode Mata Kuliah*',
            'Semester rekomendasi',
            'Wajib',
            'SKS',
            'Kode MK (snapshot)',
            'Nama MK (snapshot)',
            'Nama MK EN (snapshot)',
        ];
        $sheet->fromArray([$headers], null, 'A1');

        foreach (['A' => 18, 'B' => 18, 'C' => 20, 'D' => 14, 'E' => 10, 'F' => 18, 'G' => 32, 'H' => 28] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2E7D32'],
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ];
        $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(36);

        $exampleRow = [
            'KUR-2024',
            'IF123',
            '3',
            'Ya',
            '',
            '',
            '',
            '',
        ];
        $sheet->fromArray([$exampleRow], null, 'A2');
        $sheet->freezePane('A2');

        $petunjuk = $spreadsheet->createSheet();
        $petunjuk->setTitle('Petunjuk');
        $petunjuk->getColumnDimension('A')->setWidth(100);
        $lines = [
            ['PANDUAN IMPORT KURIKULUM – MATA KULIAH (kurikulum_matkul)'],
            [''],
            ['A — Kode Kurikulum* : harus sudah ada di sistem (tabel kurikulum, kolom kode).'],
            ['B — Kode Mata Kuliah* : kode MK pada program studi yang sama dengan kurikulum (master matkul).'],
            ['C — Semester rekomendasi : opsional, angka 1–14.'],
            ['D — Wajib : opsional. Ya/1/wajib = wajib; Tidak/0/pilihan = pilihan. Default wajib.'],
            ['E — SKS : opsional; jika kosong dipakai SKS dari master matkul.'],
            ['F–H — Snapshot : opsional; salinan kode/nama untuk tampilan di kurikulum. Kosongkan = pakai nilai dari master matkul.'],
            [''],
            ['Kombinasi (kode kurikulum + kode MK) unik. Baris yang sudah ada akan diperbarui (semester, wajib, snapshot).'],
            ['Jika sebelumnya di-soft-delete, baris akan dipulihkan.'],
            [''],
            ['Baris 1 sheet "Data" = header. Isi data mulai baris 2.'],
        ];
        $row = 1;
        foreach ($lines as $line) {
            $petunjuk->setCellValue('A'.$row, $line[0]);
            $row++;
        }
        $petunjuk->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'template_import_kurikulum_matkul_'.date('YmdHis').'.xlsx';

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
     * Impor baris kurikulum_matkul dari Excel (kode kurikulum + kode MK per prodi kurikulum).
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $user = $request->user();
        $allowedProdiIds = ($user && $user->hasScopeRestriction()) ? $user->getAllowedProdiIds() : null;
        $by = $user ? ($user->name ?? (string) ($user->email ?? $user->id)) : 'system';

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
        $processedKeys = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $rowIndex => $row) {
                $rowNumber = $rowIndex + 2;

                if (empty(array_filter($row))) {
                    continue;
                }

                $kodeKurikulum = isset($row[0]) ? trim((string) $row[0]) : '';
                $kodeMatkul = isset($row[1]) ? trim((string) $row[1]) : '';
                $semRaw = $row[2] ?? null;
                $wajibRaw = $row[3] ?? null;
                $sksRaw = $row[4] ?? null;
                $snapKode = isset($row[5]) ? trim((string) $row[5]) : '';
                $snapNama = isset($row[6]) ? trim((string) $row[6]) : '';
                $snapNamaEn = isset($row[7]) ? trim((string) $row[7]) : '';

                if ($kodeKurikulum === '') {
                    $errors[] = "Baris {$rowNumber}: Kode kurikulum wajib diisi.";
                    $skipCount++;

                    continue;
                }

                if ($kodeMatkul === '') {
                    $errors[] = "Baris {$rowNumber}: Kode mata kuliah wajib diisi.";
                    $skipCount++;

                    continue;
                }

                $kurikulum = Kurikulum::where('kode', $kodeKurikulum)->whereNull('deleted_at')->first();
                if (! $kurikulum) {
                    $errors[] = "Baris {$rowNumber}: Kurikulum dengan kode '{$kodeKurikulum}' tidak ditemukan.";
                    $skipCount++;

                    continue;
                }

                if ($allowedProdiIds !== null && ! in_array((int) $kurikulum->id_prodi, $allowedProdiIds, true)) {
                    $errors[] = "Baris {$rowNumber}: Anda tidak memiliki akses ke kurikulum '{$kodeKurikulum}'.";
                    $skipCount++;

                    continue;
                }

                $matkul = Matkul::query()
                    ->where('kode', $kodeMatkul)
                    ->where('id_prodi', $kurikulum->id_prodi)
                    ->whereNull('deleted_at')
                    ->first();

                if (! $matkul) {
                    $errors[] = "Baris {$rowNumber}: Mata kuliah kode '{$kodeMatkul}' tidak ditemukan untuk prodi kurikulum ini.";
                    $skipCount++;

                    continue;
                }

                $dupKey = $kodeKurikulum.'|'.$kodeMatkul;
                if (isset($processedKeys[$dupKey])) {
                    $errors[] = "Baris {$rowNumber}: Duplikat kombinasi kurikulum '{$kodeKurikulum}' dan MK '{$kodeMatkul}' dalam berkas.";
                    $skipCount++;

                    continue;
                }
                $processedKeys[$dupKey] = true;

                $semesterRekomendasi = null;
                if ($semRaw !== null && $semRaw !== '') {
                    if (! is_numeric($semRaw)) {
                        $errors[] = "Baris {$rowNumber}: Semester rekomendasi harus angka 1–14.";
                        $skipCount++;

                        continue;
                    }
                    $semesterRekomendasi = (int) $semRaw;
                    if ($semesterRekomendasi < 1 || $semesterRekomendasi > 14) {
                        $errors[] = "Baris {$rowNumber}: Semester rekomendasi harus antara 1 dan 14.";
                        $skipCount++;

                        continue;
                    }
                }

                $isWajib = self::parseIsWajibImport($wajibRaw);

                $sks = null;
                if ($sksRaw !== null && $sksRaw !== '') {
                    if (! is_numeric($sksRaw)) {
                        $errors[] = "Baris {$rowNumber}: SKS harus berupa angka.";
                        $skipCount++;

                        continue;
                    }
                    $sks = (int) $sksRaw;
                    if ($sks < 0 || $sks > 99) {
                        $errors[] = "Baris {$rowNumber}: SKS tidak valid (0–99).";
                        $skipCount++;

                        continue;
                    }
                } else {
                    $sks = $matkul->sks !== null && $matkul->sks !== '' ? (int) $matkul->sks : null;
                }

                $kodeMatkulSnap = $snapKode !== '' ? $snapKode : (string) ($matkul->kode ?? '');
                $namaMatkulSnap = $snapNama !== '' ? $snapNama : (string) ($matkul->nama ?? '');
                $namaMatkulEnSnap = $snapNamaEn !== '' ? $snapNamaEn : ($matkul->nama_en);

                $payload = [
                    'id_kurikulum' => $kurikulum->id,
                    'id_matkul' => $matkul->id,
                    'kode_matkul' => $kodeMatkulSnap !== '' ? $kodeMatkulSnap : null,
                    'nama_matkul' => $namaMatkulSnap !== '' ? $namaMatkulSnap : null,
                    'nama_matkul_en' => $namaMatkulEnSnap,
                    'sks' => $sks,
                    'semester_rekomendasi' => $semesterRekomendasi,
                    'is_wajib' => $isWajib,
                    'updated_by' => $by,
                ];

                $existing = KurikulumMatkul::withTrashed()
                    ->where('id_kurikulum', $kurikulum->id)
                    ->where('id_matkul', $matkul->id)
                    ->first();

                if ($existing) {
                    if ($existing->trashed()) {
                        $existing->restore();
                    }
                    $existing->fill($payload);
                    $existing->save();
                } else {
                    $payload['created_by'] = $by;
                    KurikulumMatkul::create($payload);
                }

                $successCount++;
            }

            DB::commit();

            $message = "Import selesai. Berhasil: {$successCount}, Dilewati: {$skipCount}";
            if (count($errors) > 0) {
                $message .= '. Terdapat '.count($errors).' catatan/error.';
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
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Import gagal: '.$e->getMessage(),
            ], 500);
        }
    }

    private static function parseIsWajibImport(mixed $cell): bool
    {
        if ($cell === null || $cell === '') {
            return true;
        }
        $s = strtolower(trim((string) $cell));
        if (in_array($s, ['0', 'tidak', 't', 'no', 'n', 'false', 'f', 'pilihan', 'optional', 'opsional'], true)) {
            return false;
        }

        return true;
    }
}
