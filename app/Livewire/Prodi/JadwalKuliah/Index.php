<?php

namespace App\Livewire\Prodi\JadwalKuliah;

use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Semester;
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
    #[Url(as: 'id_semester')]
    public string $filterSemester = '';

    #[Url(as: 'id_kelas')]
    public string $filterKelas = '';

    #[Url(as: 'hari')]
    public string $filterHari = '';

    public int $perPage = 10;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterSemester(): void
    {
        // Opsi "Kelas" ikut disaring oleh semester (lihat render()) — nilai lama yang mungkin
        // sudah tidak relevan untuk semester baru harus dibuang, sama seperti FE (filterKelas
        // direset setiap kali daftar opsi kelas dimuat ulang).
        $this->filterKelas = '';
        $this->resetPage();
    }

    public function updatingFilterKelas(): void
    {
        $this->resetPage();
    }

    public function updatingFilterHari(): void
    {
        $this->resetPage();
    }

    /**
     * Sama persis dengan JadwalController::indexProdi — rute /prodi/jadwal-kuliah ini read-only
     * (tidak ada store/update/destroy di grup route prodi), jadi tidak ada aksi apa pun per baris.
     */
    public function render()
    {
        $user = Auth::user();
        $allowedProdiIds = $user && $user->hasScopeRestriction() ? $user->getAllowedProdiIds() : null;

        if ($allowedProdiIds === null || $allowedProdiIds === []) {
            $jadwalList = Jadwal::whereRaw('1 = 0')->paginate($this->perPage);
            $kelasOptions = collect();
        } else {
            $query = Jadwal::with([
                'kelas.kurikulumMatkul.matkul',
                'kelas.kurikulumMatkul.kurikulum',
                'kelas.prodi',
                'kelas.semester',
                'jenisKuliah',
                'ruangan',
                'dosen.dosen',
            ])->whereHas('kelas', function ($q) use ($allowedProdiIds) {
                $q->whereNull('deleted_at')->whereIn('id_prodi', $allowedProdiIds);
            });

            if ($this->filterSemester !== '') {
                $query->whereHas('kelas', function ($q) {
                    $q->where('id_semester', (int) $this->filterSemester);
                });
            }

            if ($this->filterKelas !== '') {
                $query->where('id_kelas', (int) $this->filterKelas);
            }

            if ($this->filterHari !== '') {
                $query->where('hari', $this->filterHari);
            }

            if ($this->search !== '') {
                $query->whereHas('kelas.kurikulumMatkul.matkul', function ($q) {
                    $q->where('nama', 'like', "%{$this->search}%")
                        ->orWhere('kode', 'like', "%{$this->search}%");
                });
            }

            $jadwalList = $query->orderBy('id_kelas')->orderBy('urutan_pertemuan')->paginate($this->perPage);

            // Sama persis dengan JadwalController::getKelasOptionsProdi.
            $kelasQuery = Kelas::with(['kurikulumMatkul.matkul', 'kurikulumMatkul.kurikulum', 'prodi', 'semester'])
                ->whereIn('id_prodi', $allowedProdiIds);
            if ($this->filterSemester !== '') {
                $kelasQuery->where('id_semester', (int) $this->filterSemester);
            }
            $kelasOptions = $kelasQuery->orderBy('id')->limit(100)->get();
        }

        return view('livewire.prodi.jadwal-kuliah.index', [
            'jadwalList' => $jadwalList,
            'semesterOptions' => Semester::orderByDesc('kode')->limit(100)->get(['id', 'kode', 'nama'])
                ->map(fn (Semester $s) => (object) ['id' => $s->id, 'label' => "{$s->kode} - {$s->nama}"]),
            'kelasOptions' => $kelasOptions->map(fn (Kelas $k) => (object) [
                'id' => $k->id,
                'label' => trim(($k->kurikulumMatkul?->matkul?->nama ?? $k->kurikulumMatkul?->matkul?->kode ?? 'Kelas').($k->semester?->kode ? " • {$k->semester->kode}" : '')),
            ]),
        ])->extends('layouts.prodi');
    }
}
