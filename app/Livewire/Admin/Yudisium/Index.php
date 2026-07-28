<?php

namespace App\Livewire\Admin\Yudisium;

use App\Models\Prodi;
use App\Models\Semester;
use App\Models\Yudisium;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    // Properti filter yang terikat <select> harus string, bukan ?int — lihat catatan skill soal
    // TypeError pada opsi kosong dari <select>.
    public string $filterProdi = '';

    public string $filterSemesterMasuk = '';

    public int $perPage = 10;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterProdi(): void
    {
        $this->resetPage();
    }

    public function updatingFilterSemesterMasuk(): void
    {
        $this->resetPage();
    }

    /**
     * Sama persis dengan YudisiumController::buildYudisiumQuery.
     */
    private function baseQuery(): Builder
    {
        $query = Yudisium::with(['mahasiswa.prodi', 'mahasiswa.semester_masuk', 'jenis_keluar']);

        $allowedProdiIds = null;
        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereHas('mahasiswa', function ($q) use ($allowedProdiIds) {
                    $q->whereIn('id_prodi', $allowedProdiIds);
                });
            }
        }

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->whereHas('mahasiswa', function ($mq) {
                    $mq->where('nama', 'like', "%{$this->search}%")
                        ->orWhere('nim', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                })
                    ->orWhere('no_ijazah', 'like', "%{$this->search}%")
                    ->orWhere('no_sk_yudisium', 'like', "%{$this->search}%")
                    ->orWhere('judul_skripsi', 'like', "%{$this->search}%");
            });
        }

        if ($this->filterProdi !== '' && ($allowedProdiIds === null || in_array((int) $this->filterProdi, $allowedProdiIds, true))) {
            $prodiId = (int) $this->filterProdi;
            $query->whereHas('mahasiswa', function ($q) use ($prodiId) {
                $q->where('id_prodi', $prodiId);
            });
        }

        if ($this->filterSemesterMasuk !== '') {
            $semesterMasukId = (int) $this->filterSemesterMasuk;
            $query->whereHas('mahasiswa', function ($q) use ($semesterMasukId) {
                $q->where('id_semester_masuk', $semesterMasukId);
            });
        }

        return $query;
    }

    /**
     * Sama persis dengan YudisiumController::exportPdf.
     */
    public function exportPdf()
    {
        $rows = $this->baseQuery()->orderBy('created_at')->get();

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; }
        .title { text-align: center; font-size: 13pt; font-weight: bold; margin-bottom: 4px; }
        .subtitle { text-align: center; font-size: 10pt; margin-bottom: 12px; }
        table.peserta { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.peserta th { background-color: #334155; color: #fff; padding: 6px 4px; border: 1px solid #000; font-size: 8pt; text-align: center; }
        table.peserta td { padding: 4px; border: 1px solid #000; font-size: 8pt; vertical-align: top; }
        table.peserta td.num, table.peserta td.ipk { text-align: center; }
        .footer { margin-top: 14px; font-size: 8pt; text-align: right; color: #444; }
        </style></head><body>';

        $html .= '<div class="title">LAMPIRAN SURAT KEPUTUSAN YUDISIUM</div>';
        $html .= '<div class="subtitle">Sesuai filter yang dipilih</div>';

        $html .= '<table class="peserta"><thead><tr>
            <th style="width:30px">No</th>
            <th>NIM</th>
            <th>Nama</th>
            <th>Program Studi</th>
            <th style="width:50px">IPK</th>
            <th>Jenis Keluar</th>
            <th>Judul Skripsi/TA</th>
        </tr></thead><tbody>';

        if ($rows->isEmpty()) {
            $html .= '<tr><td colspan="7" style="text-align:center">Tidak ada data sesuai filter.</td></tr>';
        } else {
            $no = 1;
            foreach ($rows as $row) {
                $m = $row->mahasiswa;
                $prodiText = $m?->prodi ? ($m->prodi->nama.($m->prodi->kode ? ' ('.$m->prodi->kode.')' : '')) : '—';
                $html .= '<tr>'
                    .'<td class="num">'.$no.'</td>'
                    .'<td>'.htmlspecialchars($m?->nim ?? '—').'</td>'
                    .'<td>'.htmlspecialchars($m?->nama ?? '—').'</td>'
                    .'<td>'.htmlspecialchars($prodiText).'</td>'
                    .'<td class="ipk">'.htmlspecialchars($row->ipk !== null ? number_format((float) $row->ipk, 2) : '—').'</td>'
                    .'<td>'.htmlspecialchars($row->jenis_keluar?->nama ?? '—').'</td>'
                    .'<td>'.htmlspecialchars($row->judul_skripsi ?? '—').'</td>'
                    .'</tr>';
                $no++;
            }
        }

        $html .= '</tbody></table>';
        $html .= '<div class="footer">Dicetak: '.now()->format('d/m/Y H:i').'</div></body></html>';

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = 'lampiran_sk_yudisium_'.date('Y-m-d_His').'.pdf';

        return response()->streamDownload(function () use ($dompdf) {
            echo $dompdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Sama persis dengan YudisiumController::exportExcel.
     */
    public function exportExcel()
    {
        $rows = $this->baseQuery()->orderBy('created_at')->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Yudisium');

        $headers = ['No', 'NIM', 'Nama', 'Program Studi', 'IPK', 'Jenis Keluar', 'No. SK Yudisium', 'Tanggal SK', 'No. Ijazah', 'Judul Skripsi/TA'];
        $sheet->fromArray([$headers], null, 'A1');
        $sheet->getStyle('A1:J1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $rowNum = 2;
        $no = 1;
        foreach ($rows as $row) {
            $m = $row->mahasiswa;
            $prodiText = $m?->prodi ? ($m->prodi->nama.($m->prodi->kode ? ' ('.$m->prodi->kode.')' : '')) : '—';
            $sheet->fromArray([[
                $no,
                $m?->nim ?? '—',
                $m?->nama ?? '—',
                $prodiText,
                $row->ipk !== null ? (float) $row->ipk : '—',
                $row->jenis_keluar?->nama ?? '—',
                $row->no_sk_yudisium ?? '—',
                $row->tanggal_sk_yudisium ?? '—',
                $row->no_ijazah ?? '—',
                $row->judul_skripsi ?? '—',
            ]], null, 'A'.$rowNum);
            $rowNum++;
            $no++;
        }

        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'yudisium_'.date('Y-m-d_His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function render()
    {
        $yudisiumList = $this->baseQuery()->orderByDesc('created_at')->paginate($this->perPage);

        $allowedProdiIds = null;
        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
        }

        $prodiQuery = Prodi::query()->whereNull('deleted_at');
        if ($allowedProdiIds !== null) {
            $prodiQuery->whereIn('id', $allowedProdiIds);
        }

        return view('livewire.admin.yudisium.index', [
            'yudisiumList' => $yudisiumList,
            'prodiOptions' => $prodiQuery->orderBy('nama')->get(['id', 'nama'])
                ->map(fn (Prodi $p) => (object) ['id' => $p->id, 'label' => $p->nama]),
            'semesterOptions' => Semester::orderByDesc('kode')->get(['id', 'kode', 'nama'])
                ->map(fn (Semester $s) => (object) ['id' => $s->id, 'label' => "{$s->nama} ({$s->kode})"]),
        ])->extends('layouts.web');
    }
}
