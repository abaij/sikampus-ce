<?php

namespace App\Livewire\Prodi\Matkul;

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

    #[Url(as: 'semester')]
    public string $filterSemester = '';

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

    public function updatingFilterSemester(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    /**
     * Sama persis dengan MatkulController::index — rute /prodi/matkul ini read-only, tidak ada
     * store/update/destroy/import di grup route prodi (bandingkan dengan Admin\Matkul\Index yang
     * punya tombol Tambah/Ubah/Hapus). Filter id_jenis_matkul tidak diikutkan karena
     * app/prodi/matkul/page.tsx tidak menyediakan filter itu di UI, meski API-nya mendukung.
     */
    public function render()
    {
        $query = Matkul::with(['prodi.jenjang', 'jenisMatkul']);

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
                    ->orWhere('nama', 'like', "%{$this->search}%")
                    ->orWhere('nama_en', 'like', "%{$this->search}%");
            });
        }

        if ($this->filterProdi !== '') {
            $query->where('id_prodi', (int) $this->filterProdi);
        }

        if ($this->filterSemester !== '') {
            $query->where('semester', (int) $this->filterSemester);
        }

        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }

        $matkulList = $query->orderBy('kode')->paginate($this->perPage);

        $prodiOptions = Prodi::with('jenjang')->whereNull('deleted_at');
        if ($allowedProdiIds !== null) {
            $prodiOptions->whereIn('id', $allowedProdiIds);
        }
        $prodiOptions = $prodiOptions->orderBy('nama')->get()->map(fn ($p) => (object) [
            'id' => $p->id,
            'label' => $p->jenjang?->kode ? "{$p->nama} ({$p->jenjang->kode})" : $p->nama,
        ]);

        // Diselipkan ke link "Lihat" supaya tombol Kembali di halaman detail mendarat di
        // halaman/filter yang sama.
        $returnParams = array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'id_prodi' => $this->filterProdi !== '' ? $this->filterProdi : null,
            'semester' => $this->filterSemester !== '' ? $this->filterSemester : null,
            'status' => $this->filterStatus !== '' ? $this->filterStatus : null,
            'page' => $matkulList->currentPage() > 1 ? $matkulList->currentPage() : null,
        ], fn ($value) => $value !== null);

        return view('livewire.prodi.matkul.index', [
            'matkulList' => $matkulList,
            'prodiOptions' => $prodiOptions,
            'returnQuery' => http_build_query($returnParams),
        ])->extends('layouts.prodi');
    }
}
