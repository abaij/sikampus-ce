<?php

namespace App\Livewire\Admin\KonversiNilai;

use App\Models\Mahasiswa;
use App\Models\Prodi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterProdi = '';

    public int $perPage = 15;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterProdi(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function prodiOptions(): array
    {
        $query = Prodi::query()->with('jenjang')->orderBy('nama');

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereIn('id', $allowedProdiIds);
            }
        }

        return $query->get(['id', 'nama', 'kode'])
            ->mapWithKeys(fn ($prodi) => [$prodi->id => $prodi->kode ? "{$prodi->kode} — {$prodi->nama}" : $prodi->nama])
            ->all();
    }

    /**
     * Sama persis dengan KonversiNilaiController::ringkasanMahasiswa — mahasiswa yang punya
     * minimal satu baris konversi nilai, dengan agregat jumlah MK & total SKS lama/baru.
     */
    public function render()
    {
        $aggSub = DB::table('konversi_nilai')
            ->whereNull('deleted_at')
            ->select('id_mahasiswa')
            ->selectRaw('COUNT(*) as jumlah_matkul')
            ->selectRaw('COALESCE(SUM(sks_baru), 0) as total_sks_baru')
            ->selectRaw('COALESCE(SUM(sks_lama), 0) as total_sks_lama')
            ->groupBy('id_mahasiswa');

        $query = Mahasiswa::query()
            ->joinSub($aggSub, 'k_agg', 'mahasiswa.id', '=', 'k_agg.id_mahasiswa')
            ->select('mahasiswa.*')
            ->addSelect(['k_agg.jumlah_matkul', 'k_agg.total_sks_baru', 'k_agg.total_sks_lama'])
            ->with(['prodi:id,nama,kode']);

        $user = Auth::user();
        $filterProdi = $this->filterProdi;
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereIn('mahasiswa.id_prodi', $allowedProdiIds);
                if ($filterProdi !== '' && ! in_array((int) $filterProdi, $allowedProdiIds, true)) {
                    $filterProdi = '';
                }
            }
        }

        if ($this->search !== '') {
            $s = $this->search;
            $query->where(function ($q) use ($s) {
                $q->where('mahasiswa.nama', 'like', "%{$s}%")
                    ->orWhere('mahasiswa.nim', 'like', "%{$s}%");
            });
        }

        if ($filterProdi !== '') {
            $query->where('mahasiswa.id_prodi', (int) $filterProdi);
        }

        $konversiNilaiList = $query->orderBy('mahasiswa.nama')->paginate($this->perPage);

        return view('livewire.admin.konversi-nilai.index', [
            'konversiNilaiList' => $konversiNilaiList,
        ])->extends('layouts.web');
    }
}
