<?php

namespace App\Livewire\Dosen\UjianSidang;

use App\Models\Dosen;
use App\Models\JenisMatkul;
use App\Models\Krs;
use App\Models\Nilai;
use App\Models\RentangNilai;
use App\Models\TugasAkhir;
use App\Models\UjianSidang;
use App\Models\UjianSidangPenguji;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    private const JENIS_MATKUL_TA = 'TA';

    // Locked: pengujiSidangId/dosenId dipakai langsung untuk cek kepemilikan baris penguji dan
    // syarat ketua penguji sebelum finalisasi nilai — properti publik biasa bisa di-override
    // client lewat request Livewire.
    #[Locked]
    public int $pengujiSidangId;

    #[Locked]
    public int $dosenId;

    public string $tab = 'detail';

    public string $formNilai = '';

    public string $formCatatan = '';

    public bool $showFinalisasiConfirm = false;

    public function mount(int $id): void
    {
        $this->pengujiSidangId = $id;

        $dosen = Dosen::where('id_user', Auth::id())->firstOrFail();
        $this->dosenId = $dosen->id;

        $row = $this->pengujiSidang;
        $this->formNilai = $row->nilai !== null ? (string) $row->nilai : '';
        $this->formCatatan = $row->catatan ?? '';
    }

    /**
     * Sama persis dengan TugasAkhirController::showUjianSidangPengujiDosen /
     * updateUjianSidangPengujiDosen — baris penguji harus milik dosen login.
     */
    private function ensureAccess(UjianSidangPenguji $pengujiSidang): void
    {
        abort_unless((int) $pengujiSidang->id_dosen === $this->dosenId, 403, 'Anda tidak memiliki akses ke data ini.');
    }

    #[Computed]
    public function pengujiSidang(): UjianSidangPenguji
    {
        $row = UjianSidangPenguji::with([
            'ujianSidang.semester',
            'ujianSidang.tugasAkhir.mahasiswa.prodi',
            'ujianSidang.penguji.dosen',
        ])->find($this->pengujiSidangId);

        abort_unless($row, 404, 'Data penguji tidak ditemukan.');
        $this->ensureAccess($row);

        return $row;
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['detail', 'nilai'], true) ? $tab : 'detail';
    }

    public function resetForm(): void
    {
        $row = $this->pengujiSidang;
        $this->formNilai = $row->nilai !== null ? (string) $row->nilai : '';
        $this->formCatatan = $row->catatan ?? '';
        $this->resetValidation();
    }

    /**
     * Sama persis dengan TugasAkhirController::updateUjianSidangPengujiDosen (bagian nilai &
     * catatan — dosen tidak diberi kendali atas kolom status lewat halaman ini, sama seperti
     * halaman dosen di frontend).
     */
    public function saveNilai(): void
    {
        $row = $this->pengujiSidang;

        $validated = $this->validate([
            'formNilai' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'formCatatan' => ['nullable', 'string'],
        ], [], ['formNilai' => 'nilai', 'formCatatan' => 'catatan']);

        $row->update([
            'nilai' => $validated['formNilai'] !== '' ? $validated['formNilai'] : null,
            'catatan' => $validated['formCatatan'] !== '' ? $validated['formCatatan'] : null,
            'updated_by' => Auth::user()->name,
        ]);

        unset($this->pengujiSidang, $this->previewFinalisasi);
        $this->resetForm();
        session()->flash('status', 'Penilaian disimpan.');
    }

    /**
     * Sama persis dengan TugasAkhirController::resolveFinalisasiNilaiUjianSidang, dipanggil lewat
     * previewFinalisasiNilaiUjianSidangDosen (hanya ketua penguji).
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function previewFinalisasi(): array
    {
        $pengujiSidang = $this->pengujiSidang;
        if (! $pengujiSidang->is_ketua) {
            return ['ok' => false, 'message' => 'Hanya ketua penguji yang dapat memfinalisasi nilai ke transkrip.'];
        }

        $ujianSidang = UjianSidang::with('penguji')->find($pengujiSidang->id_ujian_sidang);
        if (! $ujianSidang) {
            return ['ok' => false, 'message' => 'Ujian sidang tidak ditemukan.'];
        }

        $tugasAkhir = TugasAkhir::with('mahasiswa.prodi.jenjang')->find($ujianSidang->id_tugas_akhir);
        if (! $tugasAkhir) {
            return ['ok' => false, 'message' => 'Tugas akhir tidak ditemukan.'];
        }

        $penguji = $ujianSidang->penguji;
        if ($penguji->isEmpty()) {
            return ['ok' => false, 'message' => 'Belum ada dosen penguji.'];
        }

        $nilaiAngkaList = [];
        foreach ($penguji as $p) {
            if ($p->nilai === null) {
                return ['ok' => false, 'message' => 'Semua dosen penguji harus mengisi nilai sebelum finalisasi.'];
            }
            $nilaiAngkaList[] = (float) $p->nilai;
        }

        $rata = array_sum($nilaiAngkaList) / count($nilaiAngkaList);

        $jenjang = $tugasAkhir->mahasiswa?->prodi?->jenjang;
        if (! $jenjang) {
            return ['ok' => false, 'message' => 'Jenjang program studi mahasiswa tidak ditemukan.'];
        }

        $rentangNilaiList = RentangNilai::where('id_jenjang', $jenjang->id)
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
        return Krs::with(['kelas.kurikulumMatkul.matkul', 'kelas.semester', 'kelas.dosenPic'])
            ->where('id_mahasiswa', $idMahasiswa)
            ->whereNull('deleted_at')
            ->whereNotNull('approved_at')
            ->whereHas('kelas', fn ($q) => $q->where('id_semester', $idSemester))
            ->whereHas('kelas.kurikulumMatkul.matkul', fn ($q) => $q->where('id_jenis_matkul', $idJenisTa))
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
     * Sama persis dengan TugasAkhirController::finalisasiNilaiUjianSidangDosen /
     * commitFinalisasiNilaiToTranskrip — hanya ketua penguji.
     */
    public function finalisasiNilai(): void
    {
        $this->showFinalisasiConfirm = false;

        $pengujiSidang = $this->pengujiSidang;
        abort_unless($pengujiSidang->is_ketua, 403, 'Hanya ketua penguji yang dapat memfinalisasi nilai ke transkrip.');

        $preview = $this->previewFinalisasi;
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
        return view('livewire.dosen.ujian-sidang.show')->extends('layouts.dosen');
    }
}
