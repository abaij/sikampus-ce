<?php

namespace App\Livewire\Dosen\Arsip;

use App\Models\Dosen;
use App\Models\JadwalDosen;
use App\Models\Kelas;
use App\Models\Semester;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Index extends Component
{
    #[Locked]
    public int $dosenId;

    public string $filterSemester = '';

    public function mount(): void
    {
        $dosen = Dosen::where('id_user', Auth::id())->firstOrFail();
        $this->dosenId = $dosen->id;

        $activeSemester = Semester::where('is_active', true)->whereNull('deleted_at')->first();
        $this->filterSemester = $activeSemester ? (string) $activeSemester->id : '';
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
     * Sama persis dengan JadwalDosenController::getMyJadwal, disederhanakan ke daftar kelas unik
     * (satu baris per kelas, meniru kelasRows di dosen/arsip/page.tsx) — jadwal aktif dosen ini,
     * bisa untuk semester mana pun (bukan hanya semester aktif).
     *
     * @return array<int, Kelas>
     */
    #[Computed]
    public function rows(): array
    {
        $kelasIds = JadwalDosen::where('id_dosen', $this->dosenId)
            ->where('status', 'active')
            ->whereHas('jadwal', function ($q) {
                if ($this->filterSemester !== '') {
                    $semId = (int) $this->filterSemester;
                    $q->whereHas('kelas', fn ($qk) => $qk->where('id_semester', $semId));
                }
            })
            ->with('jadwal.kelas')
            ->get()
            ->pluck('jadwal.kelas.id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($kelasIds === []) {
            return [];
        }

        return Kelas::with(['kurikulumMatkul.matkul', 'prodi'])
            ->whereIn('id', $kelasIds)
            ->whereNull('deleted_at')
            ->get()
            ->sortBy(fn (Kelas $k) => $k->kurikulumMatkul?->kodeMatkulLabel() ?? '')
            ->values()
            ->all();
    }

    public function render()
    {
        return view('livewire.dosen.arsip.index')->extends('layouts.dosen');
    }
}
