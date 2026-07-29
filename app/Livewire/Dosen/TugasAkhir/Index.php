<?php

namespace App\Livewire\Dosen\TugasAkhir;

use App\Models\Dosen;
use App\Models\Semester;
use App\Models\TugasAkhir;
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
     * Sama persis dengan TugasAkhirController::listTugasAkhirBimbinganDosen — tugas akhir dengan
     * judul disetujui dimana dosen ini berperan sebagai pembimbing.
     *
     * @return array<int, TugasAkhir>
     */
    #[Computed]
    public function rows(): array
    {
        $query = TugasAkhir::query()
            ->where('status', 'approved')
            ->with(['mahasiswa.prodi', 'semester'])
            ->whereHas('pembimbing', function ($q) {
                $q->where('id_dosen', $this->dosenId);
            })
            ->orderByDesc('updated_at');

        if ($this->filterSemester !== '') {
            $query->where('id_semester', (int) $this->filterSemester);
        }

        return $query->get()->values()->all();
    }

    public function render()
    {
        return view('livewire.dosen.tugas-akhir.index')->extends('layouts.dosen');
    }
}
