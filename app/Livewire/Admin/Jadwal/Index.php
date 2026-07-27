<?php

namespace App\Livewire\Admin\Jadwal;

use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Perkuliahan;
use App\Models\Prodi;
use App\Models\Semester;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    // #[Url] supaya state ini bisa dibaca ulang lewat query string ketika user kembali dari
    // halaman detail/ubah (lihat Jadwal\Concerns\ForwardsIndexState) — bukan cuma kosmetik alamat browser.
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
        // Sama seperti default filter semester di app/admin/jadwal/page.tsx: semester aktif
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
     * Sama persis dengan JadwalController::destroy — scope-filter dicek ulang di sini.
     */
    public function delete(): void
    {
        if (! $this->confirmingDeleteId) {
            return;
        }

        $jadwal = Jadwal::with('kelas')->findOrFail($this->confirmingDeleteId);

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $kelas = $jadwal->kelas ?? Kelas::find($jadwal->id_kelas);
            if ($kelas) {
                $allowedProdiIds = $user->getAllowedProdiIds();
                if ($allowedProdiIds !== null && ! in_array((int) $kelas->id_prodi, $allowedProdiIds, true)) {
                    abort(403, 'Anda tidak memiliki akses ke jadwal ini.');
                }
            }
        }

        $jadwal->delete();

        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    /**
     * Cocokkan baris perkuliahan dengan slot jadwal (prioritas sesi berlangsung, lalu terbaru).
     * Sama persis dengan JadwalController::findPerkuliahanForJadwalSlot.
     */
    private function findPerkuliahanForJadwalSlot(Jadwal $j, Collection $perkuliahanRows): ?Perkuliahan
    {
        $slotId = (int) $j->id;
        $candidates = $perkuliahanRows->filter(fn ($p) => (int) $p->id_jadwal === $slotId);

        $ts = static function (?Perkuliahan $p): int {
            if ($p === null || ! $p->waktu_mulai) {
                return 0;
            }

            return Carbon::parse($p->waktu_mulai)->getTimestamp();
        };

        $ongoing = $candidates
            ->filter(fn (Perkuliahan $p) => $p->waktu_mulai && ! $p->waktu_selesai)
            ->sortByDesc(fn (Perkuliahan $p) => $ts($p))
            ->first();

        if ($ongoing) {
            return $ongoing;
        }

        return $candidates
            ->sortByDesc(fn (Perkuliahan $p) => [$ts($p), $p->id])
            ->first();
    }

    /**
     * Sama persis dengan JadwalController::sesiStatusForPerkuliahan.
     *
     * @return array{sesi_status: string, sesi_status_label: string}
     */
    private function sesiStatusForPerkuliahan(?Perkuliahan $p): array
    {
        if ($p === null) {
            return ['sesi_status' => 'belum_mulai', 'sesi_status_label' => 'Belum ada sesi'];
        }

        $mulai = $p->waktu_mulai !== null && trim((string) $p->waktu_mulai) !== '';
        $selesai = $p->waktu_selesai !== null && trim((string) $p->waktu_selesai) !== '';

        if (! $mulai) {
            return ['sesi_status' => 'belum_mulai', 'sesi_status_label' => 'Belum dimulai'];
        }

        if (! $selesai) {
            return ['sesi_status' => 'sedang_berlangsung', 'sesi_status_label' => 'Sedang berlangsung'];
        }

        return ['sesi_status' => 'selesai', 'sesi_status_label' => 'Selesai'];
    }

    /**
     * Sama persis dengan JadwalController::index.
     */
    public function render()
    {
        $query = Jadwal::with([
            'kelas.kurikulumMatkul.matkul',
            'kelas.kurikulumMatkul.kurikulum',
            'kelas.prodi.jenjang',
            'kelas.semester',
            'jenisKuliah',
            'ruangan',
            'dosen.dosen',
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
                    ->orWhereHas('ruangan', function ($q) {
                        $q->where('nama', 'like', "%{$this->search}%");
                    })
                    ->orWhere('hari', 'like', "%{$this->search}%");
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
            $query->whereHas('kelas', function ($q) {
                $q->where('id_semester', (int) $this->filterSemester);
            });
        }

        $jadwalList = $query->orderBy('id_kelas')->orderBy('urutan_pertemuan')->paginate($this->perPage);

        $jadwalIds = $jadwalList->getCollection()->pluck('id')->filter()->values()->all();
        $perkuliahanRows = $jadwalIds === []
            ? collect()
            : Perkuliahan::whereIn('id_jadwal', $jadwalIds)->whereNull('deleted_at')->get();

        $jadwalList->getCollection()->each(function (Jadwal $jadwal) use ($perkuliahanRows) {
            $p = $this->findPerkuliahanForJadwalSlot($jadwal, $perkuliahanRows);
            $sesi = $this->sesiStatusForPerkuliahan($p);
            $jadwal->setAttribute('sesi_status', $sesi['sesi_status']);
            $jadwal->setAttribute('sesi_status_label', $sesi['sesi_status_label']);
        });

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
        // mendarat di halaman/filter yang sama persis — lihat Jadwal\Concerns\ForwardsIndexState.
        $returnParams = array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'id_prodi' => $this->filterProdi !== '' ? $this->filterProdi : null,
            'id_semester' => $this->filterSemester !== '' ? $this->filterSemester : null,
            'id_kelas' => $this->filterKelas !== '' ? $this->filterKelas : null,
            'page' => $jadwalList->currentPage() > 1 ? $jadwalList->currentPage() : null,
        ], fn ($value) => $value !== null);

        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.jadwal.index', [
            'jadwalList' => $jadwalList,
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
