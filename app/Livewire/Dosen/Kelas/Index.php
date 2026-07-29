<?php

namespace App\Livewire\Dosen\Kelas;

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

    private const HARI_LABEL = [
        'senin' => 'Senin',
        'selasa' => 'Selasa',
        'rabu' => 'Rabu',
        'kamis' => 'Kamis',
        'jumat' => 'Jumat',
        'sabtu' => 'Sabtu',
        'minggu' => 'Minggu',
    ];

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
     * Sama persis dengan JadwalDosenController::getKelasAmpu — daftar kelas dari kelas_dosen
     * (termasuk sebagai PIC atau tim pengampu), bukan slot jadwal per hari.
     *
     * @return array<int, array{kelas: Kelas, is_pic: bool, jadwal_ringkas: string}>
     */
    #[Computed]
    public function rows(): array
    {
        $kelasDosenRows = KelasDosen::where('id_dosen', $this->dosenId)->whereNull('deleted_at')->get();
        $kelasIds = $kelasDosenRows->pluck('id_kelas')->unique()->values()->all();
        $picByKelas = $kelasDosenRows->keyBy('id_kelas');

        if ($kelasIds === []) {
            return [];
        }

        $query = Kelas::with([
            'kurikulumMatkul.matkul',
            'prodi.jenjang',
            'kelompokKelas',
            'jadwal' => fn ($q) => $q->whereNull('deleted_at')->orderBy('hari')->orderBy('jam_mulai'),
        ])
            ->whereIn('id', $kelasIds)
            ->whereNull('deleted_at');

        if ($this->filterSemester !== '') {
            $query->where('id_semester', (int) $this->filterSemester);
        }

        return $query->get()
            ->sortBy(fn (Kelas $k) => ($k->kurikulumMatkul?->kodeMatkulLabel() ?? '').'-'.$k->id)
            ->map(fn (Kelas $kelas) => [
                'kelas' => $kelas,
                'is_pic' => (bool) ($picByKelas->get($kelas->id)?->is_pic ?? false),
                'jadwal_ringkas' => $this->jadwalRingkas($kelas->jadwal),
            ])
            ->values()
            ->all();
    }

    /**
     * Sama persis dengan formatJadwalRingkas di dosen/kelas/page.tsx.
     */
    private function jadwalRingkas($jadwalList): string
    {
        if ($jadwalList->isEmpty()) {
            return '—';
        }

        $first = $jadwalList->first();
        $hari = self::HARI_LABEL[strtolower((string) $first->hari)] ?? $first->hari ?? '—';
        $mulai = substr((string) $first->jam_mulai, 0, 5);
        $selesai = substr((string) $first->jam_selesai, 0, 5);

        $label = "{$hari}, {$mulai}–{$selesai}";

        if ($jadwalList->count() > 1) {
            $label .= ' (+'.($jadwalList->count() - 1).')';
        }

        return $label;
    }

    public function render()
    {
        return view('livewire.dosen.kelas.index')->extends('layouts.dosen');
    }
}
