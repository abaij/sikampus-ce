<?php

namespace App\Livewire\Prodi\Mahasiswa;

use App\Models\KelompokKelas;
use App\Models\Mahasiswa;
use App\Models\Semester;
use App\Models\StatusAkademik;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
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
    #[Url(as: 'id_semester_masuk')]
    public string $filterSemesterMasuk = '';

    // Frontend (app/prodi/mahasiswa/page.tsx) menyebutnya "Kelas (Grup)" dan mengirim param
    // id_grup_mahasiswa, tapi MahasiswaController::index sebenarnya membaca id_kelompok_kelas —
    // param id_grup_mahasiswa yang dikirim FE diam-diam tidak pernah dipakai controller, jadi
    // filter itu di frontend sebenarnya tidak berfungsi. Sama seperti Admin\Mahasiswa\Index
    // (satu-satunya mirror lain dari controller yang sama di panel ini), dipakai id_kelompok_kelas
    // yang benar-benar berfungsi, bukan disalin apa adanya dari nama parameter FE yang keliru.
    #[Url(as: 'id_kelompok_kelas')]
    public string $filterKelompokKelas = '';

    #[Url(as: 'id_status_akademik')]
    public string $filterStatusAkademik = '';

    public int $perPage = 10;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterSemesterMasuk(): void
    {
        $this->resetPage();
    }

    public function updatingFilterKelompokKelas(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatusAkademik(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function semesterOptions()
    {
        return Semester::orderByDesc('kode')->limit(100)->get(['id', 'kode', 'nama']);
    }

    #[Computed]
    public function kelompokKelasOptions()
    {
        return KelompokKelas::orderBy('nama')->limit(100)->get(['id', 'nama']);
    }

    #[Computed]
    public function statusAkademikOptions()
    {
        return StatusAkademik::orderBy('nama')->limit(100)->get(['id', 'nama']);
    }

    /**
     * Sama persis dengan MahasiswaController::index — rute /prodi/mahasiswa ini read-only, tidak
     * ada store/update/destroy di grup route prodi. Filter id_prodi tidak diikutkan karena
     * app/prodi/mahasiswa/page.tsx tidak menyediakan filter itu di UI (scope sudah otomatis
     * membatasi ke prodi kaprodi/sekprodi yang login).
     */
    public function render()
    {
        $query = Mahasiswa::with(['prodi', 'kelompok_kelas', 'semester_masuk', 'status_akademik']);

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

        if ($this->filterSemesterMasuk !== '') {
            $query->where('id_semester_masuk', (int) $this->filterSemesterMasuk);
        }

        if ($this->filterKelompokKelas !== '') {
            $query->where('id_kelompok_kelas', (int) $this->filterKelompokKelas);
        }

        if ($this->filterStatusAkademik !== '') {
            $query->where('id_status_akademik', (int) $this->filterStatusAkademik);
        }

        $mahasiswaList = $query->orderBy('nama')->paginate($this->perPage);

        return view('livewire.prodi.mahasiswa.index', [
            'mahasiswaList' => $mahasiswaList,
        ])->extends('layouts.prodi');
    }
}
