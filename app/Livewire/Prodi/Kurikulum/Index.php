<?php

namespace App\Livewire\Prodi\Kurikulum;

use App\Models\Kurikulum;
use App\Models\Matkul;
use App\Models\Prodi;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url(as: 'search')]
    public string $search = '';

    // Properti filter terikat <x-searchable-select> harus string, bukan ?int — lihat catatan di
    // SKILL.md soal TypeError pada opsi kosong.
    #[Url(as: 'id_prodi')]
    public string $filterProdi = '';

    #[Url(as: 'status')]
    public string $filterStatus = '';

    public int $perPage = 10;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterProdi(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    /**
     * Sama persis dengan KurikulumController::index — rute /prodi/kurikulum ini read-only, tidak
     * ada store/update/destroy di grup route prodi (bandingkan dengan Admin\Kurikulum\Index yang
     * punya tombol Tambah/Ubah/Hapus).
     */
    public function render()
    {
        $query = Kurikulum::with(['prodi.jenjang', 'tahunBerlaku', 'matkuls']);

        $user = Auth::user();
        $allowedProdiIds = null;
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereIn('id_prodi', $allowedProdiIds);
            }
        }

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('kode', 'like', "%{$this->search}%")
                    ->orWhere('nama', 'like', "%{$this->search}%");
            });
        }

        if ($this->filterProdi !== '') {
            $query->where('id_prodi', (int) $this->filterProdi);
        }

        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }

        $kurikulumList = $query->orderBy('kode')->paginate($this->perPage);

        $kurikulumList->getCollection()->transform(function (Kurikulum $kurikulum) {
            $kurikulum->total_sks_kurikulum = (int) $kurikulum->matkuls->sum(fn (Matkul $matkul) => (int) ($matkul->pivot->sks ?? $matkul->sks ?? 0));

            return $kurikulum;
        });

        $prodiOptions = Prodi::with('jenjang')->whereNull('deleted_at');
        if ($allowedProdiIds !== null) {
            $prodiOptions->whereIn('id', $allowedProdiIds);
        }
        $prodiOptions = $prodiOptions->orderBy('nama')->get()->map(fn ($p) => (object) [
            'id' => $p->id,
            'label' => $p->jenjang?->kode ? "{$p->nama} ({$p->jenjang->kode})" : $p->nama,
        ]);

        // Diselipkan ke link "Lihat" supaya tombol Kembali mendarat di halaman/filter yang sama.
        $returnParams = array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'id_prodi' => $this->filterProdi !== '' ? $this->filterProdi : null,
            'status' => $this->filterStatus !== '' ? $this->filterStatus : null,
            'page' => $kurikulumList->currentPage() > 1 ? $kurikulumList->currentPage() : null,
        ], fn ($value) => $value !== null);

        return view('livewire.prodi.kurikulum.index', [
            'kurikulumList' => $kurikulumList,
            'prodiOptions' => $prodiOptions,
            'returnQuery' => http_build_query($returnParams),
        ])->extends('layouts.prodi');
    }
}
