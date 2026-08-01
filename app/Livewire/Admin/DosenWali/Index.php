<?php

namespace App\Livewire\Admin\DosenWali;

use App\Models\Dosen;
use App\Models\DosenWali;
use App\Models\Mahasiswa;
use App\Support\PanelAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\IOFactory;

class Index extends Component
{
    use WithFileUploads, WithPagination;

    // #[Url] supaya state ini bisa dibaca ulang lewat query string ketika user kembali dari
    // halaman detail (lihat DosenWali\Concerns\ForwardsIndexState::resolveBackToIndexUrl).
    #[Url(as: 'search')]
    public string $search = '';

    public int $perPage = 10;

    public bool $showKuotaModal = false;

    public string $kuotaInput = '';

    public $importFile = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openKuotaModal(): void
    {
        // Tombol pemicu Set Kuota dan input import disembunyikan di Blade untuk user tanpa hak
        // ubah, tapi method Livewire tetap bisa dipanggil langsung lewat request yang
        // dipalsukan — pengecekan di sini (dan di applyKuota/updatedImportFile) adalah otoritas
        // sebenarnya, bukan sekadar UI.
        abort_unless(PanelAccess::can(Auth::user(), 'dosen wali', 'update'), 403, 'Anda tidak memiliki hak untuk mengubah data dosen wali.');

        $this->resetValidation();
        $this->kuotaInput = '';
        $this->showKuotaModal = true;
    }

    public function closeKuotaModal(): void
    {
        $this->showKuotaModal = false;
    }

    /**
     * Sama dengan DosenWaliController::setKuotaBulk — hanya menerapkan ke dosen yang
     * belum punya kuota (null atau 0), dosen yang sudah punya kuota tidak diubah.
     */
    public function applyKuota(): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'dosen wali', 'update'), 403, 'Anda tidak memiliki hak untuk mengubah data dosen wali.');

        $this->validate([
            'kuotaInput' => ['required', 'integer', 'min:0'],
        ], [], ['kuotaInput' => 'kuota']);

        $kuota = (int) $this->kuotaInput;

        $updated = Dosen::where(function ($q) {
            $q->whereNull('kuota_bimbingan_akademik')
                ->orWhere('kuota_bimbingan_akademik', 0);
        })->update(['kuota_bimbingan_akademik' => $kuota]);

        $this->showKuotaModal = false;
        session()->flash('status', "Kuota bimbingan akademik ({$kuota}) diterapkan untuk {$updated} dosen yang belum memiliki kuota.");
    }

    /**
     * Sama persis dengan DosenWaliController::import — kolom (Kode Dosen, NIM), dosen dicari
     * lewat kode_dosen, mahasiswa lewat nim. Dipicu otomatis begitu file terpilih (mirip onChange
     * di halaman frontend), bukan lewat tombol submit terpisah.
     */
    public function updatedImportFile(): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'dosen wali', 'update'), 403, 'Anda tidak memiliki hak untuk mengubah data dosen wali.');

        session()->forget(['import_errors']);

        $this->validate([
            'importFile' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $spreadsheet = IOFactory::load($this->importFile->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray();

        if (count($rows) < 2) {
            session()->flash('import_error', 'File Excel kosong atau tidak valid. Minimal harus ada baris header dan satu baris data.');
            $this->reset('importFile');

            return;
        }

        array_shift($rows);

        $user = Auth::user();
        $allowedProdiIds = ($user && $user->hasScopeRestriction()) ? $user->getAllowedProdiIds() : null;

        $errors = [];
        $successCount = 0;
        $skipCount = 0;

        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 2;

            if (empty(array_filter($row))) {
                continue;
            }

            $kodeDosen = isset($row[0]) ? trim((string) $row[0]) : '';
            $nim = isset($row[1]) ? trim((string) $row[1]) : '';

            if ($kodeDosen === '' || $nim === '') {
                $errors[] = "Baris {$rowNumber}: Kode Dosen dan NIM wajib diisi.";
                $skipCount++;

                continue;
            }

            $dosen = Dosen::where('kode_dosen', $kodeDosen)->first();
            if (! $dosen) {
                $errors[] = "Baris {$rowNumber}: Kode Dosen '{$kodeDosen}' tidak ditemukan.";
                $skipCount++;

                continue;
            }

            $mahasiswa = Mahasiswa::where('nim', $nim)->first();
            if (! $mahasiswa) {
                $errors[] = "Baris {$rowNumber}: NIM '{$nim}' tidak ditemukan.";
                $skipCount++;

                continue;
            }

            if ($allowedProdiIds !== null && ! in_array((int) $mahasiswa->id_prodi, $allowedProdiIds, true)) {
                $errors[] = "Baris {$rowNumber}: Anda tidak memiliki akses ke mahasiswa dengan NIM '{$nim}'.";
                $skipCount++;

                continue;
            }

            $existing = DosenWali::where('id_dosen', $dosen->id)
                ->where('id_mahasiswa', $mahasiswa->id)
                ->first();

            if ($existing) {
                $errors[] = "Baris {$rowNumber}: Pasangan Kode Dosen '{$kodeDosen}' dan NIM '{$nim}' sudah terdaftar.";
                $skipCount++;

                continue;
            }

            DosenWali::create([
                'id_dosen' => $dosen->id,
                'id_mahasiswa' => $mahasiswa->id,
                'status' => 'active',
            ]);
            $successCount++;
        }

        $this->reset('importFile');

        session()->flash('status', "Import selesai. Berhasil: {$successCount}, dilewati: {$skipCount}.");
        if ($errors !== []) {
            session()->flash('import_errors', $errors);
        }
    }

    /**
     * Sama dengan DosenController::index (withCount mahasiswaBimbingan, with prodiAsKaprodi),
     * dipersempit ke kolom yang relevan untuk halaman Dosen Wali.
     */
    public function render()
    {
        $query = Dosen::query()
            ->withCount('mahasiswaBimbingan')
            ->with('prodiAsKaprodi');

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('kode_dosen', 'like', "%{$this->search}%");
            });
        }

        $dosenList = $query->orderBy('nama')->paginate($this->perPage);

        // Diselipkan ke link "Lihat" supaya tombol Kembali di halaman detail bisa mendarat di
        // halaman/pencarian yang sama persis — lihat DosenWali\Concerns\ForwardsIndexState.
        $returnParams = array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'page' => $dosenList->currentPage() > 1 ? $dosenList->currentPage() : null,
        ], fn ($value) => $value !== null);

        return view('livewire.admin.dosen-wali.index', [
            'dosenList' => $dosenList,
            'returnQuery' => http_build_query($returnParams),
        ])->extends('layouts.web');
    }
}
