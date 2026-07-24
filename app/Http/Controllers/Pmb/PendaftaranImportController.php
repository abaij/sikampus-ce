<?php

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Models\PmbCamaba;
use App\Models\PmbPeriode;
use App\Models\PmbPendaftaran;
use App\Models\PmbProdiPilih;
use App\Models\Prodi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PendaftaranImportController extends Controller
{
    /**
     * Download template Excel untuk import data pendaftaran.
     */
    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set header
        $headers = [
            'Nama Camaba*',
            'Kode Periode*',
            'No. Pendaftaran (Kosongkan untuk auto-generate)',
            'Kode Prodi (Pisahkan dengan koma jika lebih dari satu)*',
            'Tanggal Pendaftaran (YYYY-MM-DD, kosongkan untuk hari ini)',
            'Status (pending/approved/rejected/verified, default: pending)',
            'Nama Jalur Masuk (Opsional)',
            'Nama Jenis Daftar (Opsional)',
            'Keterangan (Opsional)',
        ];

        $sheet->fromArray([$headers], null, 'A1');

        // Set column widths
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
        $widths = [30, 20, 35, 40, 30, 25, 25, 25, 30];
        
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
        $sheet->getStyle('A1:I1')->applyFromArray($headerStyle);

        // Add example row
        $exampleRow = [
            'John Doe',
            'PMB2024',
            '',
            'IF,SI',
            '2024-01-15',
            'pending',
            'SNMPTN',
            'Reguler',
            'Pendaftaran via import',
        ];
        $sheet->fromArray([$exampleRow], null, 'A2');

        $filename = 'template_import_pendaftaran_' . date('YmdHis') . '.xlsx';

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
     * Import data pendaftaran dari file Excel.
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

        DB::beginTransaction();
        try {
            foreach ($rows as $rowIndex => $row) {
                $rowNumber = $rowIndex + 2; // +2 karena header di row 1 dan array 0-indexed

                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                $data = [
                    'nama_camaba' => trim($row[0] ?? ''),
                    'kode_periode' => trim($row[1] ?? ''),
                    'no_pendaftaran' => trim($row[2] ?? ''),
                    'kode_prodi' => trim($row[3] ?? ''),
                    'tanggal_pendaftaran' => trim($row[4] ?? ''),
                    'status' => trim($row[5] ?? ''),
                    'kode_jalur_masuk' => trim($row[6] ?? ''),
                    'kode_jenis_daftar' => trim($row[7] ?? ''),
                    'keterangan' => trim($row[8] ?? ''),
                ];

                // Validate required fields
                if (empty($data['nama_camaba'])) {
                    $errors[] = "Baris {$rowNumber}: Nama Camaba wajib diisi.";
                    $skipCount++;
                    continue;
                }

                if (empty($data['kode_periode'])) {
                    $errors[] = "Baris {$rowNumber}: Kode Periode wajib diisi.";
                    $skipCount++;
                    continue;
                }

                if (empty($data['kode_prodi'])) {
                    $errors[] = "Baris {$rowNumber}: Kode Prodi wajib diisi.";
                    $skipCount++;
                    continue;
                }

                // Find Camaba by nama
                $camaba = PmbCamaba::where('nama', 'like', '%' . $data['nama_camaba'] . '%')->first();
                if (!$camaba) {
                    $errors[] = "Baris {$rowNumber}: Camaba dengan nama '{$data['nama_camaba']}' tidak ditemukan.";
                    $skipCount++;
                    continue;
                }

                // Find Periode by kode
                $periode = PmbPeriode::where('kode', $data['kode_periode'])->first();
                if (!$periode) {
                    $errors[] = "Baris {$rowNumber}: Periode dengan kode '{$data['kode_periode']}' tidak ditemukan.";
                    $skipCount++;
                    continue;
                }

                // Check if pendaftaran already exists (by no_pendaftaran or by camaba+periode)
                $existingPendaftaran = null;
                if (!empty($data['no_pendaftaran'])) {
                    $existingPendaftaran = PmbPendaftaran::where('no_pendaftaran', $data['no_pendaftaran'])
                        ->where('id_periode', $periode->id)
                        ->first();
                } else {
                    $existingPendaftaran = PmbPendaftaran::where('id_camaba', $camaba->id)
                        ->where('id_periode', $periode->id)
                        ->first();
                }

                // Generate no_pendaftaran if not provided
                $noPendaftaran = $data['no_pendaftaran'];
                if (empty($noPendaftaran)) {
                    $noPendaftaran = $this->generateNoPendaftaran($periode->id);
                } else {
                    // Check if no_pendaftaran already exists
                    if (PmbPendaftaran::where('no_pendaftaran', $noPendaftaran)
                        ->where('id_periode', $periode->id)
                        ->where('id', '!=', $existingPendaftaran?->id)
                        ->exists()) {
                        $errors[] = "Baris {$rowNumber}: Nomor pendaftaran '{$noPendaftaran}' sudah digunakan di periode ini.";
                        $skipCount++;
                        continue;
                    }
                }

                // Parse kode prodi (bisa dipisah dengan koma atau semicolon)
                $kodeProdiList = preg_split('/[,;]/', $data['kode_prodi']);
                $kodeProdiList = array_map('trim', $kodeProdiList);
                $kodeProdiList = array_filter($kodeProdiList);

                if (empty($kodeProdiList)) {
                    $errors[] = "Baris {$rowNumber}: Kode Prodi tidak valid.";
                    $skipCount++;
                    continue;
                }

                // Find Prodi by kode
                $prodiList = [];
                foreach ($kodeProdiList as $kodeProdi) {
                    $prodi = Prodi::where('kode', $kodeProdi)->first();
                    if (!$prodi) {
                        $errors[] = "Baris {$rowNumber}: Prodi dengan kode '{$kodeProdi}' tidak ditemukan.";
                        $skipCount++;
                        continue 2; // Continue outer loop
                    }
                    $prodiList[] = $prodi;
                }

                // Validate tanggal pendaftaran
                $tanggalPendaftaran = now()->toDateString();
                if (!empty($data['tanggal_pendaftaran'])) {
                    try {
                        $tanggalPendaftaran = \Carbon\Carbon::createFromFormat('Y-m-d', $data['tanggal_pendaftaran'])->format('Y-m-d');
                    } catch (\Exception $e) {
                        $errors[] = "Baris {$rowNumber}: Format tanggal pendaftaran tidak valid. Gunakan format YYYY-MM-DD.";
                        $skipCount++;
                        continue;
                    }
                }

                // Validate status
                $status = $data['status'] ?: 'pending';
                if (!in_array($status, ['pending', 'approved', 'rejected', 'verified'])) {
                    $errors[] = "Baris {$rowNumber}: Status harus salah satu dari: pending, approved, rejected, verified.";
                    $skipCount++;
                    continue;
                }

                // Find Jalur Masuk by nama (if provided)
                $idJalurMasuk = null;
                if (!empty($data['kode_jalur_masuk'])) {
                    // JalurMasuk tidak punya kolom kode, jadi cari berdasarkan nama
                    $jalurMasuk = \App\Models\JalurMasuk::where('nama', 'like', '%' . $data['kode_jalur_masuk'] . '%')->first();
                    if ($jalurMasuk) {
                        $idJalurMasuk = $jalurMasuk->id;
                    }
                }

                // Find Jenis Daftar by nama (if provided)
                $idJenisDaftar = null;
                if (!empty($data['kode_jenis_daftar'])) {
                    // JenisDaftar tidak punya kolom kode, jadi cari berdasarkan nama
                    $jenisDaftar = \App\Models\JenisDaftar::where('nama', 'like', '%' . $data['kode_jenis_daftar'] . '%')->first();
                    if ($jenisDaftar) {
                        $idJenisDaftar = $jenisDaftar->id;
                    }
                }

                // Create or update pendaftaran
                if ($existingPendaftaran) {
                    $pendaftaran = $existingPendaftaran;
                    $pendaftaran->update([
                        'no_pendaftaran' => $noPendaftaran,
                        'tanggal_pendaftaran' => $tanggalPendaftaran,
                        'status' => $status,
                        'id_jalur_masuk' => $idJalurMasuk,
                        'id_jenis_daftar' => $idJenisDaftar,
                        'keterangan' => $data['keterangan'] ?: null,
                    ]);
                } else {
                    $pendaftaran = PmbPendaftaran::create([
                        'id_camaba' => $camaba->id,
                        'id_periode' => $periode->id,
                        'tanggal_pendaftaran' => $tanggalPendaftaran,
                        'no_pendaftaran' => $noPendaftaran,
                        'status' => $status,
                        'id_jalur_masuk' => $idJalurMasuk,
                        'id_jenis_daftar' => $idJenisDaftar,
                        'keterangan' => $data['keterangan'] ?: null,
                    ]);
                }

                // Delete existing prodi pilihan if updating
                if ($existingPendaftaran) {
                    PmbProdiPilih::where('id_pendaftaran', $pendaftaran->id)->delete();
                }

                // Create prodi pilihan
                foreach ($prodiList as $prodi) {
                    // Check if already exists (shouldn't happen after delete, but just in case)
                    $existingProdiPilih = PmbProdiPilih::where('id_pendaftaran', $pendaftaran->id)
                        ->where('id_prodi', $prodi->id)
                        ->first();
                    
                    if (!$existingProdiPilih) {
                        PmbProdiPilih::create([
                            'id_pendaftaran' => $pendaftaran->id,
                            'id_prodi' => $prodi->id,
                        ]);
                    }
                }

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

    /**
     * Generate nomor pendaftaran
     */
    private function generateNoPendaftaran(int $idPeriode): string
    {
        $periode = PmbPeriode::find($idPeriode);
        $kode = $periode->kode ?? 'PMB';
        $year = date('Y');
        $month = date('m');
        
        // Hitung jumlah pendaftaran di periode ini
        $count = PmbPendaftaran::where('id_periode', $idPeriode)->count() + 1;
        
        return sprintf('%s%s%s%04d', $kode, $year, $month, $count);
    }
}

