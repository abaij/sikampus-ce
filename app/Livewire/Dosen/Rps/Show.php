<?php

namespace App\Livewire\Dosen\Rps;

use App\Models\Dosen;
use App\Models\Kelas;
use App\Models\KelasDosen;
use App\Models\Rps;
use App\Models\RpsCpl;
use App\Models\RpsCpmk;
use App\Models\RpsPembelajaran;
use App\Models\RpsSubcpmk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    // Locked: dosenIsPic() dan seluruh aksi simpan/hapus CPL/CPMK/Pembelajaran memakai kelasId
    // dan dosenId langsung — tanpa ini, keduanya bisa "disentuh" lewat request Livewire yang
    // dimanipulasi untuk mengubah RPS kelas yang bukan diampu dosen ini.
    #[Locked]
    public int $kelasId;

    #[Locked]
    public int $dosenId;

    public string $activeTab = 'info';

    // Tab Info RPS
    public string $deskripsi_matkul = '';

    public string $deskripsi_matkul_en = '';

    public string $materi_kuliah = '';

    public string $model_pembelajaran = '';

    public string $pustaka_utama = '';

    public string $pustaka_pendukung = '';

    public string $media_perangkat_lunak = '';

    public string $media_perangkat_keras = '';

    public string $tanggal_penyusunan = '';

    // Modal CPL
    public bool $showCplModal = false;

    public ?int $editingCplId = null;

    public string $form_cpl = '';

    public string $form_cpl_en = '';

    // Modal CPMK
    public bool $showCpmkModal = false;

    public ?int $editingCpmkId = null;

    public string $form_cpmk = '';

    public string $form_cpmk_en = '';

    // Modal SubCPMK
    public bool $showSubcpmkModal = false;

    public ?int $editingSubcpmkId = null;

    public ?int $subcpmkParentCpmkId = null;

    public string $form_subcpmk = '';

    public string $form_subcpmk_en = '';

    // Modal Pembelajaran
    public bool $showPembelajaranModal = false;

    public ?int $editingPembelajaranId = null;

    public string $form_urutan_pertemuan = '';

    public string $form_sub_cpmk = '';

    public string $form_indikator_penilaian = '';

    public string $form_bentuk_kriteria_penilaian = '';

    public string $form_pembelajaran_sinkron = '';

    public string $form_pembelajaran_asinkron = '';

    public string $form_materi = '';

    public string $form_materi_en = '';

    public string $form_bobot = '';

    public function mount(int $kelasId): void
    {
        $dosen = Dosen::where('id_user', Auth::id())->firstOrFail();
        $this->dosenId = $dosen->id;

        $kelas = Kelas::find($kelasId);
        abort_unless($kelas, 404, 'Kelas tidak ditemukan.');
        abort_unless($this->dosenIsPic(), 403, 'Anda tidak berhak mengakses RPS kelas ini.');

        $this->kelasId = $kelasId;
        $this->fillInfoForm();
    }

    private function dosenIsPic(): bool
    {
        return KelasDosen::where('id_dosen', $this->dosenId)
            ->where('id_kelas', $this->kelasId)
            ->where('is_pic', true)
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * findOrFail lewat query yang sudah discope ke kelas ini melempar ModelNotFoundException, yang
     * tidak diterjemahkan Livewire test harness jadi respons HTTP — abort_unless di sini memastikan
     * baris milik dosen/kelas lain (atau id yang tidak ada) selalu berujung 404 yang bisa diuji.
     */
    private function firstOrAbort(Builder $query)
    {
        $row = $query->first();
        abort_unless($row, 404, 'Data tidak ditemukan.');

        return $row;
    }

    #[Computed]
    public function kelas(): Kelas
    {
        return Kelas::with(['kurikulumMatkul.matkul', 'prodi.jenjang', 'semester', 'kelompokKelas'])->findOrFail($this->kelasId);
    }

    #[Computed]
    public function rps(): ?Rps
    {
        return Rps::with(['rpsCpl', 'rpsCpmk.rpsSubcpmk', 'rpsPembelajaran'])->where('id_kelas', $this->kelasId)->first();
    }

    private function fillInfoForm(): void
    {
        $rps = $this->rps;
        $this->deskripsi_matkul = (string) $rps?->deskripsi_matkul;
        $this->deskripsi_matkul_en = (string) $rps?->deskripsi_matkul_en;
        $this->materi_kuliah = (string) $rps?->materi_kuliah;
        $this->model_pembelajaran = (string) $rps?->model_pembelajaran;
        $this->pustaka_utama = (string) $rps?->pustaka_utama;
        $this->pustaka_pendukung = (string) $rps?->pustaka_pendukung;
        $this->media_perangkat_lunak = (string) $rps?->media_perangkat_lunak;
        $this->media_perangkat_keras = (string) $rps?->media_perangkat_keras;
        $this->tanggal_penyusunan = $rps?->tanggal_penyusunan?->format('Y-m-d') ?? '';
    }

    /**
     * Sama persis dengan JadwalDosenController::upsertRpsByKelas.
     */
    public function saveInfo(): void
    {
        $validated = $this->validate([
            'deskripsi_matkul' => ['nullable', 'string'],
            'deskripsi_matkul_en' => ['nullable', 'string'],
            'materi_kuliah' => ['nullable', 'string'],
            'model_pembelajaran' => ['nullable', 'string'],
            'pustaka_utama' => ['nullable', 'string'],
            'pustaka_pendukung' => ['nullable', 'string'],
            'media_perangkat_lunak' => ['nullable', 'string'],
            'media_perangkat_keras' => ['nullable', 'string'],
            'tanggal_penyusunan' => ['nullable', 'date'],
        ]);

        $rps = Rps::firstOrNew(['id_kelas' => $this->kelasId]);
        $isNew = ! $rps->exists;

        foreach ([
            'deskripsi_matkul', 'deskripsi_matkul_en', 'materi_kuliah', 'model_pembelajaran',
            'pustaka_utama', 'pustaka_pendukung', 'media_perangkat_lunak', 'media_perangkat_keras',
        ] as $field) {
            $rps->{$field} = $validated[$field] !== '' ? $validated[$field] : null;
        }
        $rps->tanggal_penyusunan = $validated['tanggal_penyusunan'] !== '' ? $validated['tanggal_penyusunan'] : null;

        if ($isNew) {
            $rps->created_by = Auth::user()->name ?? (string) Auth::id();
        }

        $rps->save();

        unset($this->rps);
        session()->flash('status', $isNew ? 'RPS berhasil dibuat.' : 'RPS berhasil diperbarui.');
    }

    // ==================== CPL ====================

    public function openCplModal(?int $id = null): void
    {
        $this->resetValidation();

        if ($id) {
            $row = $this->firstOrAbort(RpsCpl::whereHas('rps', fn ($q) => $q->where('id_kelas', $this->kelasId))->where('id', $id));
            $this->editingCplId = $row->id;
            $this->form_cpl = (string) $row->cpl;
            $this->form_cpl_en = (string) $row->cpl_en;
        } else {
            $this->editingCplId = null;
            $this->form_cpl = '';
            $this->form_cpl_en = '';
        }

        $this->showCplModal = true;
    }

    public function closeCplModal(): void
    {
        $this->showCplModal = false;
        $this->editingCplId = null;
    }

    public function saveCpl(): void
    {
        $rps = $this->rps;
        if (! $rps) {
            $this->addError('form_cpl', 'Simpan bagian Info RPS terlebih dahulu agar baris RPS tersedia, lalu tambahkan CPL.');

            return;
        }

        $cpl = trim($this->form_cpl);
        $cplEn = trim($this->form_cpl_en);
        if ($cpl === '' && $cplEn === '') {
            $this->addError('form_cpl', 'Isi teks CPL (Bahasa Indonesia) dan/atau CPL (English).');

            return;
        }

        if ($this->editingCplId) {
            $this->firstOrAbort(RpsCpl::whereHas('rps', fn ($q) => $q->where('id_kelas', $this->kelasId))->where('id', $this->editingCplId))
                ->update(['cpl' => $cpl !== '' ? $cpl : null, 'cpl_en' => $cplEn !== '' ? $cplEn : null]);
        } else {
            RpsCpl::create(['id_rps' => $rps->id, 'cpl' => $cpl !== '' ? $cpl : null, 'cpl_en' => $cplEn !== '' ? $cplEn : null]);
        }

        unset($this->rps);
        $this->closeCplModal();
        session()->flash('status', 'CPL berhasil disimpan.');
    }

    public function deleteCpl(int $id): void
    {
        $this->firstOrAbort(RpsCpl::whereHas('rps', fn ($q) => $q->where('id_kelas', $this->kelasId))->where('id', $id))->delete();
        unset($this->rps);
        session()->flash('status', 'CPL berhasil dihapus.');
    }

    // ==================== CPMK ====================

    public function openCpmkModal(?int $id = null): void
    {
        $this->resetValidation();

        if ($id) {
            $row = $this->firstOrAbort(RpsCpmk::whereHas('rps', fn ($q) => $q->where('id_kelas', $this->kelasId))->where('id', $id));
            $this->editingCpmkId = $row->id;
            $this->form_cpmk = (string) $row->cpmk;
            $this->form_cpmk_en = (string) $row->cpmk_en;
        } else {
            $this->editingCpmkId = null;
            $this->form_cpmk = '';
            $this->form_cpmk_en = '';
        }

        $this->showCpmkModal = true;
    }

    public function closeCpmkModal(): void
    {
        $this->showCpmkModal = false;
        $this->editingCpmkId = null;
    }

    public function saveCpmk(): void
    {
        $rps = $this->rps;
        if (! $rps) {
            $this->addError('form_cpmk', 'Simpan bagian Info RPS terlebih dahulu agar baris RPS tersedia.');

            return;
        }

        $cpmk = trim($this->form_cpmk);
        $cpmkEn = trim($this->form_cpmk_en);
        if ($cpmk === '' && $cpmkEn === '') {
            $this->addError('form_cpmk', 'Isi teks CPMK (Bahasa Indonesia) dan/atau CPMK (English).');

            return;
        }

        if ($this->editingCpmkId) {
            $this->firstOrAbort(RpsCpmk::whereHas('rps', fn ($q) => $q->where('id_kelas', $this->kelasId))->where('id', $this->editingCpmkId))
                ->update(['cpmk' => $cpmk !== '' ? $cpmk : null, 'cpmk_en' => $cpmkEn !== '' ? $cpmkEn : null]);
        } else {
            RpsCpmk::create(['id_rps' => $rps->id, 'cpmk' => $cpmk !== '' ? $cpmk : null, 'cpmk_en' => $cpmkEn !== '' ? $cpmkEn : null]);
        }

        unset($this->rps);
        $this->closeCpmkModal();
        session()->flash('status', 'CPMK berhasil disimpan.');
    }

    public function deleteCpmk(int $id): void
    {
        $row = $this->firstOrAbort(RpsCpmk::whereHas('rps', fn ($q) => $q->where('id_kelas', $this->kelasId))->where('id', $id));
        RpsSubcpmk::where('id_cpmk', $row->id)->get()->each->delete();
        $row->delete();
        unset($this->rps);
        session()->flash('status', 'CPMK dan sub-CPMK terkait berhasil dihapus.');
    }

    // ==================== SubCPMK ====================

    public function openSubcpmkModal(int $cpmkId, ?int $id = null): void
    {
        $this->resetValidation();
        $this->subcpmkParentCpmkId = $cpmkId;

        if ($id) {
            $row = $this->firstOrAbort(RpsSubcpmk::whereHas('rpsCpmk.rps', fn ($q) => $q->where('id_kelas', $this->kelasId))->where('id', $id));
            $this->editingSubcpmkId = $row->id;
            $this->form_subcpmk = (string) $row->subcpmk;
            $this->form_subcpmk_en = (string) $row->subcpmk_en;
        } else {
            $this->editingSubcpmkId = null;
            $this->form_subcpmk = '';
            $this->form_subcpmk_en = '';
        }

        $this->showSubcpmkModal = true;
    }

    public function closeSubcpmkModal(): void
    {
        $this->showSubcpmkModal = false;
        $this->editingSubcpmkId = null;
        $this->subcpmkParentCpmkId = null;
    }

    public function saveSubcpmk(): void
    {
        $sub = trim($this->form_subcpmk);
        $subEn = trim($this->form_subcpmk_en);
        if ($sub === '' && $subEn === '') {
            $this->addError('form_subcpmk', 'Isi teks sub-CPMK (Bahasa Indonesia) dan/atau (English).');

            return;
        }

        if ($this->editingSubcpmkId) {
            $this->firstOrAbort(RpsSubcpmk::whereHas('rpsCpmk.rps', fn ($q) => $q->where('id_kelas', $this->kelasId))->where('id', $this->editingSubcpmkId))
                ->update(['subcpmk' => $sub !== '' ? $sub : null, 'subcpmk_en' => $subEn !== '' ? $subEn : null]);
        } else {
            $cpmk = $this->firstOrAbort(RpsCpmk::whereHas('rps', fn ($q) => $q->where('id_kelas', $this->kelasId))->where('id', $this->subcpmkParentCpmkId));
            RpsSubcpmk::create(['id_cpmk' => $cpmk->id, 'subcpmk' => $sub !== '' ? $sub : null, 'subcpmk_en' => $subEn !== '' ? $subEn : null]);
        }

        unset($this->rps);
        $this->closeSubcpmkModal();
        session()->flash('status', 'Sub-CPMK berhasil disimpan.');
    }

    public function deleteSubcpmk(int $id): void
    {
        $this->firstOrAbort(RpsSubcpmk::whereHas('rpsCpmk.rps', fn ($q) => $q->where('id_kelas', $this->kelasId))->where('id', $id))->delete();
        unset($this->rps);
        session()->flash('status', 'Sub-CPMK berhasil dihapus.');
    }

    // ==================== Pembelajaran ====================

    public function openPembelajaranModal(?int $id = null): void
    {
        $this->resetValidation();

        if ($id) {
            $row = $this->firstOrAbort(RpsPembelajaran::whereHas('rps', fn ($q) => $q->where('id_kelas', $this->kelasId))->where('id', $id));
            $this->editingPembelajaranId = $row->id;
            $this->form_urutan_pertemuan = $row->urutan_pertemuan !== null ? (string) $row->urutan_pertemuan : '';
            $this->form_sub_cpmk = (string) $row->sub_cpmk;
            $this->form_indikator_penilaian = (string) $row->indikator_penilaian;
            $this->form_bentuk_kriteria_penilaian = (string) $row->bentuk_kriteria_penilaian;
            $this->form_pembelajaran_sinkron = (string) $row->pembelajaran_sinkron;
            $this->form_pembelajaran_asinkron = (string) $row->pembelajaran_asinkron;
            $this->form_materi = (string) $row->materi;
            $this->form_materi_en = (string) $row->materi_en;
            $this->form_bobot = $row->bobot !== null ? (string) $row->bobot : '';
        } else {
            $this->editingPembelajaranId = null;
            $this->form_urutan_pertemuan = '';
            $this->form_sub_cpmk = '';
            $this->form_indikator_penilaian = '';
            $this->form_bentuk_kriteria_penilaian = '';
            $this->form_pembelajaran_sinkron = '';
            $this->form_pembelajaran_asinkron = '';
            $this->form_materi = '';
            $this->form_materi_en = '';
            $this->form_bobot = '';
        }

        $this->showPembelajaranModal = true;
    }

    public function closePembelajaranModal(): void
    {
        $this->showPembelajaranModal = false;
        $this->editingPembelajaranId = null;
    }

    /**
     * Sama persis dengan JadwalDosenController::rulesRpsPembelajaran/applyValidatedToRpsPembelajaran.
     */
    public function savePembelajaran(): void
    {
        $validated = $this->validate([
            'form_urutan_pertemuan' => ['nullable', 'integer', 'min:1', 'max:999'],
            'form_sub_cpmk' => ['nullable', 'string'],
            'form_indikator_penilaian' => ['nullable', 'string'],
            'form_bentuk_kriteria_penilaian' => ['nullable', 'string'],
            'form_pembelajaran_sinkron' => ['nullable', 'string'],
            'form_pembelajaran_asinkron' => ['nullable', 'string'],
            'form_materi' => ['nullable', 'string'],
            'form_materi_en' => ['nullable', 'string'],
            'form_bobot' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
        ]);

        $attrs = [
            'urutan_pertemuan' => $validated['form_urutan_pertemuan'] !== '' ? (int) $validated['form_urutan_pertemuan'] : null,
            'sub_cpmk' => $validated['form_sub_cpmk'] !== '' ? $validated['form_sub_cpmk'] : null,
            'indikator_penilaian' => $validated['form_indikator_penilaian'] !== '' ? $validated['form_indikator_penilaian'] : null,
            'bentuk_kriteria_penilaian' => $validated['form_bentuk_kriteria_penilaian'] !== '' ? $validated['form_bentuk_kriteria_penilaian'] : null,
            'pembelajaran_sinkron' => $validated['form_pembelajaran_sinkron'] !== '' ? $validated['form_pembelajaran_sinkron'] : null,
            'pembelajaran_asinkron' => $validated['form_pembelajaran_asinkron'] !== '' ? $validated['form_pembelajaran_asinkron'] : null,
            'materi' => $validated['form_materi'] !== '' ? $validated['form_materi'] : null,
            'materi_en' => $validated['form_materi_en'] !== '' ? $validated['form_materi_en'] : null,
            'bobot' => $validated['form_bobot'] !== '' ? (float) $validated['form_bobot'] : null,
        ];

        if ($this->editingPembelajaranId) {
            $this->firstOrAbort(RpsPembelajaran::whereHas('rps', fn ($q) => $q->where('id_kelas', $this->kelasId))->where('id', $this->editingPembelajaranId))
                ->update($attrs);
        } else {
            $rps = $this->rps;
            if (! $rps) {
                $this->addError('form_materi', 'Simpan bagian Info RPS terlebih dahulu agar baris RPS tersedia.');

                return;
            }
            RpsPembelajaran::create(['id_rps' => $rps->id, ...$attrs]);
        }

        unset($this->rps);
        $this->closePembelajaranModal();
        session()->flash('status', 'Rincian pembelajaran berhasil disimpan.');
    }

    public function deletePembelajaran(int $id): void
    {
        $this->firstOrAbort(RpsPembelajaran::whereHas('rps', fn ($q) => $q->where('id_kelas', $this->kelasId))->where('id', $id))->delete();
        unset($this->rps);
        session()->flash('status', 'Rincian pembelajaran berhasil dihapus.');
    }

    public function render()
    {
        return view('livewire.dosen.rps.show')->extends('layouts.dosen');
    }
}
