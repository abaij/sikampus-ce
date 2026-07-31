<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\KelompokKelas;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\StatusAkademik;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export (xlsx) untuk halaman Administrasi > Mahasiswa — dipanggil dari tombol Export di
 * App\Livewire\Admin\Mahasiswa\Index. Query & filter (search, prodi, kelas mahasiswa, semester
 * masuk, status akademik) disalin ulang dari MahasiswaController::index (bukan di-share), sama
 * seperti NilaiExportController terhadap NilaiController — lihat skill siak-livewire-module.
 * Tidak dipaginasi: seluruh baris yang cocok dengan filter yang sedang dipilih ikut diexport.
 */
class MahasiswaExportController extends Controller
{
    public function excel(Request $request): StreamedResponse
    {
        $search = $request->get('search');
        $prodiId = $request->get('id_prodi') ? (int) $request->get('id_prodi') : null;
        $kelompokKelasId = $request->get('id_kelompok_kelas') ? (int) $request->get('id_kelompok_kelas') : null;
        $semesterMasukId = $request->get('id_semester_masuk') ? (int) $request->get('id_semester_masuk') : null;
        $statusAkademikId = $request->get('id_status_akademik') ? (int) $request->get('id_status_akademik') : null;

        $query = Mahasiswa::with(['prodi', 'kelompok_kelas', 'semester_masuk', 'status_akademik']);

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereIn('id_prodi', $allowedProdiIds);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('nim', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($prodiId) {
            $query->where('id_prodi', $prodiId);
        }

        if ($kelompokKelasId) {
            $query->where('id_kelompok_kelas', $kelompokKelasId);
        }

        if ($semesterMasukId) {
            $query->where('id_semester_masuk', $semesterMasukId);
        }

        if ($statusAkademikId) {
            $query->where('id_status_akademik', $statusAkademikId);
        }

        $mahasiswaList = $query->orderBy('nama')->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Mahasiswa');

        $row = 1;
        $sheet->setCellValue('A'.$row, 'DATA MAHASISWA');
        $sheet->mergeCells('A'.$row.':I'.$row);
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(14);
        $row++;

        $filterLines = [
            'Pencarian' => $search ?: null,
            'Prodi' => $prodiId ? Prodi::find($prodiId)?->nama : null,
            'Kelas Mahasiswa' => $kelompokKelasId ? KelompokKelas::find($kelompokKelasId)?->nama : null,
            'Semester Masuk' => $semesterMasukId ? Semester::find($semesterMasukId)?->nama : null,
            'Status Akademik' => $statusAkademikId ? StatusAkademik::find($statusAkademikId)?->nama : null,
        ];
        $filterLabel = collect($filterLines)->filter()->map(fn ($v, $k) => "{$k}: {$v}")->implode(' | ');

        $sheet->setCellValue('A'.$row, 'Filter:');
        $sheet->setCellValue('B'.$row, $filterLabel !== '' ? $filterLabel : 'Semua data (tanpa filter)');
        $row++;

        $sheet->setCellValue('A'.$row, 'Tanggal Export:');
        $sheet->setCellValue('B'.$row, date('d/m/Y H:i:s'));
        $row++;

        $sheet->setCellValue('A'.$row, 'Total Data:');
        $sheet->setCellValue('B'.$row, $mahasiswaList->count());
        $row += 2;

        $headers = ['No', 'NIM', 'Nama', 'Email', 'No. WA', 'Prodi', 'Kelas Mahasiswa', 'Semester Masuk', 'Status Akademik'];
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
        foreach ($mahasiswaList as $mhs) {
            $sheet->setCellValue('A'.$row, $no);
            $sheet->setCellValue('B'.$row, $mhs->nim ?? '-');
            $sheet->setCellValue('C'.$row, $mhs->nama);
            $sheet->setCellValue('D'.$row, $mhs->email ?? '-');
            $sheet->setCellValue('E'.$row, $mhs->no_wa ?? '-');
            $sheet->setCellValue('F'.$row, $mhs->prodi?->nama ?? '-');
            $sheet->setCellValue('G'.$row, $mhs->kelompok_kelas?->nama ?? '-');
            $sheet->setCellValue('H'.$row, $mhs->semester_masuk?->nama ?? '-');
            $sheet->setCellValue('I'.$row, $mhs->status_akademik?->nama ?? '-');

            $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row++;
            $no++;
        }

        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(30);
        $sheet->getColumnDimension('D')->setWidth(28);
        $sheet->getColumnDimension('E')->setWidth(16);
        $sheet->getColumnDimension('F')->setWidth(25);
        $sheet->getColumnDimension('G')->setWidth(20);
        $sheet->getColumnDimension('H')->setWidth(18);
        $sheet->getColumnDimension('I')->setWidth(16);

        if ($row > $headerRow + 1) {
            $sheet->setAutoFilter('A'.$headerRow.':'.$lastHeaderCol.($row - 1));
        }

        $filename = 'mahasiswa_'.date('YmdHis').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
