<?php

namespace App\Livewire\Admin\Mahasiswa;

use App\Models\KelompokKelas;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\StatusAkademik;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterProdi = '';

    public string $filterKelompokKelas = '';

    public string $filterSemesterMasuk = '';

    public string $filterStatusAkademik = '';

    public int $perPage = 10;

    public function mount(): void
    {
        // Default filter status akademik "Aktif" — sama dengan perilaku MahasiswaPage di frontend.
        $aktif = StatusAkademik::query()
            ->whereRaw('LOWER(TRIM(nama)) = ?', ['aktif'])
            ->first();

        if ($aktif) {
            $this->filterStatusAkademik = (string) $aktif->id;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterProdi(): void
    {
        $this->resetPage();
    }

    public function updatingFilterKelompokKelas(): void
    {
        $this->resetPage();
    }

    public function updatingFilterSemesterMasuk(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatusAkademik(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function prodiOptions()
    {
        $query = Prodi::query()->with('jenjang')->orderBy('nama');

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereIn('id', $allowedProdiIds);
            }
        }

        return $query->get(['id', 'nama', 'kode', 'id_jenjang']);
    }

    #[Computed]
    public function kelompokKelasOptions()
    {
        return KelompokKelas::orderBy('nama')->get(['id', 'nama']);
    }

    #[Computed]
    public function semesterOptions()
    {
        return Semester::orderByDesc('kode')->get(['id', 'kode', 'nama']);
    }

    #[Computed]
    public function statusAkademikOptions()
    {
        return StatusAkademik::orderBy('nama')->get(['id', 'nama']);
    }

    /**
     * Sama persis dengan MahasiswaController::index — query + scope-filter disalin,
     * bukan diekstrak jadi shared service (mengikuti pola modul Fakultas/Dosen lainnya).
     */
    public function render()
    {
        $query = Mahasiswa::with(['prodi.jenjang', 'kelompok_kelas', 'semester_masuk', 'status_akademik']);

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereIn('id_prodi', $allowedProdiIds);
            }
        }

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('nim', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%");
            });
        }

        if ($this->filterProdi !== '') {
            $query->where('id_prodi', (int) $this->filterProdi);
        }

        if ($this->filterKelompokKelas !== '') {
            $query->where('id_kelompok_kelas', (int) $this->filterKelompokKelas);
        }

        if ($this->filterSemesterMasuk !== '') {
            $query->where('id_semester_masuk', (int) $this->filterSemesterMasuk);
        }

        if ($this->filterStatusAkademik !== '') {
            $query->where('id_status_akademik', (int) $this->filterStatusAkademik);
        }

        $mahasiswaList = $query->orderBy('nama')->paginate($this->perPage);

        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.mahasiswa.index', [
            'mahasiswaList' => $mahasiswaList,
        ])->extends('layouts.web');
    }
}
