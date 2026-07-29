<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Dosen;
use App\Models\DosenWali;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export (xlsx) catatan bimbingan akademik — dipanggil dari tombol "Ekspor Excel" di
 * App\Livewire\Dosen\Perwalian\Show. Logikanya disalin ulang dari
 * DosenWaliBimbinganController::exportExcelForBimbinganAkademikWali (bukan di-share), sama
 * seperti NilaiExportController terhadap NilaiController — lihat skill siak-livewire-module.
 */
class DosenBimbinganExportController extends Controller
{
    public function excel(int $idMahasiswa, Request $request): StreamedResponse
    {
        $user = Auth::user();
        $dosen = Dosen::where('id_user', $user->id)->firstOrFail();

        $dosenWali = DosenWali::with('mahasiswa')
            ->where('id_dosen', $dosen->id)
            ->where('id_mahasiswa', $idMahasiswa)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->firstOrFail();

        $query = $dosenWali->bimbingan()->with('semester')->orderByDesc('tanggal_bimbingan')->orderByDesc('id');

        if ($request->filled('id_semester')) {
            $query->where('id_semester', (int) $request->get('id_semester'));
        }

        $bimbinganRows = $query->get();

        $mahasiswa = $dosenWali->mahasiswa;
        $nim = $mahasiswa->nim ?? 'mahasiswa';
        $nama = $mahasiswa->nama ?? '';

        $namaDosenLengkap = trim(
            ($dosen->gelar_depan ? $dosen->gelar_depan.' ' : '').
            ($dosen->nama ?? '').
            ($dosen->gelar_belakang ? ', '.$dosen->gelar_belakang : '')
        );

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Bimbingan');

        $sheet->setCellValue('A1', 'Bimbingan akademik');
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', 'Mahasiswa: '.$nama.' (NIM: '.$nim.')');
        $sheet->mergeCells('A2:H2');

        $sheet->setCellValue('A3', 'Dosen wali: '.($namaDosenLengkap !== '' ? $namaDosenLengkap : '-'));
        $sheet->mergeCells('A3:H3');
        $sheet->setCellValue('A4', 'Diekspor: '.now()->format('Y-m-d H:i:s'));
        $sheet->mergeCells('A4:H4');

        $headers = [
            'No.', 'Semester', 'Tanggal bimbingan', 'Catatan dosen', 'Catatan mahasiswa',
            'Waktu validasi dosen', 'Waktu validasi mahasiswa', 'Ada berkas',
        ];
        $headerRow = 6;
        $sheet->fromArray([$headers], null, 'A'.$headerRow);

        $sheet->getStyle('A'.$headerRow.':H'.$headerRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $dataStart = $headerRow + 1;
        $no = 1;
        $rowIndex = $dataStart;
        foreach ($bimbinganRows as $r) {
            $sem = $r->semester;
            $semLabel = $sem ? trim(($sem->kode ?? '').' '.($sem->nama ?? '')) : '-';
            $sheet->fromArray([[
                $no++,
                $semLabel,
                $r->tanggal_bimbingan ? $r->tanggal_bimbingan->format('Y-m-d') : '',
                $r->catatan_dosen ?? '',
                $r->catatan_mhs ?? '',
                $r->waktu_validasi_dosen ? $r->waktu_validasi_dosen->format('Y-m-d H:i:s') : '',
                $r->waktu_validasi_mhs ? $r->waktu_validasi_mhs->format('Y-m-d H:i:s') : '',
                $r->file ? 'Ya' : 'Tidak',
            ]], null, 'A'.$rowIndex);
            $rowIndex++;
        }

        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(28);
        $sheet->getColumnDimension('C')->setWidth(16);
        $sheet->getColumnDimension('D')->setWidth(40);
        $sheet->getColumnDimension('E')->setWidth(40);
        $sheet->getColumnDimension('F')->setWidth(20);
        $sheet->getColumnDimension('G')->setWidth(20);
        $sheet->getColumnDimension('H')->setWidth(12);

        if ($rowIndex > $dataStart) {
            $last = $rowIndex - 1;
            $sheet->getStyle('D'.$dataStart.':E'.$last)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        }

        $safeNim = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $nim) ?: 'mahasiswa';
        $filename = 'bimbingan_akademik_'.$safeNim.'_'.date('Ymd_His').'.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
