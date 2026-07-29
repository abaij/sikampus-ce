<?php

namespace App\Livewire\Dosen\Krs;

use App\Models\Dosen;
use App\Models\DosenWali;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Notifikasi;
use App\Models\Semester;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    // Locked: approveSelected() memakai dosenId sebagai subjek pengecekan kepemilikan DosenWali —
    // tanpa ini, dosenId bisa "disentuh" lewat request Livewire yang dimanipulasi untuk
    // menyetujui KRS mahasiswa bimbingan dosen lain.
    #[Locked]
    public int $dosenId;

    public string $filterSemester = '';

    public string $search = '';

    public ?int $viewingMahasiswaId = null;

    public ?string $viewingMahasiswaLabel = null;

    public bool $loadingKrs = false;

    /** @var array<int, int> */
    public array $selectedKrsIds = [];

    public function mount(): void
    {
        $dosen = Dosen::where('id_user', Auth::id())->firstOrFail();
        $this->dosenId = $dosen->id;

        $activeSemester = Semester::where('is_active', true)->whereNull('deleted_at')->first();
        $this->filterSemester = $activeSemester ? (string) $activeSemester->id : '';
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterSemester(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function semesterOptions(): array
    {
        return Semester::whereNull('deleted_at')
            ->orderByDesc('kode')
            ->get(['id', 'nama', 'kode'])
            ->mapWithKeys(fn (Semester $s) => [$s->id => $s->kode ? "{$s->nama} ({$s->kode})" : $s->nama])
            ->all();
    }

    /**
     * Sama persis dengan KrsController::getMahasiswaBimbingan (statistik_krs dihitung per
     * mahasiswa untuk semester yang dipilih), dipaginasi lewat Livewire WithPagination.
     */
    #[Computed]
    public function rows()
    {
        $query = DosenWali::where('id_dosen', $this->dosenId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->with(['mahasiswa.prodi.jenjang', 'mahasiswa.semester_masuk']);

        if ($this->search !== '') {
            $query->whereHas('mahasiswa', function ($q) {
                $q->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('nim', 'like', "%{$this->search}%");
            });
        }

        $paginated = $query->orderByDesc('created_at')->paginate(10);

        $semesterId = $this->filterSemester !== '' ? (int) $this->filterSemester : null;

        $paginated->getCollection()->transform(function (DosenWali $dosenWali) use ($semesterId) {
            $mahasiswa = $dosenWali->mahasiswa;

            $krsQuery = Krs::where('id_mahasiswa', $mahasiswa->id)->whereNull('deleted_at');
            if ($semesterId) {
                $krsQuery->whereHas('kelas', fn ($q) => $q->where('id_semester', $semesterId));
            }
            $krsList = $krsQuery->with('kelas.kurikulumMatkul.matkul:id,sks')->get();

            $total = $krsList->count();
            $diacc = $krsList->whereNotNull('approved_at')->count();

            $mahasiswa->setAttribute('statistik_krs', [
                'total' => $total,
                'diacc' => $diacc,
                'persentase_diacc' => $total > 0 ? round(($diacc / $total) * 100, 2) : 0,
            ]);

            return $mahasiswa;
        });

        return $paginated;
    }

    /**
     * Sama persis dengan KrsController::getKrsPending (mode=all) — semua KRS mahasiswa untuk
     * semester yang dipilih, disetujui maupun belum.
     */
    public function openKrsModal(int $idMahasiswa): void
    {
        $dosenWali = DosenWali::where('id_dosen', $this->dosenId)
            ->where('id_mahasiswa', $idMahasiswa)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->with('mahasiswa')
            ->first();

        abort_unless($dosenWali, 403, 'Mahasiswa bukan bimbingan Anda.');

        $this->viewingMahasiswaId = $idMahasiswa;
        $this->viewingMahasiswaLabel = trim(($dosenWali->mahasiswa->nim ?? '').' ('.($dosenWali->mahasiswa->nama ?? '').')');
        $this->selectedKrsIds = [];
    }

    public function closeKrsModal(): void
    {
        $this->viewingMahasiswaId = null;
        $this->viewingMahasiswaLabel = null;
        $this->selectedKrsIds = [];
    }

    #[Computed]
    public function krsPending(): array
    {
        if (! $this->viewingMahasiswaId) {
            return [];
        }

        $semesterId = $this->filterSemester !== '' ? (int) $this->filterSemester : null;

        $krsQuery = Krs::with([
            'kelas.kurikulumMatkul.matkul',
            'kelas.prodi',
            'kelas.semester',
            'kelas.dosenPic',
        ])
            ->where('id_mahasiswa', $this->viewingMahasiswaId)
            ->whereNull('deleted_at');

        if ($semesterId) {
            $krsQuery->whereHas('kelas', fn ($q) => $q->where('id_semester', $semesterId));
        }

        return $krsQuery->orderByDesc('created_at')->get()->map(function (Krs $krs) {
            $km = $krs->kelas->kurikulumMatkul;

            return [
                'id' => $krs->id,
                'kode_matkul' => $km?->kodeMatkulLabel(),
                'nama_matkul' => $km?->namaMatkulLabel(),
                'sks' => $km?->sksLabel() ?? 0,
                'semester' => $krs->kelas->semester,
                'dosen_pic' => $krs->kelas->dosenPic,
                'prodi' => $krs->kelas->prodi,
                'is_approved' => $krs->approved_at !== null,
            ];
        })->all();
    }

    #[Computed]
    public function krsPendingOnlyIds(): array
    {
        return collect($this->krsPending)->where('is_approved', false)->pluck('id')->all();
    }

    public function toggleSelectAll(): void
    {
        $pendingIds = $this->krsPendingOnlyIds;

        $this->selectedKrsIds = count($this->selectedKrsIds) === count($pendingIds) ? [] : $pendingIds;
    }

    public function toggleKrsSelection(int $krsId): void
    {
        $this->selectedKrsIds = in_array($krsId, $this->selectedKrsIds, true)
            ? array_values(array_diff($this->selectedKrsIds, [$krsId]))
            : [...$this->selectedKrsIds, $krsId];
    }

    /**
     * Sama persis dengan KrsController::approveKrs — verifikasi ulang tiap KRS memang bimbingan
     * dosen ini, lalu kirim notifikasi ke mahasiswa yang KRS-nya benar-benar berubah status.
     */
    public function approveSelected(): void
    {
        if (empty($this->selectedKrsIds)) {
            return;
        }

        $dosen = Dosen::findOrFail($this->dosenId);
        $krsIds = $this->selectedKrsIds;

        DB::transaction(function () use ($dosen, $krsIds) {
            $krsList = Krs::whereIn('id', $krsIds)->whereNull('deleted_at')->get();

            foreach ($krsList as $krs) {
                $allowed = DosenWali::where('id_dosen', $dosen->id)
                    ->where('id_mahasiswa', $krs->id_mahasiswa)
                    ->where('status', 'active')
                    ->whereNull('deleted_at')
                    ->exists();

                abort_unless($allowed, 403, 'Salah satu KRS bukan bimbingan Anda.');
            }

            $idMahasiswaBaruDisetujui = $krsList->whereNull('approved_at')->pluck('id_mahasiswa')->unique();

            Krs::whereIn('id', $krsIds)->whereNull('approved_at')->update([
                'approved_by' => Auth::user()->name ?? Auth::user()->email,
                'approved_at' => now(),
            ]);

            $idUserPerMahasiswa = Mahasiswa::whereIn('id', $idMahasiswaBaruDisetujui)->whereNotNull('id_user')->pluck('id_user');
            foreach ($idUserPerMahasiswa as $idUser) {
                Notifikasi::kirim(
                    idUser: $idUser,
                    tipe: 'krs_disetujui',
                    judul: 'KRS disetujui',
                    pesan: 'KRS Anda sudah disetujui dosen wali.',
                    url: '/mahasiswa/krs',
                );
            }
        });

        $approvedCount = count($this->selectedKrsIds);
        $this->selectedKrsIds = [];
        unset($this->krsPending, $this->krsPendingOnlyIds, $this->rows);

        session()->flash('status', "{$approvedCount} KRS berhasil disetujui.");
        $this->closeKrsModal();
    }

    public function render()
    {
        return view('livewire.dosen.krs.index')->extends('layouts.dosen');
    }
}
