<?php

namespace App\Livewire\Admin\JadwalUjian;

use App\Models\Kelas;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\Ujian;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    // #[Url] supaya state ini bisa dibaca ulang lewat query string ketika user kembali dari
    // halaman detail/ubah (lihat JadwalUjian\Concerns\ForwardsIndexState) — bukan cuma kosmetik
    // alamat browser.
    #[Url(as: 'search')]
    public string $search = '';

    // Properti filter yang terikat <select> harus string, bukan ?int — lihat catatan di SKILL.md.
    #[Url(as: 'id_prodi')]
    public string $filterProdi = '';

    #[Url(as: 'id_semester')]
    public string $filterSemester = '';

    #[Url(as: 'id_kelas')]
    public string $filterKelas = '';

    public int $perPage = 10;

    public ?int $confirmingDeleteId = null;

    public function mount(): void
    {
        // Sama seperti default filter semester di app/admin/jadwal-ujian/page.tsx: semester aktif
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
        // Opsi "Kelas" ikut disaring oleh prodi (lihat render()) — nilai lama yang mungkin
        // sudah tidak relevan untuk prodi baru harus dibuang.
        $this->filterKelas = '';
        $this->resetPage();
    }

    public function updatingFilterSemester(): void
    {
        $this->filterKelas = '';
        $this->resetPage();
    }

    public function updatingFilterKelas(): void
    {
        $this->resetPage();
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
     * Sama persis dengan UjianController::destroy — scope-filter dicek ulang di sini.
     */
    public function delete(): void
    {
        if (! $this->confirmingDeleteId) {
            return;
        }

        $ujian = Ujian::findOrFail($this->confirmingDeleteId);

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $kelas = Kelas::withTrashed()->find((int) $ujian->id_kelas);
                if ($kelas && ! in_array((int) $kelas->id_prodi, $allowedProdiIds, true)) {
                    abort(403, 'Anda tidak memiliki akses ke jadwal ujian ini.');
                }
            }
        }

        $actor = $user ? ((string) ($user->name ?? $user->id)) : 'system';
        $ujian->update(['deleted_by' => $actor]);
        $ujian->delete();

        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    /**
     * Sama persis dengan UjianController::index.
     */
    public function render()
    {
        $query = Ujian::with([
            'kelas.kurikulumMatkul.matkul',
            'kelas.kurikulumMatkul.kurikulum',
            'kelas.prodi.jenjang',
            'kelas.semester',
            'semester',
            'ruangan',
        ])->whereHas('kelas', function ($q) {
            $q->whereNull('deleted_at');
        });

        $user = Auth::user();
        $prodiId = $this->filterProdi !== '' ? (int) $this->filterProdi : null;

        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereHas('kelas', function ($q) use ($allowedProdiIds) {
                    $q->whereIn('id_prodi', $allowedProdiIds);
                });
                if ($prodiId && ! in_array($prodiId, $allowedProdiIds, true)) {
                    $prodiId = null;
                }
            }
        }

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->whereHas('kelas.kurikulumMatkul.matkul', function ($q) {
                    $q->where('nama', 'like', "%{$this->search}%")
                        ->orWhere('kode', 'like', "%{$this->search}%");
                })
                    ->orWhereHas('kelas.kurikulumMatkul', function ($q) {
                        $q->where('nama_matkul', 'like', "%{$this->search}%")
                            ->orWhere('kode_matkul', 'like', "%{$this->search}%");
                    });
            });
        }

        if ($prodiId) {
            $query->whereHas('kelas', function ($q) use ($prodiId) {
                $q->where('id_prodi', $prodiId);
            });
        }

        if ($this->filterKelas !== '') {
            $query->where('id_kelas', (int) $this->filterKelas);
        }

        if ($this->filterSemester !== '') {
            $query->where('id_semester', (int) $this->filterSemester);
        }

        $ujianList = $query
            ->orderByDesc('tanggal_mulai')
            ->orderBy('id_kelas')
            ->orderBy('jenis_ujian')
            ->paginate($this->perPage);

        $prodiQuery = Prodi::with('jenjang')->whereNull('deleted_at');
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $prodiQuery->whereIn('id', $allowedProdiIds);
            }
        }

        // Opsi "Kelas" mengikuti prodi/semester yang dipilih; kalau belum ada, tampilkan semua
        // (dibatasi 200 baris supaya dropdown tetap ringan).
        $kelasQuery = Kelas::with(['kurikulumMatkul.matkul', 'semester'])->whereNull('deleted_at');
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $kelasQuery->whereIn('id_prodi', $allowedProdiIds);
            }
        }
        if ($prodiId) {
            $kelasQuery->where('id_prodi', $prodiId);
        }
        if ($this->filterSemester !== '') {
            $kelasQuery->where('id_semester', (int) $this->filterSemester);
        }

        // Diselipkan ke link "Lihat"/"Ubah" supaya tombol Kembali di halaman detail/ubah bisa
        // mendarat di halaman/filter yang sama persis — lihat JadwalUjian\Concerns\ForwardsIndexState.
        $returnParams = array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'id_prodi' => $this->filterProdi !== '' ? $this->filterProdi : null,
            'id_semester' => $this->filterSemester !== '' ? $this->filterSemester : null,
            'id_kelas' => $this->filterKelas !== '' ? $this->filterKelas : null,
            'page' => $ujianList->currentPage() > 1 ? $ujianList->currentPage() : null,
        ], fn ($value) => $value !== null);

        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.jadwal-ujian.index', [
            'ujianList' => $ujianList,
            'prodiOptions' => $prodiQuery->orderBy('nama')->get()->map(fn (Prodi $p) => (object) [
                'id' => $p->id,
                'label' => $p->jenjang?->kode ? "{$p->nama} ({$p->jenjang->kode})" : $p->nama,
            ]),
            'semesterOptions' => Semester::whereNull('deleted_at')->orderByDesc('kode')->get(['id', 'kode', 'nama'])
                ->map(fn (Semester $s) => (object) ['id' => $s->id, 'label' => "{$s->nama} ({$s->kode})"]),
            'kelasOptions' => $kelasQuery->orderBy('id')->limit(200)->get()->map(fn (Kelas $k) => (object) [
                'id' => $k->id,
                'label' => trim(($k->kurikulumMatkul?->matkul?->kode ? "{$k->kurikulumMatkul->matkul->kode} - " : '').($k->kurikulumMatkul?->matkul?->nama ?? 'Kelas').($k->semester ? " ({$k->semester->nama} {$k->semester->kode})" : '')),
            ]),
            'returnQuery' => http_build_query($returnParams),
        ])->extends('layouts.web');
    }
}
