<?php

namespace App\Livewire\Admin\TugasAkhir;

use App\Models\Dosen;
use App\Models\JenisMatkul;
use App\Models\Krs;
use App\Models\Nilai;
use App\Models\RentangNilai;
use App\Models\TugasAkhir;
use App\Models\UjianSidang;
use App\Models\UjianSidangPenguji;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

class UjianSidangShow extends Component
{
    private const JENIS_MATKUL_TA = 'TA';

    private const UJIAN_SIDANG_STATUSES = ['draft', 'submitted', 'approved', 'rejected'];

    private const PENGUJI_STATUSES = ['draft', 'submitted', 'approved', 'rejected'];

    public int $tugasAkhirId;

    public int $ujianSidangId;

    // Jadwal
    public string $tanggalMulai = '';

    public string $tanggalSelesai = '';

    // Status pengajuan
    public string $statusPengajuan = 'draft';

    // Tambah penguji
    public ?int $pengujiDosenId = null;

    public bool $pengujiIsKetua = false;

    // Ubah penguji
    public ?int $editingPengujiId = null;

    public ?int $editPengujiDosenId = null;

    public bool $editPengujiIsKetua = false;

    public string $editPengujiCatatan = '';

    public string $editPengujiNilai = '';

    public string $editPengujiStatus = 'draft';

    public ?int $confirmingPengujiDeleteId = null;

    // Finalisasi nilai
    public bool $showFinalisasiConfirm = false;

    public function mount(int $id, int $sidangId): void
    {
        $this->tugasAkhirId = $id;
        $this->ujianSidangId = $sidangId;

        $tugasAkhir = TugasAkhir::with('mahasiswa')->findOrFail($id);
        $this->ensureAccess($tugasAkhir);

        $ujianSidang = UjianSidang::findOrFail($sidangId);
        if ((int) $ujianSidang->id_tugas_akhir !== $id) {
            abort(404, 'Ujian sidang tidak ditemukan untuk tugas akhir ini.');
        }

        $this->tanggalMulai = $ujianSidang->tanggal_ujian_mulai?->format('Y-m-d\TH:i') ?? '';
        $this->tanggalSelesai = $ujianSidang->tanggal_ujian_selesai?->format('Y-m-d\TH:i') ?? '';
        $this->statusPengajuan = in_array($ujianSidang->status, self::UJIAN_SIDANG_STATUSES, true) ? $ujianSidang->status : 'draft';
    }

