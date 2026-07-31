<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Dosen;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export (xlsx) untuk halaman Administrasi > Dosen — dipanggil dari tombol Export di
 * App\Livewire\Admin\Dosen\Index. Query & filter (search) disalin ulang dari
 * DosenController::index (bukan di-share), sama seperti MahasiswaExportController terhadap
 * MahasiswaController — lihat skill siak-livewire-module. Tidak ada scope-filter karena dosen
 * tidak punya kolom id_fakultas/id_prodi langsung, sama seperti App\Livewire\Admin\Dosen\Index.
 * Tidak dipaginasi: seluruh baris yang cocok dengan filter yang sedang dipilih ikut diexport.
 */
class DosenExportController extends Controller
{
    public function excel(Request $request): StreamedResponse
    {
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

        $dosenList = $query->orderBy('nama')->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Dosen');

        $row = 1;
        $sheet->setCellValue('A'.$row, 'DATA DOSEN');
        $sheet->mergeCells('A'.$row.':J'.$row);
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(14);
        $row++;

        $sheet->setCellValue('A'.$row, 'Pencarian:');
        $sheet->setCellValue('B'.$row, $search ?: 'Semua data (tanpa filter)');
        $row++;

        $sheet->setCellValue('A'.$row, 'Tanggal Export:');
        $sheet->setCellValue('B'.$row, date('d/m/Y H:i:s'));
        $row++;

        $sheet->setCellValue('A'.$row, 'Total Data:');
        $sheet->setCellValue('B'.$row, $dosenList->count());
        $row += 2;

        $headers = ['No', 'Kode Dosen', 'Nama', 'NIP', 'NIDN', 'Email', 'No. HP', 'Jenis Kelamin', 'Tempat Lahir', 'Alamat'];
        $sheet->fromArray([$headers], null, 'A'.$row);

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $lastHeaderCol = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle('A'.$row.':'.$lastHeaderCol.$row)->applyFromArray($headerStyle);

        $headerRow = $row;
        $row++;
        $no = 1;
        foreach ($dosenList as $dosen) {
            $namaLengkap = trim(($dosen->gelar_depan ? $dosen->gelar_depan.' ' : '').$dosen->nama.($dosen->gelar_belakang ? ', '.$dosen->gelar_belakang : ''));

            $sheet->setCellValue('A'.$row, $no);
            $sheet->setCellValue('B'.$row, $dosen->kode_dosen ?? '-');
            $sheet->setCellValue('C'.$row, $namaLengkap);
            $sheet->setCellValue('D'.$row, $dosen->nip ?? '-');
            $sheet->setCellValue('E'.$row, $dosen->nidn ?? '-');
            $sheet->setCellValue('F'.$row, $dosen->email ?? '-');
            $sheet->setCellValue('G'.$row, $dosen->no_hp ?? '-');
            $sheet->setCellValue('H'.$row, $dosen->jenis_kelamin ?? '-');
            $sheet->setCellValue('I'.$row, $dosen->tempat_lahir ?? '-');
            $sheet->setCellValue('J'.$row, $dosen->alamat ?? '-');

            $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row++;
            $no++;
        }

        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(30);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(28);
        $sheet->getColumnDimension('G')->setWidth(16);
        $sheet->getColumnDimension('H')->setWidth(14);
        $sheet->getColumnDimension('I')->setWidth(20);
        $sheet->getColumnDimension('J')->setWidth(30);

        if ($row > $headerRow + 1) {
            $sheet->setAutoFilter('A'.$headerRow.':'.$lastHeaderCol.($row - 1));
        }

        $filename = 'dosen_'.date('YmdHis').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
