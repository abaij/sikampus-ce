<?php

namespace App\Livewire\Admin\Pembayaran;

use App\Models\Mahasiswa;
use App\Models\Pembayaran;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\Tagihan;
use App\Services\KeringananBiayaKreditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Laporan read-only: total tagihan vs total pembayaran disetujui per mahasiswa, sama persis
// dengan LaporanController::getPelunasanTagihan/exportPelunasanTagihan. Tidak ada scope prodi —
// controller aslinya juga tidak menerapkannya di sini.
class LaporanPelunasan extends Component
{
    use WithPagination;

    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'id_semester')]
    public string $filterSemester = '';

    #[Url(as: 'id_prodi')]
    public string $filterProdi = '';

    public int $perPage = 15;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterSemester(): void
    {
        $this->resetPage();
    }

    public function updatingFilterProdi(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function semesterOptions(): array
    {
        return Semester::orderByDesc('kode')
            ->get(['id', 'nama', 'kode'])
            ->mapWithKeys(fn ($s) => [$s->id => $s->kode ? "{$s->nama} ({$s->kode})" : $s->nama])
            ->all();
    }

    #[Computed]
    public function prodiOptions(): array
    {
        return Prodi::orderBy('nama')->pluck('nama', 'id')->all();
    }

    /**
     * Sama persis dengan LaporanController::buildPelunasanTagihanQuery.
     */
    private function baseQuery(): Builder
    {
        $semesterId = $this->filterSemester !== '' ? (int) $this->filterSemester : null;
        $prodiId = $this->filterProdi !== '' ? (int) $this->filterProdi : null;
        $search = trim($this->search) !== '' ? trim($this->search) : null;

        $tagihanSub = Tagihan::query()
            ->when($semesterId, fn ($q) => $q->where('id_semester', $semesterId))
            ->selectRaw('id_mahasiswa, SUM(total) as total_tagihan')
            ->groupBy('id_mahasiswa');

        $pembayaranSub = Pembayaran::query()
            ->join('tagihan', function ($join) {
                $join->on('pembayaran.id_tagihan', '=', 'tagihan.id')
                    ->whereNull('tagihan.deleted_at');
            })
            ->whereNotNull('pembayaran.approved_at')
            ->when($semesterId, fn ($q) => $q->where('tagihan.id_semester', $semesterId))
            ->selectRaw('tagihan.id_mahasiswa, SUM(pembayaran.nominal) as total_pembayaran')
            ->groupBy('tagihan.id_mahasiswa');

        $query = Mahasiswa::query()
            ->select(['mahasiswa.id', 'mahasiswa.nim', 'mahasiswa.nama', 'mahasiswa.id_prodi'])
            ->joinSub($tagihanSub, 'tt', 'mahasiswa.id', '=', 'tt.id_mahasiswa')
            ->leftJoinSub($pembayaranSub, 'tp', 'mahasiswa.id', '=', 'tp.id_mahasiswa')
            ->addSelect([
                DB::raw('tt.total_tagihan as agg_total_tagihan'),
                DB::raw('COALESCE(tp.total_pembayaran, 0) as agg_total_pembayaran'),
            ])
            ->whereNull('mahasiswa.deleted_at')
            ->when($prodiId, fn ($q) => $q->where('mahasiswa.id_prodi', $prodiId));

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('mahasiswa.nama', 'like', "%{$search}%")
                    ->orWhere('mahasiswa.nim', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('mahasiswa.nim');
    }

    /**
     * Sama persis dengan LaporanController::mapPelunasanTagihanToArray.
     */
    private function mapRows(Collection $collection): Collection
    {
        $prodiIds = $collection->pluck('id_prodi')->filter()->unique()->values();
        $prodiMap = $prodiIds->isNotEmpty()
            ? Prodi::whereIn('id', $prodiIds)->whereNull('deleted_at')->get()->keyBy('id')
            : collect();

        // Keringanan yang sudah disetujui ikut menutup kewajiban — dibatasi agar tidak melebihi
        // sisa yang belum terbayar supaya pencapaian tidak pernah lewat 100%.
        $kreditMap = KeringananBiayaKreditService::kreditPerMahasiswa(
            $collection->pluck('id')->all(),
            $this->filterSemester !== '' ? (int) $this->filterSemester : null
        );

        return $collection->map(function ($row) use ($prodiMap, $kreditMap) {
            $totalTagihan = (float) ($row->agg_total_tagihan ?? 0);
            $totalPembayaran = (float) ($row->agg_total_pembayaran ?? 0);
            $keringanan = min(
                (float) ($kreditMap[$row->id] ?? 0),
                max(0.0, $totalTagihan - $totalPembayaran)
            );
            $persentase = $totalTagihan > 0
                ? round(100.0 * ($totalPembayaran + $keringanan) / $totalTagihan, 2)
                : 0.0;

            return (object) [
                'id' => $row->id,
                'nim' => $row->nim,
                'nama' => $row->nama,
                'prodi' => $prodiMap->get($row->id_prodi),
                'total_tagihan' => $totalTagihan,
                'total_pembayaran' => $totalPembayaran,
                'total_keringanan' => $keringanan,
                'sisa' => max(0.0, $totalTagihan - $totalPembayaran - $keringanan),
                'persentase' => $persentase,
            ];
        })->values();
    }

    /**
     * Sama persis dengan LaporanController::exportPelunasanTagihan.
     */
    public function exportExcel()
    {
        $semesterLabel = 'Semua semester';
        if ($this->filterSemester !== '') {
            $sem = Semester::whereNull('deleted_at')->find((int) $this->filterSemester);
            if ($sem) {
                $semesterLabel = $sem->kode ? ($sem->kode.' — '.$sem->nama) : $sem->nama;
            }
        }

        $prodiLabel = 'Semua prodi';
        if ($this->filterProdi !== '') {
            $prodiRow = Prodi::whereNull('deleted_at')->find((int) $this->filterProdi);
            if ($prodiRow) {
                $prodiLabel = $prodiRow->kode ? ($prodiRow->kode.' — '.$prodiRow->nama) : $prodiRow->nama;
            }
        }

        $rows = $this->mapRows($this->baseQuery()->get());

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pelunasan Tagihan');

        $sheet->setCellValue('A1', 'LAPORAN PELUNASAN TAGIHAN');
        $sheet->setCellValue('A2', 'Filter semester (tagihan): '.$semesterLabel);
        $sheet->setCellValue('A3', 'Filter program studi: '.$prodiLabel);
        $sheet->setCellValue('A4', 'Tanggal ekspor: '.now()->format('d/m/Y H:i:s'));
        if (trim($this->search) !== '') {
            $sheet->setCellValue('A5', 'Pencarian: '.trim($this->search));
        }

        $headerRow = trim($this->search) !== '' ? 7 : 6;
        $headers = [
            'No', 'NIM', 'Nama', 'Program Studi',
            'Total Tagihan (Rp)', 'Pembayaran Disetujui (Rp)', 'Keringanan Disetujui (Rp)',
            'Sisa Tunggakan (Rp)', 'Pencapaian (%)',
        ];
        $sheet->fromArray([$headers], null, 'A'.$headerRow);

        $dataStart = $headerRow + 1;
        $row = $dataStart;
        $no = 1;
        $sumTagihan = 0.0;
        $sumPembayaran = 0.0;
        $sumKeringanan = 0.0;

        foreach ($rows as $item) {
            $prodiText = '—';
            if ($item->prodi) {
                $prodiText = ($item->prodi->kode ? $item->prodi->kode.' · ' : '').$item->prodi->nama;
            }
            $sheet->setCellValue('A'.$row, $no);
            $sheet->setCellValue('B'.$row, $item->nim ?? '');
            $sheet->setCellValue('C'.$row, $item->nama);
            $sheet->setCellValue('D'.$row, $prodiText);
            $sheet->setCellValue('E'.$row, $item->total_tagihan);
            $sheet->setCellValue('F'.$row, $item->total_pembayaran);
            $sheet->setCellValue('G'.$row, $item->total_keringanan);
            $sheet->setCellValue('H'.$row, $item->sisa);
            $sheet->setCellValue('I'.$row, $item->persentase);

            $sumTagihan += $item->total_tagihan;
            $sumPembayaran += $item->total_pembayaran;
            $sumKeringanan += $item->total_keringanan;
            $row++;
            $no++;
        }

        // Persentase total dulu ditulis ke kolom sisa tunggakan, bukan ke kolom pencapaian.
        $totalPersen = $sumTagihan > 0 ? round(100.0 * ($sumPembayaran + $sumKeringanan) / $sumTagihan, 2) : 0.0;
        $sheet->setCellValue('C'.$row, 'TOTAL');
        $sheet->setCellValue('E'.$row, $sumTagihan);
        $sheet->setCellValue('F'.$row, $sumPembayaran);
        $sheet->setCellValue('G'.$row, $sumKeringanan);
        $sheet->setCellValue('H'.$row, max(0.0, $sumTagihan - $sumPembayaran - $sumKeringanan));
        $sheet->setCellValue('I'.$row, $totalPersen);

        $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14]]);
        $sheet->getStyle('A'.$headerRow.':I'.$headerRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('C'.$row.':I'.$row)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E7E6E6']],
        ]);

        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(16);
        $sheet->getColumnDimension('C')->setWidth(28);
        $sheet->getColumnDimension('D')->setWidth(36);
        $sheet->getColumnDimension('E')->setWidth(22);
        $sheet->getColumnDimension('F')->setWidth(26);
        $sheet->getColumnDimension('G')->setWidth(26);
        $sheet->getColumnDimension('H')->setWidth(20);
        $sheet->getColumnDimension('I')->setWidth(16);

        $lastDataRow = $row - 1;
        if ($lastDataRow >= $dataStart) {
            $sheet->getStyle('A'.$dataStart.':A'.$lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('E'.$dataStart.':I'.$lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        $filename = 'laporan_pelunasan_tagihan_'.now()->format('YmdHis').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function render()
    {
        $paginator = $this->baseQuery()->paginate($this->perPage);
        $paginator->setCollection($this->mapRows($paginator->getCollection()));

        return view('livewire.admin.pembayaran.laporan-pelunasan', [
            'rows' => $paginator,
        ])->extends('layouts.web');
    }
}
