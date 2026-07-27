<?php

namespace App\Livewire\Admin\Jadwal;

use App\Livewire\Admin\Jadwal\Concerns\ForwardsIndexState;
use App\Models\Jadwal;
use App\Models\Kehadiran;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Perkuliahan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    use ForwardsIndexState;

    public int $jadwalId;

    public bool $confirmingDelete = false;

    public function mount(int $id): void
    {
        $this->jadwalId = $id;
        $this->resolveBackUrl();

        $jadwal = Jadwal::findOrFail($id);
        $this->ensureAccess($jadwal);
    }

    /**
     * Sama persis dengan JadwalController — pengecekan scope prodi lewat kelas.
     */
    private function ensureAccess(Jadwal $jadwal): void
    {
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
    }

    /**
     * Sama persis dengan JadwalController::findPerkuliahanForJadwalSlot, disederhanakan untuk
     * satu jadwal (semua baris di $perkuliahanRows sudah pasti milik jadwal ini).
     */
    private function findPerkuliahanAktif()
    {
        $rows = $this->perkuliahanRows;

        $ts = static function (?Perkuliahan $p): int {
            if ($p === null || ! $p->waktu_mulai) {
                return 0;
            }

            return Carbon::parse($p->waktu_mulai)->getTimestamp();
        };

        $ongoing = $rows
            ->filter(fn (Perkuliahan $p) => $p->waktu_mulai && ! $p->waktu_selesai)
            ->sortByDesc(fn (Perkuliahan $p) => $ts($p))
            ->first();

        if ($ongoing) {
            return $ongoing;
        }

        return $rows->sortByDesc(fn (Perkuliahan $p) => [$ts($p), $p->id])->first();
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
     * Sama persis dengan JadwalController::detail.
     */
    #[Computed]
    public function jadwal(): Jadwal
    {
        $jadwal = Jadwal::with([
            'kelas.kurikulumMatkul.matkul',
            'kelas.kurikulumMatkul.kurikulum',
            'kelas.prodi.jenjang',
            'kelas.semester',
            'jenisKuliah',
            'ruangan',
            'dosen.dosen',
        ])->findOrFail($this->jadwalId);

        $sesi = $this->sesiStatusForPerkuliahan($this->perkuliahanAktif);
        $jadwal->setAttribute('sesi_status', $sesi['sesi_status']);
        $jadwal->setAttribute('sesi_status_label', $sesi['sesi_status_label']);

        return $jadwal;
    }

    #[Computed]
    public function perkuliahanRows()
    {
        return Perkuliahan::where('id_jadwal', $this->jadwalId)
            ->whereNull('deleted_at')
            ->orderByRaw('waktu_mulai IS NULL')
            ->orderBy('waktu_mulai', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    #[Computed]
    public function perkuliahanAktif(): ?Perkuliahan
    {
        if ($this->perkuliahanRows->isEmpty()) {
            return null;
        }

        return $this->findPerkuliahanAktif();
    }

    #[Computed]
    public function perkuliahanList()
    {
        return $this->perkuliahanRows->map(function (Perkuliahan $p) {
            $status = $this->sesiStatusForPerkuliahan($p);

            return (object) [
                'id' => $p->id,
                'tanggal' => $p->waktu_mulai ? Carbon::parse($p->waktu_mulai)->format('Y-m-d') : null,
                'waktu_mulai' => $p->waktu_mulai,
                'waktu_selesai' => $p->waktu_selesai,
                'materi' => $p->materi,
                'realisasi_materi' => $p->realisasi_materi,
                'sesi_status' => $status['sesi_status'],
                'sesi_status_label' => $status['sesi_status_label'],
                'is_aktif' => $this->perkuliahanAktif?->id === $p->id,
            ];
        })->values();
    }

    #[Computed]
    public function kehadiranList()
    {
        if (! $this->perkuliahanAktif) {
            return collect();
        }

        $perkuliahanAktifId = $this->perkuliahanAktif->id;

        return Krs::with(['mahasiswa.prodi'])
            ->where('id_kelas', (int) $this->jadwal->id_kelas)
            ->whereNotNull('approved_at')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get()
            ->map(function (Krs $krs) use ($perkuliahanAktifId) {
                $kehadiran = Kehadiran::where('id_perkuliahan', $perkuliahanAktifId)
                    ->where('id_mhs', $krs->id_mahasiswa)
                    ->whereNull('deleted_at')
                    ->first();

                return (object) [
                    'id_krs' => $krs->id,
                    'mahasiswa' => $krs->mahasiswa,
                    'kehadiran' => $kehadiran,
                ];
            })
            ->values();
    }

    public function confirmDelete(): void
    {
        $this->confirmingDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDelete = false;
    }

    /**
     * Sama persis dengan JadwalController::destroy.
     */
    public function delete()
    {
        $jadwal = Jadwal::with('kelas')->findOrFail($this->jadwalId);
        $this->ensureAccess($jadwal);

        $jadwal->delete();

        session()->flash('status', 'Jadwal dihapus.');

        return redirect()->route('admin.akademik.jadwal');
    }

    public function render()
    {
        return view('livewire.admin.jadwal.show')->extends('layouts.web');
    }
}
