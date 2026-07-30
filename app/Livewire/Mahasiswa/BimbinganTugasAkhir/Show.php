<?php

namespace App\Livewire\Mahasiswa\BimbinganTugasAkhir;

use App\Models\Mahasiswa;
use App\Models\TugasAkhir;
use App\Models\TugasAkhirBimbingan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Show extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $mahasiswaId;

    #[Locked]
    public int $tugasAkhirId;

    public bool $showAddModal = false;

    public string $addTanggal = '';

    public string $addIdDosen = '';

    public string $addCatatan = '';

    /** @var TemporaryUploadedFile|null */
    public $addFile = null;

    public ?int $detailId = null;

    public string $detailCatatanDraft = '';

    /** @var TemporaryUploadedFile|null */
    public $detailFile = null;

    public function mount(int $id): void
    {
        $mahasiswa = Mahasiswa::where('id_user', Auth::id())->firstOrFail();
        $this->mahasiswaId = $mahasiswa->id;

        $tugasAkhir = TugasAkhir::find($id);
        abort_if($tugasAkhir === null || (int) $tugasAkhir->id_mahasiswa !== $this->mahasiswaId, 404, 'Tugas akhir tidak ditemukan.');
        abort_unless($tugasAkhir->status === 'approved', 403, 'Riwayat bimbingan hanya tersedia untuk tugas akhir yang sudah disetujui.');

        $this->tugasAkhirId = $id;
    }

    /**
     * Sama persis dengan TugasAkhirController::bimbinganRiwayatByTugasAkhirMahasiswa.
     */
    #[Computed]
    public function tugasAkhir(): TugasAkhir
    {
        return TugasAkhir::with(['semester', 'pembimbing.dosen'])->findOrFail($this->tugasAkhirId);
    }

    #[Computed]
    public function bimbinganRows()
    {
        return TugasAkhirBimbingan::with(['dosen', 'tugasAkhir.semester'])
            ->where('id_tugas_akhir', $this->tugasAkhirId)
            ->orderByDesc('tanggal_bimbingan')
            ->orderByDesc('id')
            ->get();
    }

    #[Computed]
    public function detailRow(): ?TugasAkhirBimbingan
    {
        if ($this->detailId === null) {
            return null;
        }

        return TugasAkhirBimbingan::where('id', $this->detailId)
            ->where('id_tugas_akhir', $this->tugasAkhirId)
            ->first();
    }

    public function openAddModal(): void
    {
        $this->resetValidation();
        $this->addTanggal = now()->format('Y-m-d');
        $this->addCatatan = '';
        $this->addFile = null;

        $pembimbing = $this->tugasAkhir->pembimbing;
        $this->addIdDosen = $pembimbing->count() === 1 ? (string) $pembimbing->first()->id_dosen : '';

        $this->showAddModal = true;
    }

    public function closeAddModal(): void
    {
        $this->showAddModal = false;
        $this->resetValidation();
    }

    /**
     * Sama persis dengan TugasAkhirController::storeBimbinganMahasiswa.
     */
    public function submitAdd(): void
    {
        $tugasAkhir = $this->tugasAkhir;

        $validated = $this->validate([
            'addTanggal' => ['required', 'date'],
            'addIdDosen' => ['required', 'integer', 'exists:dosen,id'],
            'addCatatan' => ['nullable', 'string'],
            'addFile' => ['nullable', 'file', 'max:10240'],
        ], [], [
            'addTanggal' => 'tanggal',
            'addIdDosen' => 'pembimbing',
        ]);

        $idDosen = (int) $validated['addIdDosen'];
        $isPembimbingTa = $tugasAkhir->pembimbing->contains('id_dosen', $idDosen);
        if (! $isPembimbingTa) {
            $this->addError('addIdDosen', 'Dosen yang dipilih bukan pembimbing pada tugas akhir ini.');

            return;
        }

        $tanggal = Carbon::parse($validated['addTanggal'])->toDateString();

        $dup = TugasAkhirBimbingan::where('id_tugas_akhir', $this->tugasAkhirId)
            ->where('id_dosen', $idDosen)
            ->whereDate('tanggal_bimbingan', $tanggal)
            ->exists();

        if ($dup) {
            $this->addError('addTanggal', 'Untuk tanggal dan pembimbing ini sudah ada entri bimbingan.');

            return;
        }

        $pathFile = $this->addFile ? $this->addFile->store('tugas-akhir-bimbingan', 'public') : null;

        $mahasiswa = Mahasiswa::find($this->mahasiswaId);
        $catatan = trim((string) $validated['addCatatan']) !== '' ? $validated['addCatatan'] : null;
        $createdBy = trim((string) ($mahasiswa->nama ?? '')) !== '' ? trim((string) $mahasiswa->nama) : (string) Auth::id();

        TugasAkhirBimbingan::create([
            'id_tugas_akhir' => $this->tugasAkhirId,
            'id_dosen' => $idDosen,
            'tanggal_bimbingan' => $tanggal,
            'catatan_dosen' => null,
            'catatan_mahasiswa' => $catatan,
            'file' => $pathFile,
            'created_by' => $createdBy,
        ]);

        $this->showAddModal = false;
        $this->resetValidation();
        unset($this->bimbinganRows);
        session()->flash('status', 'Entri bimbingan berhasil ditambahkan.');
    }

    public function openDetailModal(int $id): void
    {
        $this->detailId = $id;
        $this->detailCatatanDraft = (string) ($this->detailRow?->catatan_mahasiswa ?? '');
        $this->detailFile = null;
        $this->resetValidation();
    }

    public function closeDetailModal(): void
    {
        $this->detailId = null;
        $this->detailCatatanDraft = '';
        $this->detailFile = null;
        $this->resetValidation();
    }

    /**
     * Sama persis dengan TugasAkhirController::updateBimbinganCatatanMahasiswa.
     */
    public function saveDetail(): void
    {
        $row = $this->detailRow;
        abort_if($row === null, 404, 'Entri bimbingan tidak ditemukan.');

        $validated = $this->validate([
            'detailCatatanDraft' => ['nullable', 'string'],
            'detailFile' => ['nullable', 'file', 'max:10240'],
        ]);

        $catatan = trim((string) $validated['detailCatatanDraft']) !== '' ? $validated['detailCatatanDraft'] : null;

        $pathFile = $row->file;
        if ($this->detailFile) {
            if ($row->file) {
                Storage::disk('public')->delete($row->file);
            }
            $pathFile = $this->detailFile->store('tugas-akhir-bimbingan', 'public');
        }

        $row->update([
            'catatan_mahasiswa' => $catatan,
            'file' => $pathFile,
        ]);

        $this->closeDetailModal();
        unset($this->bimbinganRows);
        session()->flash('status', 'Catatan dan lampiran disimpan.');
    }

    public function render()
    {
        return view('livewire.mahasiswa.bimbingan-tugas-akhir.show')->extends('layouts.mahasiswa');
    }
}
