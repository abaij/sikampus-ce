<?php

namespace App\Livewire\Prodi\Kurikulum;

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
    use WithPagination;

    public int $kurikulumId;

    public Kurikulum $kurikulum;

    public string $backUrl;

    public string $matkulSearch = '';

    public int $matkulPerPage = 10;

    /** Id baris kurikulum_matkul yang sedang dibuka di modal detail. */
    public ?int $detailMatkulId = null;

    public bool $showBobotForm = false;

    /** @var array<int, string> [id_jenis_penilaian => bobot] untuk form "Kelola Bobot Penilaian". */
    public array $bobotForm = [];

    public bool $showAutoFillConfirm = false;

    public function mount(int $id): void
    {
        $this->kurikulumId = $id;

        $kurikulum = Kurikulum::with(['prodi.jenjang', 'tahunBerlaku', 'matkuls'])->findOrFail($id);

        $this->ensureAccess($kurikulum);

        $this->kurikulum = $kurikulum;

        $forwarded = collect(request()->query())
            ->only(['search', 'id_prodi', 'status', 'page'])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
        $this->backUrl = $forwarded === [] ? route('prodi.kurikulum') : route('prodi.kurikulum').'?'.http_build_query($forwarded);
    }

    /**
     * Bukan properti biasa — lihat catatan yang sama di Admin\Kurikulum\Show::totalSksKurikulum.
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
     * Sama persis dengan Admin\Kurikulum\Show::matkulList.
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
     * Sama persis dengan Admin\Kurikulum\Show::matkulBobotTotals.
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
     * Dibatasi ke kurikulum ini — lihat catatan yang sama di Admin\Kurikulum\Show::detailMatkul.
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
     * Isi form dengan bobot yang sudah tersimpan (atau 0 kalau belum ada) untuk setiap jenis
     * penilaian aktif — sama persis dengan Admin\Kurikulum\Show::openBobotForm.
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
     * Sama persis dengan KurikulumMatkulController::updateBobotPenilaian (rute
     * PUT /prodi/kurikulum-matkul/{id}/bobot-penilaian).
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
            // Hapus fisik agar unique (id_kurikulum_matkul, id_jenis_penilaian) tidak bentrok saat
            // create baru — sama seperti controller API.
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

        unset($this->detailMatkul, $this->matkulBobotTotals);
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
     * Terapkan bobot default dari master jenis penilaian ke mata kuliah ini — memakai endpoint
     * yang sama dengan "Kelola Bobot Penilaian" (replace penuh), hanya sumber datanya beda. Sama
     * persis dengan Admin\Kurikulum\Show::confirmAutoFill.
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

        unset($this->detailMatkul, $this->matkulBobotTotals);
        $this->showAutoFillConfirm = false;
        session()->flash('status', 'Bobot penilaian berhasil diterapkan dari default jenis penilaian.');
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

    public function render()
    {
        return view('livewire.prodi.kurikulum.show')->extends('layouts.prodi');
    }
}
