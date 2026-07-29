<?php

namespace App\Livewire\Dosen\Rps;

use App\Models\Dosen;
use App\Models\Kelas;
use App\Models\KelasDosen;
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
     * Sama persis dengan JadwalDosenController::getKelasSebagaiPicUntukRps — hanya kelas dimana
     * dosen ini adalah PIC (RPS hanya bisa dikelola oleh PIC mata kuliah).
     *
     * @return array<int, Kelas>
     */
    #[Computed]
    public function rows(): array
    {
        $kelasIds = KelasDosen::where('id_dosen', $this->dosenId)
            ->where('is_pic', true)
            ->whereNull('deleted_at')
            ->pluck('id_kelas')
            ->unique()
            ->values()
            ->all();

        if ($kelasIds === []) {
            return [];
        }

        $query = Kelas::with(['kurikulumMatkul.matkul', 'prodi.jenjang', 'semester', 'kelompokKelas'])
            ->whereIn('id', $kelasIds)
            ->whereNull('deleted_at');

        if ($this->filterSemester !== '') {
            $query->where('id_semester', (int) $this->filterSemester);
        }

        return $query->get()
            ->sortBy(fn (Kelas $k) => [-(int) ($k->id_semester ?? 0), $k->kurikulumMatkul?->kodeMatkulLabel() ?? '', $k->id])
            ->values()
            ->all();
    }

    public function render()
    {
        return view('livewire.dosen.rps.index')->extends('layouts.dosen');
    }
}