    /**
     * Sama persis dengan TugasAkhirController::assertTugasAkhirProdiScope.
     */
    private function ensureAccess(TugasAkhir $tugasAkhir): void
    {
        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && $tugasAkhir->mahasiswa
                && ! in_array((int) $tugasAkhir->mahasiswa->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke data tugas akhir ini.');
            }
        }
    }

    private function actor(): string
    {
        $user = Auth::user();
        $name = trim((string) ($user->name ?? ''));

        return $name !== '' ? $name : (string) ($user->email ?? '');
    }

    #[Computed]
    public function tugasAkhir(): TugasAkhir
    {
        return TugasAkhir::with(['mahasiswa.prodi', 'semester', 'pembimbing.dosen'])->findOrFail($this->tugasAkhirId);
    }

    #[Computed]
    public function ujianSidang(): UjianSidang
    {
        return UjianSidang::with(['semester', 'penguji.dosen'])->findOrFail($this->ujianSidangId);
    }

    #[Computed]
    public function dosenOptions()
    {
        return Dosen::whereNull('deleted_at')->orderBy('nama')->get()
            ->map(fn (Dosen $d) => (object) ['id' => $d->id, 'label' => $this->formatDosenLabel($d)]);
    }

    private function formatDosenLabel(Dosen $dosen): string
    {
        $label = trim(($dosen->gelar_depan ? $dosen->gelar_depan.' ' : '').$dosen->nama.($dosen->gelar_belakang ? ', '.$dosen->gelar_belakang : ''));

        return $dosen->kode_dosen ? "{$label} ({$dosen->kode_dosen})" : $label;
    }

    // ---------- Jadwal & status pengajuan ----------

    /**
     * Sama persis dengan TugasAkhirController::updateUjianSidang (bagian jadwal).
     */
    public function saveJadwal(): void
    {
        $validated = $this->validate([
            'tanggalMulai' => ['nullable', 'date'],
            'tanggalSelesai' => ['nullable', 'date'],
        ], [], ['tanggalMulai' => 'waktu mulai', 'tanggalSelesai' => 'waktu selesai']);

        $mulai = ($validated['tanggalMulai'] ?? '') !== '' ? Carbon::parse($validated['tanggalMulai']) : null;
        $selesai = ($validated['tanggalSelesai'] ?? '') !== '' ? Carbon::parse($validated['tanggalSelesai']) : null;

        if ($mulai !== null && $selesai !== null && $selesai->lt($mulai)) {
            $this->addError('tanggalSelesai', 'Waktu selesai ujian harus sama atau setelah waktu mulai.');

            return;
        }

        UjianSidang::findOrFail($this->ujianSidangId)->update([
            'tanggal_ujian_mulai' => $mulai,
            'tanggal_ujian_selesai' => $selesai,
            'updated_by' => $this->actor(),
        ]);

        unset($this->ujianSidang);
        session()->flash('status', 'Jadwal ujian sidang disimpan.');
    }

    /**
     * Sama persis dengan TugasAkhirController::updateUjianSidang (bagian status).
     */
    public function saveStatusPengajuan(): void
    {
        $validated = $this->validate([
            'statusPengajuan' => ['required', 'string', 'in:'.implode(',', self::UJIAN_SIDANG_STATUSES)],
        ], [], ['statusPengajuan' => 'status']);

        UjianSidang::findOrFail($this->ujianSidangId)->update([
            'status' => $validated['statusPengajuan'],
            'updated_by' => $this->actor(),
        ]);

        unset($this->ujianSidang);
        session()->flash('status', 'Status pengajuan ujian sidang diperbarui.');
    }

    // ---------- Penguji ----------

    /**
     * Sama persis dengan TugasAkhirController::storePengujiSidang.
     */
    public function addPenguji(): void
    {
        $validated = $this->validate([
            'pengujiDosenId' => ['required', 'integer', 'exists:dosen,id'],
        ], [], ['pengujiDosenId' => 'dosen']);

        $dup = UjianSidangPenguji::query()
            ->where('id_ujian_sidang', $this->ujianSidangId)
            ->where('id_dosen', $validated['pengujiDosenId'])
            ->exists();

        if ($dup) {
            $this->addError('pengujiDosenId', 'Dosen ini sudah terdaftar sebagai penguji.');

            return;
        }

        $actor = $this->actor();

        UjianSidangPenguji::create([
            'id_ujian_sidang' => $this->ujianSidangId,
            'id_dosen' => $validated['pengujiDosenId'],
            'is_ketua' => $this->pengujiIsKetua,
            'status' => 'draft',
            'created_by' => $actor,
            'updated_by' => $actor,
        ]);

        $this->pengujiDosenId = null;
        $this->pengujiIsKetua = false;
        unset($this->ujianSidang, $this->previewFinalisasi);
        session()->flash('status', 'Penguji berhasil ditambahkan.');
    }

    public function openEditPenguji(int $id): void
    {
        $this->resetValidation();

        $item = UjianSidangPenguji::findOrFail($id);
        $this->editingPengujiId = $item->id;
        $this->editPengujiDosenId = $item->id_dosen;
        $this->editPengujiIsKetua = $item->is_ketua;
        $this->editPengujiCatatan = $item->catatan ?? '';
        $this->editPengujiNilai = $item->nilai !== null ? (string) $item->nilai : '';
        $this->editPengujiStatus = $item->status;
    }

    public function cancelEditPenguji(): void
    {
        $this->editingPengujiId = null;
    }

    /**
     * Sama persis dengan TugasAkhirController::updatePengujiSidang.
     */
    public function saveEditPenguji(): void
    {
        if (! $this->editingPengujiId) {
            return;
        }

        $validated = $this->validate([
            'editPengujiDosenId' => ['required', 'integer', 'exists:dosen,id'],
            'editPengujiCatatan' => ['nullable', 'string'],
            'editPengujiNilai' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'editPengujiStatus' => ['required', 'string', 'in:'.implode(',', self::PENGUJI_STATUSES)],
        ], [], [
            'editPengujiDosenId' => 'dosen',
            'editPengujiCatatan' => 'catatan',
            'editPengujiNilai' => 'nilai',
            'editPengujiStatus' => 'status',
        ]);

        $dup = UjianSidangPenguji::query()
            ->where('id_ujian_sidang', $this->ujianSidangId)
            ->where('id_dosen', $validated['editPengujiDosenId'])
            ->where('id', '!=', $this->editingPengujiId)
            ->exists();

        if ($dup) {
            $this->addError('editPengujiDosenId', 'Dosen ini sudah terdaftar sebagai penguji lain.');

            return;
        }

        UjianSidangPenguji::findOrFail($this->editingPengujiId)->update([
            'id_dosen' => $validated['editPengujiDosenId'],
            'is_ketua' => $this->editPengujiIsKetua,
            'catatan' => ($validated['editPengujiCatatan'] ?? '') !== '' ? $validated['editPengujiCatatan'] : null,
            'nilai' => ($validated['editPengujiNilai'] ?? '') !== '' ? $validated['editPengujiNilai'] : null,
            'status' => $validated['editPengujiStatus'],
            'updated_by' => $this->actor(),
        ]);

        $this->editingPengujiId = null;
        unset($this->ujianSidang, $this->previewFinalisasi);
        session()->flash('status', 'Data penguji diperbarui.');
    }

    public function confirmDeletePenguji(int $id): void
    {
        $this->confirmingPengujiDeleteId = $id;
    }

    public function cancelDeletePenguji(): void
    {
        $this->confirmingPengujiDeleteId = null;
    }

    /**
     * Sama persis dengan TugasAkhirController::destroyPengujiSidang.
     */
    public function deletePenguji(): void
    {
        if (! $this->confirmingPengujiDeleteId) {
            return;
        }

        $actor = $this->actor();
        $item = UjianSidangPenguji::findOrFail($this->confirmingPengujiDeleteId);
        $item->update(['deleted_by' => $actor, 'updated_by' => $actor]);
        $item->delete();

        $this->confirmingPengujiDeleteId = null;
        unset($this->ujianSidang, $this->previewFinalisasi);
        session()->flash('status', 'Penguji dihapus.');
    }

    // ---------- Finalisasi nilai ----------

    /**
     * Sama persis dengan TugasAkhirController::resolveFinalisasiNilaiUjianSidang.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function previewFinalisasi(): array
    {
        $tugasAkhir = TugasAkhir::with('mahasiswa.prodi.jenjang')->findOrFail($this->tugasAkhirId);
        $ujianSidang = UjianSidang::with('penguji')->findOrFail($this->ujianSidangId);

        $penguji = $ujianSidang->penguji;
        if ($penguji->isEmpty()) {
            return ['ok' => false, 'message' => 'Belum ada dosen penguji.'];
        }

        $nilaiAngkaList = [];
        $pengujiTanpaNilai = [];
        foreach ($penguji as $p) {
            if ($p->nilai === null) {
                $pengujiTanpaNilai[] = $p->id;

                continue;
            }
            $nilaiAngkaList[] = (float) $p->nilai;
        }

        if ($pengujiTanpaNilai !== []) {
            return ['ok' => false, 'message' => 'Semua dosen penguji harus mengisi nilai sebelum finalisasi.'];
        }

        $rata = array_sum($nilaiAngkaList) / count($nilaiAngkaList);

        $jenjang = $tugasAkhir->mahasiswa?->prodi?->jenjang;
        if (! $jenjang) {
            return ['ok' => false, 'message' => 'Jenjang program studi mahasiswa tidak ditemukan.'];
        }

        $rentangNilaiList = RentangNilai::query()
            ->where('id_jenjang', $jenjang->id)
            ->whereNull('deleted_at')
            ->orderByDesc('nilai_tinggi')
            ->get();

        if ($rentangNilaiList->isEmpty()) {
            return ['ok' => false, 'message' => 'Rentang nilai untuk jenjang '.$jenjang->nama.' belum dikonfigurasi.'];
        }

        $rentangTer = null;
        foreach ($rentangNilaiList as $rn) {
            if ($rata >= (float) $rn->nilai_rendah && $rata <= (float) $rn->nilai_tinggi) {
                $rentangTer = $rn;
                break;
            }
        }

        if (! $rentangTer) {
            return ['ok' => false, 'message' => 'Rata-rata nilai tidak berada dalam rentang nilai yang dikonfigurasi untuk jenjang ini.'];
        }

        $idJenisTa = JenisMatkul::where('kode', self::JENIS_MATKUL_TA)->value('id');
        if (! $idJenisTa) {
            return ['ok' => false, 'message' => 'Jenis mata kuliah Tugas Akhir (kode TA) belum dikonfigurasi.'];
        }

        $krs = $this->findKrsTugasAkhirDisetujui((int) $tugasAkhir->id_mahasiswa, (int) $tugasAkhir->id_semester, (int) $idJenisTa);
        if (! $krs) {
            return ['ok' => false, 'message' => 'KRS mata kuliah Tugas Akhir (jenis TA) yang disetujui tidak ditemukan untuk semester tugas akhir ini.'];
        }

        $krs->load(['kelas.kurikulumMatkul.matkul']);
        $km = $krs->kelas?->kurikulumMatkul;
        $sks = (int) ($km?->sks ?? $km?->matkul?->sks ?? 0);

        $nilaiEksisting = Nilai::where('id_krs', $krs->id)->first();

        return [
            'ok' => true,
            'rata_rata' => round($rata, 2),
            'jumlah_penguji' => count($nilaiAngkaList),
            'rentang' => [
                'nilai_huruf' => $rentangTer->nilai_huruf,
                'nilai_angka' => (float) $rentangTer->nilai_angka,
                'nilai_rendah' => (float) $rentangTer->nilai_rendah,
                'nilai_tinggi' => (float) $rentangTer->nilai_tinggi,
            ],
            'jenjang' => ['id' => $jenjang->id, 'nama' => $jenjang->nama],
            'krs_id' => $krs->id,
            'sks' => $sks,
            'nilai_eksisting' => $nilaiEksisting ? [
                'huruf_mutu' => $nilaiEksisting->huruf_mutu,
                'angka_mutu' => $nilaiEksisting->angka_mutu !== null ? (float) $nilaiEksisting->angka_mutu : null,
                'is_final' => (bool) $nilaiEksisting->is_final,
            ] : null,
        ];
    }

    /**
     * Sama persis dengan TugasAkhirController::findKrsTugasAkhirDisetujui.
     */
    private function findKrsTugasAkhirDisetujui(int $idMahasiswa, int $idSemester, int $idJenisTa): ?Krs
    {
        return Krs::query()
            ->with(['kelas.kurikulumMatkul.matkul', 'kelas.semester', 'kelas.dosenPic'])
            ->where('id_mahasiswa', $idMahasiswa)
            ->whereNull('deleted_at')
            ->whereNotNull('approved_at')
            ->whereHas('kelas', function ($q) use ($idSemester) {
                $q->where('id_semester', $idSemester);
            })
            ->whereHas('kelas.kurikulumMatkul.matkul', function ($q) use ($idJenisTa) {
                $q->where('id_jenis_matkul', $idJenisTa);
            })
            ->orderByDesc('approved_at')
            ->first();
    }

    public function reloadPreviewFinalisasi(): void
    {
        unset($this->previewFinalisasi);
    }

    public function openFinalisasiConfirm(): void
    {
        $this->showFinalisasiConfirm = true;
    }

    public function closeFinalisasiConfirm(): void
    {
        $this->showFinalisasiConfirm = false;
    }

    /**
     * Sama persis dengan TugasAkhirController::finalisasiNilaiUjianSidang / commitFinalisasiNilaiToTranskrip.
     */
    public function finalisasiNilai(): void
    {
        $preview = $this->previewFinalisasi;
        $this->showFinalisasiConfirm = false;

        if (! ($preview['ok'] ?? false)) {
            return;
        }

        $krsId = (int) $preview['krs_id'];
        $sks = (int) $preview['sks'];
        $rentang = $preview['rentang'];

        DB::transaction(function () use ($krsId, $sks, $rentang): void {
            $nilai = Nilai::withTrashed()->where('id_krs', $krsId)->first();
            if ($nilai && $nilai->trashed()) {
                $nilai->restore();
            }

            Nilai::updateOrCreate(
                ['id_krs' => $krsId],
                [
                    'sks' => $sks > 0 ? $sks : null,
                    'angka_mutu' => $rentang['nilai_angka'],
                    'huruf_mutu' => $rentang['nilai_huruf'],
                    'is_final' => true,
                ]
            );
        });

        unset($this->previewFinalisasi);
        session()->flash('status', 'Nilai tugas akhir berhasil difinalisasi ke transkrip.');
    }

    public function render()
    {
        return view('livewire.admin.tugas-akhir.ujian-sidang-show')->extends('layouts.web');
    }
}
