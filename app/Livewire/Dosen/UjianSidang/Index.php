<?php

namespace App\Livewire\Dosen\UjianSidang;

use App\Models\Dosen;
use App\Models\Semester;
use App\Models\UjianSidangPenguji;
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
     * Sama persis dengan TugasAkhirController::listUjianSidangPengujiDosen — penugasan dosen ini
     * sebagai penguji, filter berdasarkan semester ujian sidang.
     *
     * @return array<int, UjianSidangPenguji>
     */
    #[Computed]
    public function rows(): array
    {
        $query = UjianSidangPenguji::where('id_dosen', $this->dosenId)
            ->with(['ujianSidang.semester', 'ujianSidang.tugasAkhir.mahasiswa.prodi']);

        if ($this->filterSemester !== '') {
            $semId = (int) $this->filterSemester;
            $query->whereHas('ujianSidang', fn ($q) => $q->where('id_semester', $semId));
        }

        return $query->orderByDesc('id')->get()->values()->all();
    }

    public function render()
    {
        return view('livewire.dosen.ujian-sidang.index')->extends('layouts.dosen');
    }
}
