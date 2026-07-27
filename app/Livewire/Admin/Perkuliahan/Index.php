<?php

namespace App\Livewire\Admin\Perkuliahan;

use App\Models\Kelas;
use App\Models\Prodi;
use App\Models\Semester;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    // #[Url] supaya state ini bisa dibaca ulang lewat query string ketika user kembali dari
    // halaman detail — lihat Perkuliahan\Concerns\ForwardsIndexState.
    #[Url(as: 'search')]
    public string $search = '';

    // Properti filter yang terikat <select> harus string, bukan ?int — lihat catatan di SKILL.md.
    #[Url(as: 'id_prodi')]
    public string $filterProdi = '';

    #[Url(as: 'id_semester')]
    public string $filterSemester = '';

    public int $perPage = 10;

    public function mount(): void
    {
        // Sama seperti default filter semester di app/admin/perkuliahan/page.tsx: semester aktif
        // ter-pilih otomatis — tapi hanya kalau belum ada filter dari query string.
        if ($this->filterSemester !== '') {
            return;
        }

        $semesterAktif = Semester::where('is_active', true)->first();
        if ($semesterAktif) {
            $this->filterSemester = (string) $semesterAktif->id;
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

    public function updatingFilterSemester(): void
    {
        $this->resetPage();
    }

    /**
     * Sama persis dengan KelasController::index (dipakai juga oleh modul Kelas) — modul ini
     * hanya menambahkan withCount('jadwal') dan kolom aksi Kehadiran, tanpa CRUD.
     */
    public function render()
    {
        $query = Kelas::with([
            'kurikulumMatkul.matkul',
            'kurikulumMatkul.kurikulum',
            'prodi.jenjang',
            'semester',
            'dosenPic',
        ])->withCount('jadwal');

        $user = Auth::user();
        $prodiId = $this->filterProdi !== '' ? (int) $this->filterProdi : null;

        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereIn('id_prodi', $allowedProdiIds);
                if ($prodiId && ! in_array($prodiId, $allowedProdiIds, true)) {
                    $prodiId = null;
                }
            }
        }

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->whereHas('kurikulumMatkul.matkul', function ($q) {
                    $q->where('nama', 'like', "%{$this->search}%")
                        ->orWhere('kode', 'like', "%{$this->search}%");
                })
                    ->orWhereHas('prodi', function ($q) {
                        $q->where('nama', 'like', "%{$this->search}%")
                            ->orWhere('kode', 'like', "%{$this->search}%");
                    })
                    ->orWhereHas('dosenPic', function ($q) {
                        $q->where('nama', 'like', "%{$this->search}%")
                            ->orWhere('kode_dosen', 'like', "%{$this->search}%");
                    });
            });
        }

        if ($prodiId) {
            $query->where('id_prodi', $prodiId);
        }

        if ($this->filterSemester !== '') {
            $query->where('id_semester', (int) $this->filterSemester);
        }

        $kelasList = $query->orderBy('id')->paginate($this->perPage);

        $prodiQuery = Prodi::with('jenjang')->whereNull('deleted_at');
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $prodiQuery->whereIn('id', $allowedProdiIds);
            }
        }

        // Diselipkan ke link "Kehadiran" supaya tombol Kembali di halaman detail bisa mendarat
        // di halaman/filter yang sama persis — lihat Perkuliahan\Concerns\ForwardsIndexState.
        $returnParams = array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'id_prodi' => $this->filterProdi !== '' ? $this->filterProdi : null,
            'id_semester' => $this->filterSemester !== '' ? $this->filterSemester : null,
            'page' => $kelasList->currentPage() > 1 ? $kelasList->currentPage() : null,
        ], fn ($value) => $value !== null);

        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.perkuliahan.index', [
            'kelasList' => $kelasList,
            'prodiOptions' => $prodiQuery->orderBy('nama')->get()->map(fn (Prodi $p) => (object) [
                'id' => $p->id,
                'label' => $p->jenjang?->kode ? "{$p->nama} ({$p->jenjang->kode})" : $p->nama,
            ]),
            'semesterOptions' => Semester::whereNull('deleted_at')->orderByDesc('kode')->get(['id', 'kode', 'nama'])
                ->map(fn (Semester $s) => (object) ['id' => $s->id, 'label' => "{$s->nama} ({$s->kode})"]),
            'returnQuery' => http_build_query($returnParams),
        ])->extends('layouts.web');
    }
}
