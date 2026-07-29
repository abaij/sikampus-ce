<?php

namespace App\Livewire\Dosen\Jadwal;

use App\Models\Dosen;
use App\Models\JadwalDosen;
use App\Models\Semester;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Index extends Component
{
    #[Locked]
    public int $dosenId;

    public string $filterSemester = '';

    public string $viewMonth;

    private const HARI_OFFSET = [
        'senin' => 0, 'selasa' => 1, 'rabu' => 2, 'kamis' => 3, 'jumat' => 4, 'sabtu' => 5, 'minggu' => 6,
    ];

    public function mount(): void
    {
        $dosen = Dosen::where('id_user', Auth::id())->firstOrFail();
        $this->dosenId = $dosen->id;

        $activeSemester = Semester::where('is_active', true)->whereNull('deleted_at')->first();
        $this->filterSemester = $activeSemester ? (string) $activeSemester->id : '';

        $this->viewMonth = CarbonImmutable::now()->startOfMonth()->format('Y-m-d');
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

    public function prevMonth(): void
    {
        $this->viewMonth = CarbonImmutable::parse($this->viewMonth)->subMonth()->format('Y-m-d');
    }

    public function nextMonth(): void
    {
        $this->viewMonth = CarbonImmutable::parse($this->viewMonth)->addMonth()->format('Y-m-d');
    }

    public function thisMonth(): void
    {
        $this->viewMonth = CarbonImmutable::now()->startOfMonth()->format('Y-m-d');
    }

    /**
     * Grid tanggal Senin–Minggu untuk bulan yang dilihat, di-padding null di awal/akhir baris —
     * sama seperti buildMonthGrid di dosen/jadwal/page.tsx.
     *
     * @return array<int, CarbonImmutable|null>
     */
    #[Computed]
    public function monthGrid(): array
    {
        $start = CarbonImmutable::parse($this->viewMonth)->startOfMonth();
        $end = $start->endOfMonth();

        $cells = [];
        // dayOfWeekIso: 1=Senin..7=Minggu, jadi offset dari Senin = dayOfWeekIso - 1.
        for ($i = 0; $i < $start->dayOfWeekIso - 1; $i++) {
            $cells[] = null;
        }
        for ($day = 1; $day <= $end->day; $day++) {
            $cells[] = $start->day($day);
        }
        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }

        return $cells;
    }

    /**
     * Sama persis dengan JadwalDosenController::getMyJadwal (jadwal_dosen status active), tapi
     * setiap baris diekspansi jadi seluruh kemunculannya pada bulan yang sedang dilihat — recurring
     * tiap minggu berdasarkan `hari` kalau tanggal eksplisit kosong, sama seperti
     * buildEventsByDateForMonth di dosen/jadwal/page.tsx.
     *
     * @return array<string, array<int, array<string, mixed>>> keyed 'Y-m-d', diurutkan jam_mulai
     */
    #[Computed]
    public function eventsByDate(): array
    {
        $monthStart = CarbonImmutable::parse($this->viewMonth)->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();

        $query = JadwalDosen::with(['jadwal.kelas.kurikulumMatkul.matkul'])
            ->where('id_dosen', $this->dosenId)
            ->where('status', 'active');

        if ($this->filterSemester !== '') {
            $semesterId = (int) $this->filterSemester;
            $query->whereHas('jadwal.kelas', fn ($q) => $q->where('id_semester', $semesterId));
        }

        $events = [];

        foreach ($query->get() as $jadwalDosen) {
            $jadwal = $jadwalDosen->jadwal;
            if (! $jadwal) {
                continue;
            }

            $km = $jadwal->kelas?->kurikulumMatkul;
            $item = [
                'id_jadwal' => $jadwal->id,
                'id_kelas' => $jadwal->kelas?->id,
                'jam_mulai' => $jadwal->jam_mulai,
                'kode_matkul' => $km?->kodeMatkulLabel(),
                'nama_matkul' => $km?->namaMatkulLabel() ?? '—',
            ];

            if ($jadwal->tanggal) {
                $date = $jadwal->tanggal->copy()->startOfDay();
                if ($date->between($monthStart, $monthEnd)) {
                    $events[$date->format('Y-m-d')][] = $item;
                }

                continue;
            }

            $offset = self::HARI_OFFSET[strtolower((string) $jadwal->hari)] ?? null;
            if ($offset === null) {
                continue;
            }

            for ($cursor = $monthStart; $cursor->lte($monthEnd); $cursor = $cursor->addDay()) {
                if (($cursor->dayOfWeekIso - 1) === $offset) {
                    $events[$cursor->format('Y-m-d')][] = $item;
                }
            }
        }

        foreach ($events as &$dayEvents) {
            usort($dayEvents, fn (array $a, array $b) => strcmp((string) $a['jam_mulai'], (string) $b['jam_mulai']));
        }
        unset($dayEvents);

        return $events;
    }

    public function render()
    {
        return view('livewire.dosen.jadwal.index')->extends('layouts.dosen');
    }
}
