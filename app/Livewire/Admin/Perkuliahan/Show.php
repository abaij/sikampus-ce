<?php

namespace App\Livewire\Admin\Perkuliahan;

use App\Livewire\Admin\Perkuliahan\Concerns\ForwardsIndexState;
use App\Models\Jadwal;
use App\Models\Kehadiran;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Perkuliahan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Show extends Component
{
    use ForwardsIndexState;

    public int $kelasId;

    public array $openJadwal = [];

    public bool $showRekapModal = false;

    public function mount(int $id): void
    {
        $this->kelasId = $id;
        $this->resolveBackUrl();

        $kelas = Kelas::findOrFail($id);
        $this->ensureAccess($kelas);
    }

    /**
     * Sama persis dengan KelasController::getDetailWithJadwal / KehadiranController::getRekapByKelasAdmin
     * — pengecekan scope prodi.
     */
    private function ensureAccess(Kelas $kelas): void
    {
        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $kelas->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke kelas ini.');
            }
        }
    }

    #[Computed]
    public function kelas(): Kelas
    {
        return Kelas::with([
            'kurikulumMatkul.matkul',
            'kurikulumMatkul.kurikulum',
            'prodi.jenjang',
            'semester',
            'dosenPic',
        ])->findOrFail($this->kelasId);
    }

    #[Computed]
    public function jadwalList()
    {
        return Jadwal::with(['jenisKuliah', 'ruangan', 'dosen.dosen'])
            ->where('id_kelas', $this->kelasId)
            ->whereNull('deleted_at')
            ->orderBy('hari')
            ->orderBy('jam_mulai')
            ->get();
    }

    #[Computed]
    public function jumlahMahasiswa(): int
    {
        return Krs::where('id_kelas', $this->kelasId)
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(DISTINCT id_mahasiswa) as jumlah')
            ->value('jumlah') ?? 0;
    }

    /**
     * Sama persis dengan KelasController::getDetailWithJadwal, dikelompokkan per slot jadwal
     * (bukan satu daftar datar) supaya bisa ditampilkan per accordion jadwal di view.
     */
    #[Computed]
    public function perkuliahanByJadwal()
    {
        $jadwalIds = $this->jadwalList->pluck('id')->all();
        if ($jadwalIds === []) {
            return collect();
        }

        $perkuliahan = Perkuliahan::whereIn('id_jadwal', $jadwalIds)
            ->whereNull('deleted_at')
            ->orderByRaw('waktu_mulai IS NULL')
            ->orderBy('waktu_mulai', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $perkuliahanIds = $perkuliahan->pluck('id')->all();
        $jumlahHadirPerPerkuliahan = [];
        if ($perkuliahanIds !== []) {
            $jumlahHadirPerPerkuliahan = Kehadiran::whereIn('id_perkuliahan', $perkuliahanIds)
                ->whereNull('deleted_at')
                ->where('status', 'hadir')
                ->selectRaw('id_perkuliahan, COUNT(DISTINCT id_mhs) as jumlah_hadir')
                ->groupBy('id_perkuliahan')
                ->pluck('jumlah_hadir', 'id_perkuliahan')
                ->all();
        }

        $jumlahMahasiswa = $this->jumlahMahasiswa;

        return $perkuliahan
            ->map(function (Perkuliahan $p) use ($jumlahHadirPerPerkuliahan, $jumlahMahasiswa) {
                $p->setAttribute('jumlah_hadir', $jumlahHadirPerPerkuliahan[$p->id] ?? 0);
                $p->setAttribute('jumlah_total', $jumlahMahasiswa);

                return $p;
            })
            ->groupBy('id_jadwal');
    }

    public function toggleJadwal(int $jadwalId): void
    {
        $this->openJadwal[$jadwalId] = ! ($this->openJadwal[$jadwalId] ?? false);
    }

    public function openRekap(): void
    {
        $this->showRekapModal = true;
    }

    public function closeRekap(): void
    {
        $this->showRekapModal = false;
    }

    /**
     * Sama persis dengan KehadiranController::perkuliahanSortedForRekap.
     *
     * @return array{Collection<int, Perkuliahan>, array<int, int>}
     */
    private function perkuliahanSortedForRekap(): array
    {
        $jadwalIds = $this->jadwalList->pluck('id')->all();
        if ($jadwalIds === []) {
            return [collect(), []];
        }

        $list = Perkuliahan::whereIn('id_jadwal', $jadwalIds)
            ->whereNull('deleted_at')
            ->get()
            ->sortBy([
                fn (Perkuliahan $p) => $p->waktu_mulai?->getTimestamp() ?? \PHP_INT_MAX,
                fn (Perkuliahan $p) => $p->id,
            ])
            ->values();

        $idToCol = [];
        foreach ($list as $i => $p) {
            $idToCol[(int) $p->id] = $i + 1;
        }

        return [$list, $idToCol];
    }

    /**
     * Sama persis dengan KehadiranController::getRekapByKelasAdmin.
     */
    #[Computed]
    public function rekap(): array
    {
        [$perkuliahanList, $perkuliahanIdToCol] = $this->perkuliahanSortedForRekap();

        $mahasiswaList = Krs::with('mahasiswa:id,nim,nama')
            ->where('krs.id_kelas', $this->kelasId)
            ->whereNull('krs.deleted_at')
            ->join('mahasiswa', 'krs.id_mahasiswa', '=', 'mahasiswa.id')
            ->whereNull('mahasiswa.deleted_at')
            ->orderBy('mahasiswa.nim')
            ->select('krs.*')
            ->get();

        $perkuliahanIds = $perkuliahanList->pluck('id')->all();
        $kehadiranByPerkuliahan = $perkuliahanIds === []
            ? collect()
            : Kehadiran::whereIn('id_perkuliahan', $perkuliahanIds)
                ->get()
                ->groupBy('id_perkuliahan')
                ->map(fn ($items) => $items->keyBy('id_mhs'));

        $mahasiswaData = $mahasiswaList->map(function (Krs $krs) use ($perkuliahanList, $kehadiranByPerkuliahan, $perkuliahanIdToCol) {
            $kehadiranPerPertemuan = [];
            foreach ($perkuliahanList as $p) {
                $col = $perkuliahanIdToCol[(int) $p->id] ?? null;
                if ($col === null) {
                    continue;
                }
                $kehadiran = $kehadiranByPerkuliahan[$p->id][$krs->id_mahasiswa] ?? null;
                $kehadiranPerPertemuan[$col] = $kehadiran ? [
                    'status' => $kehadiran->status,
                    'keterangan' => $kehadiran->keterangan,
                ] : null;
            }

            return (object) [
                'id_mahasiswa' => $krs->mahasiswa->id,
                'nim' => $krs->mahasiswa->nim,
                'nama' => $krs->mahasiswa->nama,
                'kehadiran' => $kehadiranPerPertemuan,
            ];
        })->values();

        return [
            'perkuliahan' => $perkuliahanList->map(fn (Perkuliahan $p) => (object) [
                'id' => $p->id,
                'pertemuan_ke' => $perkuliahanIdToCol[(int) $p->id] ?? null,
                'tanggal' => $p->waktu_mulai?->format('d M Y'),
                'materi' => $p->materi,
            ])->values(),
            'mahasiswa' => $mahasiswaData,
        ];
    }

    /**
     * Sama persis dengan KehadiranController::exportRekapByKelasAdmin.
     */
    public function exportKehadiran()
    {
        $kelas = $this->kelas;
        $rekap = $this->rekap;

        $kodeMatkul = ! empty($kelas->kurikulumMatkul?->kode_matkul)
            ? $kelas->kurikulumMatkul->kode_matkul
            : ($kelas->kurikulumMatkul?->matkul?->kode ?? '-');
        $namaMatkul = ! empty($kelas->kurikulumMatkul?->nama_matkul)
            ? $kelas->kurikulumMatkul->nama_matkul
            : ($kelas->kurikulumMatkul?->matkul?->nama ?? '-');
        $prodiNama = $kelas->prodi?->nama ?? '-';
        $jenjangNama = $kelas->prodi?->jenjang?->nama ?? '';
        $semesterNama = $kelas->semester?->nama ?? '-';
        $semesterKode = $kelas->semester?->kode ?? '-';

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Kehadiran');

        $row = 1;
        $sheet->setCellValue('A'.$row, 'REKAP KEHADIRAN MAHASISWA');
        $row++;
        $sheet->setCellValue('A'.$row, 'Mata Kuliah: '.$kodeMatkul.' - '.$namaMatkul);
        $row++;
        $sheet->setCellValue('A'.$row, 'Program Studi: '.$prodiNama.($jenjangNama ? ' ('.$jenjangNama.')' : ''));
        $row++;
        $sheet->setCellValue('A'.$row, 'Semester: '.$semesterNama.' ('.$semesterKode.')');
        $row++;
        $sheet->setCellValue('A'.$row, 'Tanggal Export: '.now()->format('d/m/Y H:i:s'));
        $row += 2;

        $headers = ['No', 'NIM', 'Nama'];
        for ($i = 1; $i <= 16; $i++) {
            $headers[] = $i;
        }
        $sheet->fromArray([$headers], null, 'A'.$row);

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $lastHeaderCol = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle('A'.$row.':'.$lastHeaderCol.$row)->applyFromArray($headerStyle);

        $statusLabel = [
            'hadir' => 'H',
            'izin' => 'I',
            'sakit' => 'S',
            'alfa' => 'A',
        ];

        $mahasiswaData = $rekap['mahasiswa'];

        $row++;
        $dataStartRow = $row;
        $no = 1;
        foreach ($mahasiswaData as $mhs) {
            $sheet->setCellValue('A'.$row, $no);
            $sheet->setCellValue('B'.$row, $mhs->nim);
            $sheet->setCellValue('C'.$row, $mhs->nama);

            for ($pertemuan = 1; $pertemuan <= 16; $pertemuan++) {
                $status = $mhs->kehadiran[$pertemuan]['status'] ?? null;
                $col = Coordinate::stringFromColumnIndex(3 + $pertemuan);
                $sheet->setCellValue($col.$row, $status ? ($statusLabel[strtolower($status)] ?? $status) : '');
            }
            $row++;
            $no++;
        }

        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(30);
        for ($i = 4; $i <= 19; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(6);
        }

        if ($mahasiswaData->isNotEmpty()) {
            $sheet->getStyle('A'.$dataStartRow.':A'.($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            for ($i = 4; $i <= 19; $i++) {
                $col = Coordinate::stringFromColumnIndex($i);
                $sheet->getStyle($col.$dataStartRow.':'.$col.($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }

        $filename = 'rekap_kehadiran_'.str_replace([' ', '/'], '_', $kodeMatkul).'_'.now()->format('YmdHis').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function render()
    {
        return view('livewire.admin.perkuliahan.show')->extends('layouts.web');
    }
}
