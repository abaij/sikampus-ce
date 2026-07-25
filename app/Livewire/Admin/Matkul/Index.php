<?php

namespace App\Livewire\Admin\Matkul;

use App\Models\JenisMatkul;
use App\Models\Matkul;
use App\Models\Prodi;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    // #[Url] supaya state ini bisa dibaca ulang lewat query string ketika user kembali dari
    // halaman detail (lihat Show::mount()) — bukan cuma kosmetik alamat browser.
    #[Url(as: 'search')]
    public string $search = '';

    // Properti filter terikat <x-searchable-select>/<select> harus string, bukan ?int —
    // lihat catatan di SKILL.md soal TypeError pada opsi kosong.
    #[Url(as: 'id_prodi')]
    public string $filterProdi = '';

    #[Url(as: 'id_jenis_matkul')]
    public string $filterJenisMatkul = '';

    #[Url(as: 'semester')]
    public string $filterSemester = '';

    #[Url(as: 'status')]
    public string $filterStatus = '';

    public int $perPage = 10;

    public ?int $confirmingDeleteId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterProdi(): void
    {
        $this->resetPage();
    }

    public function updatingFilterJenisMatkul(): void
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

    public function confirmDelete(int $id): void
    {
        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    /**
     * Sama persis dengan MatkulController::destroy — scope-filter dicek ulang di sini.
     */
    public function delete(): void
    {
        if (! $this->confirmingDeleteId) {
            return;
        }

        $matkul = Matkul::findOrFail($this->confirmingDeleteId);

        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $matkul->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke mata kuliah ini.');
            }
        }

        $matkul->delete();

        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    /**
     * Sama persis dengan MatkulController::index.
     */
    public function render()
    {
        $query = Matkul::with(['prodi.jenjang', 'jenisMatkul'])->withCount('matkulPrasyaratLinks');

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

        if ($this->filterJenisMatkul !== '') {
            $query->where('id_jenis_matkul', (int) $this->filterJenisMatkul);
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

        $jenisMatkulOptions = JenisMatkul::whereNull('deleted_at')->orderBy('nama')->get(['id', 'nama', 'kode'])
            ->map(fn ($j) => (object) [
                'id' => $j->id,
                'label' => $j->kode ? "{$j->nama} ({$j->kode})" : $j->nama,
            ]);

        // Diselipkan ke link "Lihat" supaya tombol Kembali di halaman detail bisa mendarat
        // di halaman/filter yang sama persis — lihat Show::mount().
        $returnParams = array_filter([
            'search' => $this->search !== '' ? $this->search : null,
            'id_prodi' => $this->filterProdi !== '' ? $this->filterProdi : null,
            'id_jenis_matkul' => $this->filterJenisMatkul !== '' ? $this->filterJenisMatkul : null,
            'semester' => $this->filterSemester !== '' ? $this->filterSemester : null,
            'status' => $this->filterStatus !== '' ? $this->filterStatus : null,
            'page' => $matkulList->currentPage() > 1 ? $matkulList->currentPage() : null,
        ], fn ($value) => $value !== null);

        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.matkul.index', [
            'matkulList' => $matkulList,
            'prodiOptions' => $prodiOptions,
            'jenisMatkulOptions' => $jenisMatkulOptions,
            'returnQuery' => http_build_query($returnParams),
        ])->extends('layouts.web');
    }
}
