<?php

namespace App\Livewire\Admin\Wisuda;

use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\Wisuda;
use App\Models\WisudaMahasiswa;
use App\Models\Yudisium;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Show extends Component
{
    private const PESERTA_STATUSES = ['pending', 'acc', 'approved', 'rejected'];

    public int $wisudaId;

    // ---- Tambah peserta ----
    public bool $showTambahModal = false;

    public string $calonSearch = '';

    // Terikat <x-searchable-select> di modal; string supaya opsi kosong aman.
    public string $calonFilterProdi = '';

    public ?int $selectedMahasiswaId = null;

    public string $selectedMahasiswaLabel = '';

    // ---- Form peserta (dipakai modal tambah & ubah) ----
    public ?int $editingPesertaId = null;

    public string $no_sk_wisuda = '';

    public string $tanggal_sk_wisuda = '';

    public string $file_sk_wisuda = '';

    public string $foto = '';

    public string $pesertaStatus = 'pending';

    public ?int $confirmingPesertaDeleteId = null;

    public function mount(int $id): void
    {
        $this->wisudaId = $id;
        Wisuda::findOrFail($id);
    }

    public function pesertaStatusOptions(): array
    {
        return [
            'pending' => 'Menunggu',
            'acc' => 'Disetujui (acc)',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
        ];
    }

    private function actor(): string
    {
        $user = Auth::user();

        return $user ? ($user->name ?? (string) ($user->email ?? $user->id)) : 'system';
    }

    /** @return array<int>|null null = tanpa batasan scope */
    private function allowedProdiIds(): ?array
    {
        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            return $user->getAllowedProdiIds();
        }

        return null;
    }

    /**
     * Sama persis dengan pemeriksaan scope di WisudaController::showPeserta/updatePeserta/destroyPeserta.
     */
    private function ensureMahasiswaInScope(?Mahasiswa $mahasiswa): void
    {
        $allowedProdiIds = $this->allowedProdiIds();
        if ($allowedProdiIds !== null && $mahasiswa && ! in_array((int) $mahasiswa->id_prodi, $allowedProdiIds, true)) {
            abort(403, 'Anda tidak memiliki akses ke data ini.');
        }
    }

    #[Computed]
    public function wisuda(): Wisuda
    {
        return Wisuda::withCount('wisudaMahasiswa as jumlah_mahasiswa')->findOrFail($this->wisudaId);
    }

    /**
     * Sama persis dengan WisudaController::show — peserta diurutkan sesuai id.
     */
    #[Computed]
    public function pesertaList()
    {
        return WisudaMahasiswa::query()
            ->where('id_wisuda', $this->wisudaId)
            ->with(['mahasiswa.prodi.jenjang'])
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function prodiOptions()
    {
        $query = Prodi::query()->whereNull('deleted_at');

        $allowedProdiIds = $this->allowedProdiIds();
        if ($allowedProdiIds !== null) {
            $query->whereIn('id', $allowedProdiIds);
        }

        return $query->orderBy('nama')->get(['id', 'nama'])
            ->map(fn (Prodi $p) => (object) ['id' => $p->id, 'label' => $p->nama]);
    }

    /**
     * Sama persis dengan WisudaController::eligibleMahasiswa — sudah punya yudisium dan
     * belum terdaftar di wisuda ini.
     */
    #[Computed]
    public function calonPeserta()
    {
        $query = Mahasiswa::query()
            ->whereNull('mahasiswa.deleted_at')
            ->whereExists(function ($q): void {
                $q->selectRaw('1')
                    ->from('yudisium')
                    ->whereColumn('yudisium.id_mahasiswa', 'mahasiswa.id')
                    ->whereNull('yudisium.deleted_at');
            })
            ->whereNotExists(function ($q): void {
                $q->selectRaw('1')
                    ->from('wisuda_mahasiswa')
                    ->whereColumn('wisuda_mahasiswa.id_mahasiswa', 'mahasiswa.id')
                    ->where('wisuda_mahasiswa.id_wisuda', $this->wisudaId)
                    ->whereNull('wisuda_mahasiswa.deleted_at');
            })
            ->with(['prodi:id,nama,kode']);

        $allowedProdiIds = $this->allowedProdiIds();
        $filterProdi = $this->calonFilterProdi;
        if ($allowedProdiIds !== null) {
            $query->whereIn('mahasiswa.id_prodi', $allowedProdiIds);
            if ($filterProdi !== '' && ! in_array((int) $filterProdi, $allowedProdiIds, true)) {
                $filterProdi = '';
            }
        }

        if ($filterProdi !== '') {
            $query->where('mahasiswa.id_prodi', (int) $filterProdi);
        }

        if ($this->calonSearch !== '') {
            $term = '%'.$this->calonSearch.'%';
            $query->where(function ($q) use ($term): void {
                $q->where('mahasiswa.nama', 'like', $term)
                    ->orWhere('mahasiswa.nim', 'like', $term);
            });
        }

        return $query->orderBy('mahasiswa.nama')->limit(25)->get();
    }

    // ---------- Tambah peserta ----------

    public function openTambahModal(): void
    {
        $this->resetValidation();
        $this->resetPesertaForm();
        $this->calonSearch = '';
        $this->calonFilterProdi = '';
        $this->showTambahModal = true;
    }

    public function closeTambahModal(): void
    {
        $this->showTambahModal = false;
    }

    private function resetPesertaForm(): void
    {
        $this->editingPesertaId = null;
        $this->selectedMahasiswaId = null;
        $this->selectedMahasiswaLabel = '';
        $this->no_sk_wisuda = '';
        $this->tanggal_sk_wisuda = '';
        $this->file_sk_wisuda = '';
        $this->foto = '';
        $this->pesertaStatus = 'pending';
    }

    public function selectMahasiswa(int $id): void
    {
        $mahasiswa = Mahasiswa::findOrFail($id);
        $this->ensureMahasiswaInScope($mahasiswa);

        $this->selectedMahasiswaId = $mahasiswa->id;
        $this->selectedMahasiswaLabel = trim(($mahasiswa->nim ?? '').' — '.$mahasiswa->nama);
        $this->resetValidation('selectedMahasiswaId');
    }

    public function clearMahasiswa(): void
    {
        $this->selectedMahasiswaId = null;
        $this->selectedMahasiswaLabel = '';
    }

    /**
     * Sama persis dengan WisudaController::storePeserta, termasuk memulihkan baris
     * yang sebelumnya di-soft delete.
     */
    public function savePeserta(): void
    {
        $this->validate([
            'selectedMahasiswaId' => ['required', 'integer', 'exists:mahasiswa,id'],
            'no_sk_wisuda' => ['nullable', 'string', 'max:255'],
            'tanggal_sk_wisuda' => ['nullable', 'string', 'max:255'],
            'file_sk_wisuda' => ['nullable', 'string', 'max:1000'],
            'foto' => ['nullable', 'string', 'max:1000'],
            'pesertaStatus' => ['nullable', 'string', 'in:'.implode(',', self::PESERTA_STATUSES)],
        ], [], [
            'selectedMahasiswaId' => 'mahasiswa',
            'pesertaStatus' => 'status',
        ]);

        $idMahasiswa = (int) $this->selectedMahasiswaId;

        $mahasiswa = Mahasiswa::query()->whereKey($idMahasiswa)->whereNull('deleted_at')->first();
        if (! $mahasiswa) {
            $this->addError('selectedMahasiswaId', 'Mahasiswa tidak ditemukan.');

            return;
        }

        $this->ensureMahasiswaInScope($mahasiswa);

        $hasYudisium = Yudisium::query()
            ->where('id_mahasiswa', $idMahasiswa)
            ->whereNull('deleted_at')
            ->exists();
        if (! $hasYudisium) {
            $this->addError('selectedMahasiswaId', 'Mahasiswa harus memiliki data yudisium terlebih dahulu.');

            return;
        }

        $active = WisudaMahasiswa::query()
            ->where('id_wisuda', $this->wisudaId)
            ->where('id_mahasiswa', $idMahasiswa)
            ->exists();
        if ($active) {
            $this->addError('selectedMahasiswaId', 'Mahasiswa sudah terdaftar pada wisuda ini.');

            return;
        }

        $actor = $this->actor();
        $payload = [
            'id_wisuda' => $this->wisudaId,
            'id_mahasiswa' => $idMahasiswa,
            'no_sk_wisuda' => $this->nullIfBlank($this->no_sk_wisuda),
            'tanggal_sk_wisuda' => $this->nullIfBlank($this->tanggal_sk_wisuda),
            'file_sk_wisuda' => $this->nullIfBlank($this->file_sk_wisuda),
            'foto' => $this->nullIfBlank($this->foto),
            'status' => $this->pesertaStatus !== '' ? $this->pesertaStatus : 'pending',
            'updated_by' => $actor,
        ];

        $trashed = WisudaMahasiswa::onlyTrashed()
            ->where('id_wisuda', $this->wisudaId)
            ->where('id_mahasiswa', $idMahasiswa)
            ->first();

        if ($trashed) {
            $trashed->restore();
            $trashed->deleted_by = null;
            $trashed->fill($payload);
            $trashed->save();
        } else {
            $payload['created_by'] = $actor;
            WisudaMahasiswa::create($payload);
        }

        $this->showTambahModal = false;
        $this->resetPesertaForm();
        unset($this->pesertaList, $this->wisuda, $this->calonPeserta);
        session()->flash('status', 'Mahasiswa berhasil ditambahkan sebagai peserta wisuda.');
    }

    private function nullIfBlank(string $value): ?string
    {
        return $value !== '' ? $value : null;
    }

    // ---------- Ubah peserta ----------

    public function openEditPeserta(int $id): void
    {
        $this->resetValidation();

        $peserta = WisudaMahasiswa::with('mahasiswa')->findOrFail($id);
        $this->assertPesertaBelongsToWisuda($peserta);
        $this->ensureMahasiswaInScope($peserta->mahasiswa);

        $this->editingPesertaId = $peserta->id;
        $this->selectedMahasiswaId = $peserta->id_mahasiswa;
        $this->selectedMahasiswaLabel = trim(($peserta->mahasiswa->nim ?? '').' — '.($peserta->mahasiswa->nama ?? ''));
        $this->no_sk_wisuda = (string) $peserta->no_sk_wisuda;
        $this->tanggal_sk_wisuda = (string) $peserta->tanggal_sk_wisuda;
        $this->file_sk_wisuda = (string) $peserta->file_sk_wisuda;
        $this->foto = (string) $peserta->foto;
        $this->pesertaStatus = $peserta->status ?? 'pending';
    }

    public function closeEditPeserta(): void
    {
        $this->editingPesertaId = null;
    }

    private function assertPesertaBelongsToWisuda(WisudaMahasiswa $peserta): void
    {
        if ((int) $peserta->id_wisuda !== $this->wisudaId) {
            abort(404, 'Data peserta tidak ditemukan untuk wisuda ini.');
        }
    }

    /**
     * Sama persis dengan WisudaController::updatePeserta.
     */
    public function saveEditPeserta(): void
    {
        if (! $this->editingPesertaId) {
            return;
        }

        $this->validate([
            'no_sk_wisuda' => ['nullable', 'string', 'max:255'],
            'tanggal_sk_wisuda' => ['nullable', 'string', 'max:255'],
            'file_sk_wisuda' => ['nullable', 'string', 'max:1000'],
            'foto' => ['nullable', 'string', 'max:1000'],
            'pesertaStatus' => ['nullable', 'string', 'in:'.implode(',', self::PESERTA_STATUSES)],
        ], [], ['pesertaStatus' => 'status']);

        $peserta = WisudaMahasiswa::with('mahasiswa')->findOrFail($this->editingPesertaId);
        $this->assertPesertaBelongsToWisuda($peserta);
        $this->ensureMahasiswaInScope($peserta->mahasiswa);

        $peserta->fill([
            'no_sk_wisuda' => $this->nullIfBlank($this->no_sk_wisuda),
            'tanggal_sk_wisuda' => $this->nullIfBlank($this->tanggal_sk_wisuda),
            'file_sk_wisuda' => $this->nullIfBlank($this->file_sk_wisuda),
            'foto' => $this->nullIfBlank($this->foto),
            'status' => $this->pesertaStatus !== '' ? $this->pesertaStatus : 'pending',
            'updated_by' => $this->actor(),
        ]);
        $peserta->save();

        $this->editingPesertaId = null;
        $this->resetPesertaForm();
        unset($this->pesertaList);
        session()->flash('status', 'Data peserta wisuda diperbarui.');
    }

    // ---------- Hapus peserta ----------

    public function confirmDeletePeserta(int $id): void
    {
        $this->confirmingPesertaDeleteId = $id;
    }

    public function cancelDeletePeserta(): void
    {
        $this->confirmingPesertaDeleteId = null;
    }

    /**
     * Sama persis dengan WisudaController::destroyPeserta.
     */
    public function deletePeserta(): void
    {
        if (! $this->confirmingPesertaDeleteId) {
            return;
        }

        $peserta = WisudaMahasiswa::with('mahasiswa')->findOrFail($this->confirmingPesertaDeleteId);
        $this->assertPesertaBelongsToWisuda($peserta);
        $this->ensureMahasiswaInScope($peserta->mahasiswa);

        $peserta->deleted_by = $this->actor();
        $peserta->save();
        $peserta->delete();

        $this->confirmingPesertaDeleteId = null;
        unset($this->pesertaList, $this->wisuda, $this->calonPeserta);
        session()->flash('status', 'Mahasiswa dihapus dari daftar peserta wisuda.');
    }

    // ---------- Export ----------

    /**
     * Sama persis dengan WisudaController::pesertaWisudaRowsForExport.
     */
    private function pesertaRowsForExport()
    {
        $allowedProdiIds = $this->allowedProdiIds();

        return WisudaMahasiswa::where('id_wisuda', $this->wisudaId)
            ->when($allowedProdiIds !== null, function ($q) use ($allowedProdiIds) {
                $q->whereHas('mahasiswa', function ($q2) use ($allowedProdiIds) {
                    $q2->whereIn('id_prodi', $allowedProdiIds);
                });
            })
            ->with(['mahasiswa.prodi'])
            ->get()
            ->sortBy(fn (WisudaMahasiswa $p) => $p->mahasiswa?->nama ?? '')
            ->values();
    }

    /**
     * Sama persis dengan WisudaController::exportPesertaPdf.
     */
    public function exportPdf()
    {
        $wisuda = $this->wisuda;
        $peserta = $this->pesertaRowsForExport();

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; }
        .title { text-align: center; font-size: 13pt; font-weight: bold; margin-bottom: 4px; }
        .subtitle { text-align: center; font-size: 10pt; margin-bottom: 12px; }
        table.peserta { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.peserta th { background-color: #334155; color: #fff; padding: 6px 4px; border: 1px solid #000; font-size: 8pt; text-align: center; }
        table.peserta td { padding: 4px; border: 1px solid #000; font-size: 8pt; vertical-align: top; }
        table.peserta td.num { text-align: center; width: 30px; }
        .footer { margin-top: 14px; font-size: 8pt; text-align: right; color: #444; }
        </style></head><body>';

        $html .= '<div class="title">DAFTAR PESERTA WISUDA</div>';
        $html .= '<div class="subtitle">'.htmlspecialchars($wisuda->nama).' — '
            .htmlspecialchars($wisuda->tanggal_wisuda?->format('d/m/Y') ?? '—').'</div>';

        $html .= '<table class="peserta"><thead><tr>
            <th style="width:30px">No</th>
            <th>NIM</th>
            <th>Nama</th>
            <th>Program Studi</th>
            <th>No. SK Wisuda</th>
        </tr></thead><tbody>';

        if ($peserta->isEmpty()) {
            $html .= '<tr><td colspan="5" style="text-align:center">Belum ada peserta terdaftar.</td></tr>';
        } else {
            $no = 1;
            foreach ($peserta as $p) {
                $m = $p->mahasiswa;
                $prodiText = $m?->prodi ? ($m->prodi->nama.($m->prodi->kode ? ' ('.$m->prodi->kode.')' : '')) : '—';
                $html .= '<tr>'
                    .'<td class="num">'.$no.'</td>'
                    .'<td>'.htmlspecialchars($m?->nim ?? '—').'</td>'
                    .'<td>'.htmlspecialchars($m?->nama ?? '—').'</td>'
                    .'<td>'.htmlspecialchars($prodiText).'</td>'
                    .'<td>'.htmlspecialchars($p->no_sk_wisuda ?? '—').'</td>'
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
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'peserta_wisuda_'.$wisuda->id.'_'.date('Y-m-d_His').'.pdf';

        return response()->streamDownload(function () use ($dompdf) {
            echo $dompdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Sama persis dengan WisudaController::exportPesertaExcel.
     */
    public function exportExcel()
    {
        $wisuda = $this->wisuda;
        $peserta = $this->pesertaRowsForExport();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Peserta Wisuda');

        $headers = ['No', 'NIM', 'Nama', 'Program Studi', 'No. SK Wisuda'];
        $sheet->fromArray([$headers], null, 'A1');
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $rowNum = 2;
        $no = 1;
        foreach ($peserta as $p) {
            $m = $p->mahasiswa;
            $prodiText = $m?->prodi ? ($m->prodi->nama.($m->prodi->kode ? ' ('.$m->prodi->kode.')' : '')) : '—';
            $sheet->fromArray([[
                $no,
                $m?->nim ?? '—',
                $m?->nama ?? '—',
                $prodiText,
                $p->no_sk_wisuda ?? '—',
            ]], null, 'A'.$rowNum);
            $rowNum++;
            $no++;
        }

        foreach (['A', 'B', 'C', 'D', 'E'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'peserta_wisuda_'.$wisuda->id.'_'.date('Y-m-d_His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function render()
    {
        return view('livewire.admin.wisuda.show')->extends('layouts.web');
    }
}
