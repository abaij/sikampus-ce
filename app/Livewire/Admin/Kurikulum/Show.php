<?php

namespace App\Livewire\Admin\Kurikulum;

use App\Livewire\Admin\Kurikulum\Concerns\ForwardsIndexState;
use App\Models\BobotPenilaian;
use App\Models\JenisPenilaian;
use App\Models\Kurikulum;
use App\Models\KurikulumMatkul;
use App\Models\Matkul;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Show extends Component
{
    use ForwardsIndexState;
    use WithPagination;

    public int $kurikulumId;

    public Kurikulum $kurikulum;

    public bool $confirmingDeleteKurikulum = false;

    public string $matkulSearch = '';

    public int $matkulPerPage = 10;

    /** Id baris kurikulum_matkul yang sedang dibuka di modal detail. */
    public ?int $detailMatkulId = null;

    public bool $showBobotForm = false;

    /** @var array<int, string> [id_jenis_penilaian => bobot] untuk form "Kelola Bobot Penilaian". */
    public array $bobotForm = [];

    public bool $showAutoFillConfirm = false;

    public bool $showBobotMassalForm = false;

    /** @var array<int, string> [id_jenis_penilaian => bobot] untuk form "Tetapkan Bobot Massal". */
    public array $bobotMassalForm = [];

    public function mount(int $id): void
    {
        $this->kurikulumId = $id;

        $kurikulum = Kurikulum::with(['prodi.jenjang', 'tahunBerlaku', 'matkuls'])->findOrFail($id);

        $this->ensureAccess($kurikulum);

        $this->kurikulum = $kurikulum;

        $this->resolveBackUrl();
    }

    /**
     * Bukan properti biasa: Livewire memuat ulang $this->kurikulum dari database di setiap
     * request berikutnya (lihat ModelSynth::hydrate), jadi atribut sintetis yang ditempel di
     * model saat mount() akan hilang begitu ada update lain (mis. pencarian mata kuliah). Hitung
     * sebagai Computed supaya selalu dievaluasi ulang dari relasi yang sedang aktif.
     */
    #[Computed]
    public function totalSksKurikulum(): int
    {
        return (int) $this->kurikulum->matkuls->sum(fn (Matkul $matkul) => (int) ($matkul->pivot->sks ?? $matkul->sks ?? 0));
    }

    public function updatingMatkulSearch(): void
    {
        $this->resetPage();
    }

    public function updatingMatkulPerPage(): void
    {
        $this->resetPage();
    }

    /**
     * Daftar mata kuliah kurikulum dengan pencarian + pagination — query pivot langsung (bukan
     * koleksi $kurikulum->matkuls yang sudah dimuat penuh di mount()), meniru search+pagination
     * di app/admin/kurikulum/[id]/page.tsx.
     */
    #[Computed]
    public function matkulList()
    {
        return $this->kurikulum->matkuls()
            ->when($this->matkulSearch !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('matkul.kode', 'like', "%{$this->matkulSearch}%")
                        ->orWhere('matkul.nama', 'like', "%{$this->matkulSearch}%")
                        ->orWhere('kurikulum_matkul.kode_matkul', 'like', "%{$this->matkulSearch}%")
                        ->orWhere('kurikulum_matkul.nama_matkul', 'like', "%{$this->matkulSearch}%");
                });
            })
            ->orderBy('kurikulum_matkul.semester_rekomendasi')
            ->orderBy('matkul.kode')
            ->paginate($this->matkulPerPage);
    }

    /**
     * Total bobot penilaian per baris kurikulum_matkul yang sedang tampil di halaman ini —
     * dihitung hanya untuk pivot id di halaman/pencarian saat ini (bukan seluruh mata kuliah
     * kurikulum sekaligus seperti matkul_bobot_totals di KurikulumController::show), supaya tidak
     * query seluruh mata kuliah untuk kurikulum yang isinya ratusan baris. Dipakai untuk kolom
     * "Status Bobot" di tabel — meniru badge Lengkap/Belum lengkap di app/admin/kurikulum/[id]/page.tsx.
     *
     * @return array<int, float>
     */
    #[Computed]
    public function matkulBobotTotals(): array
    {
        $pivotIds = collect($this->matkulList->items())->pluck('pivot.id')->filter()->values()->all();

        if (empty($pivotIds)) {
            return [];
        }

        return BobotPenilaian::query()
            ->whereIn('id_kurikulum_matkul', $pivotIds)
            ->selectRaw('id_kurikulum_matkul, SUM(bobot) as total')
            ->groupBy('id_kurikulum_matkul')
            ->pluck('total', 'id_kurikulum_matkul')
            ->map(fn ($total) => (float) $total)
            ->all();
    }

    /**
     * Baris kurikulum_matkul yang sedang ditampilkan di modal detail — dibatasi ke kurikulum ini
     * (bukan sekadar KurikulumMatkul::find($id)) supaya id hasil tebakan/manipulasi wire:click
     * tidak bisa membuka baris milik kurikulum lain di luar scope yang sudah divalidasi di mount().
     */
    #[Computed]
    public function detailMatkul(): ?KurikulumMatkul
    {
        if (! $this->detailMatkulId) {
            return null;
        }

        return KurikulumMatkul::with(['matkul', 'bobotPenilaian.jenisPenilaian'])
            ->where('id_kurikulum', $this->kurikulumId)
            ->find($this->detailMatkulId);
    }

    #[Computed]
    public function jenisPenilaianOptions()
    {
        return JenisPenilaian::whereNull('deleted_at')->orderBy('nama')->get();
    }

    /**
     * Jumlah mata kuliah kurikulum yang belum punya bobot penilaian sama sekali — menentukan
     * apakah tombol "Tetapkan Bobot Massal" ditampilkan, sama seperti countMatkulWithoutBobot
     * di app/admin/kurikulum/[id]/page.tsx.
     */
    #[Computed]
    public function matkulTanpaBobotCount(): int
    {
        return KurikulumMatkul::where('id_kurikulum', $this->kurikulumId)
            ->whereDoesntHave('bobotPenilaian')
            ->count();
    }

    public function openDetailModal(int $kurikulumMatkulId): void
    {
        $this->detailMatkulId = $kurikulumMatkulId;
        $this->showBobotForm = false;
        $this->showAutoFillConfirm = false;
        $this->resetErrorBag();
    }

    public function closeDetailModal(): void
    {
        $this->detailMatkulId = null;
        $this->showBobotForm = false;
        $this->showAutoFillConfirm = false;
        $this->bobotForm = [];
        $this->resetErrorBag();
    }

    /**
     * Sama persis dengan KurikulumMatkulController::syncFromMatkul — salin kode/nama/nama EN/SKS
     * dari master matkul ke snapshot kurikulum_matkul. Semester rekomendasi & wajib tidak diubah.
     */
    public function syncMatkulFromMaster(): void
    {
        $km = $this->detailMatkul;
        if (! $km) {
            return;
        }

        $matkul = $km->matkul;
        if (! $matkul) {
            $this->addError('sync', 'Mata kuliah master tidak ditemukan atau sudah dihapus.');

            return;
        }

        $km->update([
            'kode_matkul' => $matkul->kode ?? '',
            'nama_matkul' => $matkul->nama ?? '',
            'nama_matkul_en' => $matkul->nama_en,
            'sks' => $matkul->sks,
        ]);

        unset($this->detailMatkul);
        session()->flash('status', 'Mata kuliah kurikulum berhasil disinkronkan dari master.');
    }

    /**
     * Isi form dengan bobot yang sudah tersimpan (atau 0 kalau belum ada) untuk setiap jenis
     * penilaian aktif — meniru initFormBobot di halaman detail kurikulum-matkul frontend.
     */
    public function openBobotForm(): void
    {
        $km = $this->detailMatkul;
        if (! $km) {
            return;
        }

        $existing = $km->bobotPenilaian->keyBy('id_jenis_penilaian');
        $this->bobotForm = $this->jenisPenilaianOptions
            ->mapWithKeys(function (JenisPenilaian $jenis) use ($existing) {
                $bobot = $existing->get($jenis->id)?->bobot;

                return [$jenis->id => $bobot !== null ? (string) (float) $bobot : '0'];
            })
            ->all();
        $this->resetErrorBag();
        $this->showBobotForm = true;
    }

    public function closeBobotForm(): void
    {
        $this->showBobotForm = false;
        $this->bobotForm = [];
        $this->resetErrorBag();
    }

    public function totalBobotForm(): float
    {
        return collect($this->bobotForm)->sum(fn ($value) => (float) $value);
    }

    /**
     * Sama persis dengan KurikulumMatkulController::updateBobotPenilaian.
     */
    public function saveBobotForm(): void
    {
        $km = $this->detailMatkul;
        if (! $km) {
            return;
        }

        $items = collect($this->bobotForm)
            ->map(fn ($bobot, $idJenis) => ['id_jenis_penilaian' => (int) $idJenis, 'bobot' => (float) $bobot])
            ->values();

        $total = $items->sum('bobot');
        if ($total > 100) {
            $this->addError('bobotForm', 'Total bobot tidak boleh melebihi 100%. Total saat ini: '.round($total, 2).'%.');

            return;
        }

        DB::transaction(function () use ($km, $items) {
            // Hapus fisik agar unique (id_kurikulum_matkul, id_jenis_penilaian) tidak bentrok
            // saat create baru — sama seperti controller API.
            $km->bobotPenilaian()->forceDelete();
            foreach ($items as $row) {
                if ($row['bobot'] > 0) {
                    $km->bobotPenilaian()->create([
                        'id_jenis_penilaian' => $row['id_jenis_penilaian'],
                        'bobot' => $row['bobot'],
                    ]);
                }
            }
        });

        unset($this->detailMatkul, $this->matkulBobotTotals, $this->matkulTanpaBobotCount);
        $this->showBobotForm = false;
        $this->bobotForm = [];
        session()->flash('status', 'Bobot penilaian berhasil disimpan.');
    }

    public function openAutoFillConfirm(): void
    {
        $this->resetErrorBag();
        $this->showAutoFillConfirm = true;
    }

    public function closeAutoFillConfirm(): void
    {
        $this->showAutoFillConfirm = false;
        $this->resetErrorBag();
    }

    /**
     * Terapkan bobot default dari master jenis penilaian ke mata kuliah ini — meniru
     * handleConfirmAutoFill di halaman detail kurikulum-matkul frontend. Memakai endpoint yang
     * sama dengan "Kelola Bobot Penilaian" (replace penuh), hanya sumber datanya beda.
     */
    public function confirmAutoFill(): void
    {
        $km = $this->detailMatkul;
        if (! $km) {
            return;
        }

        $jenisList = $this->jenisPenilaianOptions;
        $total = (float) $jenisList->sum('bobot');
        if ($total > 100) {
            $this->addError('autoFill', 'Total bobot default melebihi 100%. Gunakan "Kelola Bobot Penilaian" untuk mengatur manual.');

            return;
        }

        DB::transaction(function () use ($km, $jenisList) {
            $km->bobotPenilaian()->forceDelete();
            foreach ($jenisList as $jenis) {
                $bobot = (float) $jenis->bobot;
                if ($bobot > 0) {
                    $km->bobotPenilaian()->create([
                        'id_jenis_penilaian' => $jenis->id,
                        'bobot' => $bobot,
                    ]);
                }
            }
        });

        unset($this->detailMatkul, $this->matkulBobotTotals, $this->matkulTanpaBobotCount);
        $this->showAutoFillConfirm = false;
        session()->flash('status', 'Bobot penilaian berhasil diterapkan dari default jenis penilaian.');
    }

    /**
     * Isi form dengan bobot default dari master jenis penilaian — meniru pengisian awal
     * formBobotMassal di app/admin/kurikulum/[id]/page.tsx.
     */
    public function openBobotMassalForm(): void
    {
        $this->bobotMassalForm = $this->jenisPenilaianOptions
            ->mapWithKeys(fn (JenisPenilaian $jenis) => [$jenis->id => (string) (float) ($jenis->bobot ?? 0)])
            ->all();
        $this->resetErrorBag();
        $this->showBobotMassalForm = true;
    }

    public function closeBobotMassalForm(): void
    {
        $this->showBobotMassalForm = false;
        $this->bobotMassalForm = [];
        $this->resetErrorBag();
    }

    public function totalBobotMassalForm(): float
    {
        return collect($this->bobotMassalForm)->sum(fn ($value) => (float) $value);
    }

    /**
     * Sama persis dengan KurikulumController::applyBobotPenilaianMassal — hanya menyentuh mata
     * kuliah kurikulum yang belum punya bobot penilaian sama sekali; yang sudah ada tidak diubah.
     */
    public function saveBobotMassalForm(): void
    {
        $items = collect($this->bobotMassalForm)
            ->map(fn ($bobot, $idJenis) => ['id_jenis_penilaian' => (int) $idJenis, 'bobot' => (float) $bobot])
            ->values();

        $total = $items->sum('bobot');
        if ($total > 100) {
            $this->addError('bobotMassalForm', 'Total bobot penilaian tidak boleh melebihi 100%. Total saat ini: '.round($total, 2).'%.');

            return;
        }

        if ($items->every(fn ($row) => $row['bobot'] <= 0)) {
            $this->addError('bobotMassalForm', 'Minimal satu jenis penilaian harus memiliki bobot > 0.');

            return;
        }

        $matkulsTanpaBobot = KurikulumMatkul::where('id_kurikulum', $this->kurikulumId)
            ->whereDoesntHave('bobotPenilaian')
            ->get();

        if ($matkulsTanpaBobot->isEmpty()) {
            $this->addError('bobotMassalForm', 'Tidak ada mata kuliah yang belum memiliki bobot nilai. Semua mata kuliah sudah diisi.');

            return;
        }

        DB::transaction(function () use ($items, $matkulsTanpaBobot) {
            foreach ($matkulsTanpaBobot as $km) {
                foreach ($items as $row) {
                    if ($row['bobot'] > 0) {
                        $km->bobotPenilaian()->create([
                            'id_jenis_penilaian' => $row['id_jenis_penilaian'],
                            'bobot' => $row['bobot'],
                        ]);
                    }
                }
            }
        });

        unset($this->matkulBobotTotals, $this->matkulTanpaBobotCount, $this->detailMatkul);
        $this->showBobotMassalForm = false;
        $this->bobotMassalForm = [];
        session()->flash('status', 'Bobot penilaian berhasil diterapkan ke '.$matkulsTanpaBobot->count().' mata kuliah.');
    }

    /**
     * Sama persis dengan KurikulumController::show.
     */
    private function ensureAccess(Kurikulum $kurikulum): void
    {
        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $kurikulum->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke kurikulum ini.');
            }
        }
    }

    public function confirmDeleteKurikulum(): void
    {
        $this->confirmingDeleteKurikulum = true;
    }

    public function cancelDeleteKurikulum(): void
    {
        $this->confirmingDeleteKurikulum = false;
    }

    /**
     * Sama persis dengan KurikulumController::destroy.
     */
    public function deleteKurikulum()
    {
        $this->ensureAccess($this->kurikulum);

        $this->kurikulum->delete();

        session()->flash('status', 'Kurikulum dihapus.');

        return redirect()->route('admin.akademik.kurikulum');
    }

    public function render()
    {
        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.kurikulum.show')->extends('layouts.web');
    }
}
