<?php

namespace App\Livewire\Admin\StrukturBiaya;

use App\Models\KategoriBiaya;
use App\Models\KomponenBiaya;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\StrukturBiaya;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    // Properti filter terikat <x-searchable-select> tetap string (bukan ?int) mengikuti pola
    // Krs\Index — dicast ke int saat dipakai di query.
    public string $filterKategoriBiaya = '';

    public string $filterProdi = '';

    public string $filterAngkatan = '';

    public string $filterPeriode = '';

    public string $filterKomponenBiaya = '';

    public int $perPage = 10;

    public ?int $confirmingDeleteId = null;

    /**
     * Default filter periode = semester aktif, sama seperti default StrukturBiayaController::index
     * saat parameter id_periode tidak dikirim sama sekali.
     */
    public function mount(): void
    {
        $this->filterPeriode = (string) (Semester::where('is_active', true)->value('id') ?? '');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterKategoriBiaya(): void
    {
        $this->resetPage();
    }

    public function updatingFilterProdi(): void
    {
        $this->resetPage();
    }

    public function updatingFilterAngkatan(): void
    {
        $this->resetPage();
    }

    public function updatingFilterPeriode(): void
    {
        $this->resetPage();
    }

    public function updatingFilterKomponenBiaya(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<int>|null null = tanpa batasan scope
     */
    private function allowedProdiIds(): ?array
    {
        $user = Auth::user();
        if (! $user || ! $user->hasScopeRestriction()) {
            return null;
        }

        return $user->getAllowedProdiIds();
    }

    #[Computed]
    public function kategoriBiayaOptions(): array
    {
        return KategoriBiaya::orderBy('nama')->pluck('nama', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function prodiOptions(): array
    {
        $query = Prodi::query()->orderBy('nama');

        $allowedProdiIds = $this->allowedProdiIds();
        if ($allowedProdiIds !== null) {
            $query->whereIn('id', $allowedProdiIds);
        }

        return $query->pluck('nama', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function semesterOptions(): array
    {
        return Semester::orderByDesc('kode')
            ->get(['id', 'nama', 'kode'])
            ->mapWithKeys(fn ($s) => [$s->id => $s->kode ? "{$s->nama} ({$s->kode})" : $s->nama])
            ->all();
    }

    #[Computed]
    public function komponenBiayaOptions(): array
    {
        return KomponenBiaya::orderBy('nama')
            ->get(['id', 'nama', 'kode'])
            ->mapWithKeys(fn ($k) => [$k->id => "{$k->nama} ({$k->kode})"])
            ->all();
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    /**
     * Sama persis dengan StrukturBiayaController::destroy — cek scope prodi sebelum hapus.
     */
    public function delete(): void
    {
        if (! $this->confirmingDeleteId) {
            return;
        }

        $strukturBiaya = StrukturBiaya::findOrFail($this->confirmingDeleteId);

        $allowedProdiIds = $this->allowedProdiIds();
        if ($allowedProdiIds !== null && (! $strukturBiaya->id_prodi || ! in_array((int) $strukturBiaya->id_prodi, $allowedProdiIds, true))) {
            abort(403, 'Anda tidak memiliki akses ke struktur biaya ini.');
        }

        $strukturBiaya->delete();

        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    /**
     * Sama persis dengan StrukturBiayaController::index.
     */
    public function render()
    {
        $query = StrukturBiaya::with(['kategoriBiaya', 'prodi', 'angkatan', 'periode', 'komponenBiaya']);

        $allowedProdiIds = $this->allowedProdiIds();
        if ($allowedProdiIds !== null) {
            if ($allowedProdiIds === []) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereIn('id_prodi', $allowedProdiIds);
            }
        }

        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('kategoriBiaya', function ($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%")->orWhere('kode', 'like', "%{$search}%");
                })
                    ->orWhereHas('prodi', function ($q) use ($search) {
                        $q->where('nama', 'like', "%{$search}%")->orWhere('kode', 'like', "%{$search}%");
                    })
                    ->orWhereHas('angkatan', function ($q) use ($search) {
                        $q->where('nama', 'like', "%{$search}%")->orWhere('kode', 'like', "%{$search}%");
                    })
                    ->orWhereHas('periode', function ($q) use ($search) {
                        $q->where('nama', 'like', "%{$search}%")->orWhere('kode', 'like', "%{$search}%");
                    })
                    ->orWhereHas('komponenBiaya', function ($q) use ($search) {
                        $q->where('nama', 'like', "%{$search}%")->orWhere('kode', 'like', "%{$search}%");
                    });
            });
        }

        if ($this->filterKategoriBiaya !== '') {
            $query->where('id_kategori_biaya', (int) $this->filterKategoriBiaya);
        }

        if ($this->filterProdi !== '') {
            $query->where('id_prodi', (int) $this->filterProdi);
        }

        if ($this->filterAngkatan !== '') {
            $query->where('id_angkatan', (int) $this->filterAngkatan);
        }

        // filterPeriode kosong (sengaja dikosongkan user) berarti tampilkan semua periode —
        // beda dengan mount() yang mem-prefill dengan semester aktif, meniru perilaku
        // periodeShowAllRef di halaman frontend.
        if ($this->filterPeriode !== '') {
            $query->where('id_periode', (int) $this->filterPeriode);
        }

        if ($this->filterKomponenBiaya !== '') {
            $query->where('id_komponen_biaya', (int) $this->filterKomponenBiaya);
        }

        $strukturBiayaList = $query->orderByDesc('id_angkatan')
            ->orderByDesc('id_periode')
            ->orderBy('id_kategori_biaya')
            ->orderBy('id_prodi')
            ->orderBy('tahap')
            ->paginate($this->perPage);

        return view('livewire.admin.struktur-biaya.index', [
            'strukturBiayaList' => $strukturBiayaList,
        ])->extends('layouts.web');
    }
}
