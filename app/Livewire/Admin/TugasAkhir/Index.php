<?php

namespace App\Livewire\Admin\TugasAkhir;

use App\Models\Prodi;
use App\Models\Semester;
use App\Models\TugasAkhir;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    // Properti filter yang terikat <select> harus string, bukan ?int — lihat catatan skill soal
    // TypeError pada opsi kosong dari <select>.
    public string $filterProdi = '';

    public string $filterSemester = '';

    public string $filterStatus = '';

    public string $filterJenis = '';

    public int $perPage = 10;

    private const STATUSES = ['draft', 'submitted', 'approved', 'rejected', 'returned'];

    public function statusOptions(): array
    {
        return [
            'draft' => 'Draft',
            'submitted' => 'Terkirim',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'returned' => 'Dikembalikan',
        ];
    }

    public function jenisOptions(): array
    {
        return [
            'proposal' => 'Proposal',
            'akhir' => 'Skripsi / Tesis / TA',
        ];
    }

    public function mount(): void
    {
        $aktif = Semester::query()->where('is_active', true)->orderByDesc('id')->first();
        $this->filterSemester = $aktif ? (string) $aktif->id : '';
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

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterJenis(): void
    {
        $this->resetPage();
    }

    /**
     * Sama persis dengan TugasAkhirController::index.
     */
    public function render()
    {
        $query = TugasAkhir::query()
            ->with(['mahasiswa.prodi', 'semester'])
            ->orderByDesc('updated_at');

        $allowedProdiIds = null;
        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null) {
                $query->whereHas('mahasiswa', function ($q) use ($allowedProdiIds) {
                    $q->whereIn('id_prodi', $allowedProdiIds);
                });
            }
        }

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->whereHas('mahasiswa', function ($mq) {
                    $mq->where('nama', 'like', "%{$this->search}%")
                        ->orWhere('nim', 'like', "%{$this->search}%");
                })->orWhere('judul', 'like', "%{$this->search}%");
            });
        }

        if ($this->filterProdi !== '' && ($allowedProdiIds === null || in_array((int) $this->filterProdi, $allowedProdiIds, true))) {
            $prodiId = (int) $this->filterProdi;
            $query->whereHas('mahasiswa', function ($q) use ($prodiId) {
                $q->where('id_prodi', $prodiId);
            });
        }

        if ($this->filterSemester !== '') {
            $query->where('id_semester', (int) $this->filterSemester);
        }

        if ($this->filterStatus !== '' && in_array($this->filterStatus, self::STATUSES, true)) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterJenis === 'proposal') {
            $query->where('is_proposal', true);
        } elseif ($this->filterJenis === 'akhir') {
            $query->where('is_proposal', false);
        }

        $tugasAkhirList = $query->paginate($this->perPage);

        $prodiQuery = Prodi::query()->whereNull('deleted_at');
        if ($allowedProdiIds !== null) {
            $prodiQuery->whereIn('id', $allowedProdiIds);
        }

        return view('livewire.admin.tugas-akhir.index', [
            'tugasAkhirList' => $tugasAkhirList,
            'prodiOptions' => $prodiQuery->orderBy('nama')->get(['id', 'nama'])
                ->map(fn (Prodi $p) => (object) ['id' => $p->id, 'label' => $p->nama]),
            'semesterOptions' => Semester::orderByDesc('kode')->get(['id', 'kode', 'nama', 'is_active'])
                ->map(fn (Semester $s) => (object) [
                    'id' => $s->id,
                    'label' => $s->nama.' ('.$s->kode.')'.($s->is_active ? ' — aktif' : ''),
                ]),
        ])->extends('layouts.web');
    }
}
