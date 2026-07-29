<?php

namespace App\Livewire\Dosen\TugasAkhir;

use App\Models\Dosen;
use App\Models\TugasAkhir;
use App\Models\TugasAkhirBimbingan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Show extends Component
{
    use WithFileUploads;

    // Locked: tugasAkhirId/dosenId dipakai langsung untuk cek akses (pembimbing tugas akhir ini)
    // dan sebagai penanda kepemilikan riwayat bimbingan — properti publik biasa bisa di-override
    // client lewat request Livewire.
    #[Locked]
    public int $tugasAkhirId;

    #[Locked]
    public int $dosenId;

    public bool $showBimbinganModal = false;

    public string $form_tanggal_bimbingan = '';

    public string $form_catatan_dosen = '';

    /** @var TemporaryUploadedFile|null */
    public $form_file = null;

    public function mount(int $id): void
    {
        $this->tugasAkhirId = $id;

        $dosen = Dosen::where('id_user', Auth::id())->firstOrFail();
        $this->dosenId = $dosen->id;

        $this->ensureAccess($this->tugasAkhir);
    }

    /**
     * Sama persis dengan TugasAkhirController::showTugasAkhirDetailDosen /
     * storeTugasAkhirBimbinganDosen — dosen harus terdaftar sebagai pembimbing dan judul tugas
     * akhir sudah disetujui.
     */
    private function ensureAccess(TugasAkhir $tugasAkhir): void
    {
        $isPembimbing = $tugasAkhir->pembimbing()->where('id_dosen', $this->dosenId)->exists();
        abort_unless($isPembimbing, 403, 'Anda bukan pembimbing pada tugas akhir ini.');
        abort_unless($tugasAkhir->status === 'approved', 403, 'Tugas akhir tidak tersedia untuk tampilan pembimbing (judul belum disetujui).');
    }

    #[Computed]
    public function tugasAkhir(): TugasAkhir
    {
        $row = TugasAkhir::with(['mahasiswa.prodi', 'semester', 'pembimbing.dosen'])->find($this->tugasAkhirId);
        abort_unless($row, 404, 'Tugas akhir tidak ditemukan.');

        return $row;
    }

    /**
     * @return array<int, TugasAkhirBimbingan>
     */
    #[Computed]
    public function riwayatBimbingan(): array
    {
        return TugasAkhirBimbingan::where('id_tugas_akhir', $this->tugasAkhirId)
            ->where('id_dosen', $this->dosenId)
            ->orderByDesc('tanggal_bimbingan')
            ->orderByDesc('id')
            ->get()
            ->values()
            ->all();
    }

    public function openBimbinganModal(): void
    {
        $this->resetValidation();
        $this->form_tanggal_bimbingan = now()->toDateString();
        $this->form_catatan_dosen = '';
        $this->form_file = null;
        $this->showBimbinganModal = true;
    }

    public function closeBimbinganModal(): void
    {
        $this->showBimbinganModal = false;
    }

    /**
     * Sama persis dengan TugasAkhirController::storeTugasAkhirBimbinganDosen — unik per
     * (id_tugas_akhir, id_dosen, tanggal_bimbingan).
     */
    public function saveBimbingan(): void
    {
        $this->ensureAccess($this->tugasAkhir);

        $validated = $this->validate([
            'form_tanggal_bimbingan' => ['required', 'date'],
            'form_catatan_dosen' => ['nullable', 'string'],
            'form_file' => ['nullable', 'file', 'max:10240'],
        ]);

        $tanggal = Carbon::parse($validated['form_tanggal_bimbingan'])->toDateString();

        $dup = TugasAkhirBimbingan::where('id_tugas_akhir', $this->tugasAkhirId)
            ->where('id_dosen', $this->dosenId)
            ->whereDate('tanggal_bimbingan', $tanggal)
            ->exists();

        if ($dup) {
            $this->addError('form_tanggal_bimbingan', 'Untuk tanggal ini sudah ada entri bimbingan. Ubah tanggal atau edit entri yang ada.');

            return;
        }

        $pathFile = $this->form_file ? $this->form_file->store('tugas-akhir-bimbingan', 'public') : null;

        TugasAkhirBimbingan::create([
            'id_tugas_akhir' => $this->tugasAkhirId,
            'id_dosen' => $this->dosenId,
            'tanggal_bimbingan' => $tanggal,
            'catatan_dosen' => $validated['form_catatan_dosen'] !== '' ? $validated['form_catatan_dosen'] : null,
            'file' => $pathFile,
            'created_by' => Auth::user()->name,
        ]);

        $this->showBimbinganModal = false;
        unset($this->riwayatBimbingan);
        session()->flash('status', 'Bimbingan berhasil dicatat.');
    }

    public function render()
    {
        return view('livewire.dosen.tugas-akhir.show')->extends('layouts.dosen');
    }
}
