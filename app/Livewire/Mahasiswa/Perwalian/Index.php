<?php

namespace App\Livewire\Mahasiswa\Perwalian;

use App\Models\DosenWali;
use App\Models\DosenWaliBimbingan;
use App\Models\Mahasiswa;
use App\Models\Semester;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Index extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $mahasiswaId;

    public string $filterSemester = '';

    public bool $showTambahModal = false;

    public string $tambahSemester = '';

    public string $tambahTanggal = '';

    public string $tambahCatatan = '';

    /** @var TemporaryUploadedFile|null */
    public $tambahFile = null;

    public ?int $detailId = null;

    public string $detailCatatanDraft = '';

    public bool $detailValidasiChecked = false;

    public function mount(): void
    {
        $mahasiswa = Mahasiswa::where('id_user', Auth::id())->firstOrFail();
        $this->mahasiswaId = $mahasiswa->id;

        $active = Semester::where('is_active', true)->first();
        $this->filterSemester = $active
            ? (string) $active->id
            : (string) (Semester::orderByDesc('kode')->value('id') ?? '');
    }

    /**
     * Sama persis dengan SemesterController::getList.
     */
    #[Computed]
    public function semesterOptions(): array
    {
        return Semester::orderByDesc('kode')
            ->get(['id', 'kode', 'nama', 'is_active'])
            ->mapWithKeys(fn (Semester $s) => [
                $s->id => $s->kode.' · '.$s->nama.($s->is_active ? ' (aktif)' : ''),
            ])
            ->all();
    }

    #[Computed]
    public function dosenWali(): ?DosenWali
    {
        return DosenWali::with('dosen:id,nama,kode_dosen,nidn')
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Sama persis dengan DosenWaliController::getBimbinganAkademikMahasiswa.
     */
    #[Computed]
    public function rows()
    {
        $dosenWali = $this->dosenWali;
        if (! $dosenWali) {
            return collect();
        }

        $query = $dosenWali->bimbingan()->with('semester');
        if ($this->filterSemester !== '') {
            $query->where('id_semester', (int) $this->filterSemester);
        }

        return $query->orderByDesc('tanggal_bimbingan')->orderByDesc('id')->get();
    }

    #[Computed]
    public function detailRow(): ?DosenWaliBimbingan
    {
        if ($this->detailId === null) {
            return null;
        }

        $dosenWali = $this->dosenWali;
        if (! $dosenWali) {
            return null;
        }

        return DosenWaliBimbingan::where('id', $this->detailId)
            ->where('id_dosen_wali', $dosenWali->id)
            ->whereNull('deleted_at')
            ->first();
    }

    public function openTambah(): void
    {
        $this->resetValidation();
        $this->tambahSemester = $this->filterSemester !== '' ? $this->filterSemester : (string) array_key_first($this->semesterOptions);
        $this->tambahTanggal = now()->format('Y-m-d');
        $this->tambahCatatan = '';
        $this->tambahFile = null;
        $this->showTambahModal = true;
    }

    public function closeTambah(): void
    {
        $this->showTambahModal = false;
        $this->resetValidation();
    }

    /**
     * Sama persis dengan DosenWaliBimbinganController::storeForBimbinganAkademikMahasiswa.
     */
    public function submitTambah(): void
    {
        $dosenWali = $this->dosenWali;
        abort_unless($dosenWali, 404, 'Anda tidak memiliki dosen wali aktif.');

        $validated = $this->validate([
            'tambahSemester' => ['required', 'integer', 'exists:semester,id'],
            'tambahCatatan' => ['required', 'string'],
            'tambahTanggal' => ['nullable', 'date'],
            'tambahFile' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,jpg,jpeg,png'],
        ], [], [
            'tambahSemester' => 'semester',
            'tambahCatatan' => 'catatan mahasiswa',
        ]);

        if (trim($validated['tambahCatatan']) === '') {
            $this->addError('tambahCatatan', 'Catatan tidak boleh kosong.');

            return;
        }

        $path = $this->tambahFile ? $this->tambahFile->store('dosen_wali_bimbingan', 'public') : null;

        $dosenWali->bimbingan()->create([
            'id_semester' => (int) $validated['tambahSemester'],
            'catatan_dosen' => null,
            'catatan_mhs' => $validated['tambahCatatan'],
            'tanggal_bimbingan' => $validated['tambahTanggal'] !== '' ? $validated['tambahTanggal'] : null,
            'waktu_validasi_dosen' => null,
            'waktu_validasi_mhs' => null,
            'file' => $path,
        ]);

        if ((string) $validated['tambahSemester'] !== $this->filterSemester) {
            $this->filterSemester = (string) $validated['tambahSemester'];
        }

        $this->showTambahModal = false;
        $this->resetValidation();
        unset($this->rows);
        session()->flash('status', 'Catatan bimbingan berhasil disimpan.');
    }

    public function openDetail(int $id): void
    {
        $this->detailId = $id;
        $this->detailCatatanDraft = (string) ($this->detailRow?->catatan_mhs ?? '');
        $this->detailValidasiChecked = false;
        $this->resetValidation();
    }

    public function closeDetail(): void
    {
        $this->detailId = null;
        $this->detailCatatanDraft = '';
        $this->detailValidasiChecked = false;
        $this->resetValidation();
    }

    /**
     * Sama persis dengan DosenWaliBimbinganController::updateForBimbinganAkademikMahasiswa.
     */
    public function saveDetail(): void
    {
        $row = $this->detailRow;
        abort_if($row === null, 404, 'Data bimbingan tidak ditemukan.');

        $norm = static fn (?string $v): string => trim((string) ($v ?? ''));

        if ($row->waktu_validasi_mhs) {
            if ($norm($this->detailCatatanDraft) !== $norm($row->catatan_mhs)) {
                $this->addError('detailCatatanDraft', 'Catatan tidak dapat diubah setelah Anda memvalidasi entri ini.');

                return;
            }
        } else {
            $row->catatan_mhs = $this->detailCatatanDraft;

            if ($this->detailValidasiChecked) {
                if ($norm($row->catatan_mhs) === '') {
                    $this->addError('detailCatatanDraft', 'Isi catatan mahasiswa terlebih dahulu sebelum memvalidasi.');

                    return;
                }
                $row->waktu_validasi_mhs = now();
            }
        }

        $row->save();

        $this->detailValidasiChecked = false;
        unset($this->rows, $this->detailRow);
    }

    public function render()
    {
        return view('livewire.mahasiswa.perwalian.index')->extends('layouts.mahasiswa');
    }
}
