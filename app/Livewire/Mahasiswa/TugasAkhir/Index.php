<?php

namespace App\Livewire\Mahasiswa\TugasAkhir;

use App\Models\JenisMatkul;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Semester;
use App\Models\TugasAkhir;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Index extends Component
{
    private const JENIS_MATKUL_TA = 'TA';

    private const STATUSES = ['draft', 'submitted', 'approved', 'rejected', 'returned'];

    private const STATUS_LABELS = [
        'draft' => 'Draft',
        'submitted' => 'Terkirim',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'returned' => 'Dikembalikan',
    ];

    #[Locked]
    public int $mahasiswaId;

    public string $filterStatus = '';

    public string $filterSemester = '';

    public function mount(): void
    {
        $mahasiswa = Mahasiswa::where('id_user', Auth::id())->firstOrFail();
        $this->mahasiswaId = $mahasiswa->id;
    }

    #[Computed]
    public function statusOptions(): array
    {
        return ['' => 'Semua status'] + self::STATUS_LABELS;
    }

    /**
     * Sama persis dengan SemesterController::getList.
     */
    #[Computed]
    public function semesterOptions(): array
    {
        return Semester::orderByDesc('kode')
            ->get(['id', 'kode', 'nama', 'is_active'])
            ->mapWithKeys(fn (Semester $s) => [
                $s->id => "{$s->nama} ({$s->kode})".($s->is_active ? ' · aktif' : ''),
            ])
            ->all();
    }

    /**
     * Sama persis dengan TugasAkhirController::pengajuanContextMahasiswa.
     */
    #[Computed]
    public function ctx(): array
    {
        $semesterAktif = Semester::where('is_active', true)->orderByDesc('id')->first();
        if (! $semesterAktif) {
            return [
                'eligible' => false,
                'semester_aktif' => null,
                'pesan_tidak_eligible' => 'Tidak ada semester aktif saat ini.',
                'krs_ta' => null,
            ];
        }

        $idJenisTa = JenisMatkul::where('kode', self::JENIS_MATKUL_TA)->value('id');
        if (! $idJenisTa) {
            return [
                'eligible' => false,
                'semester_aktif' => $semesterAktif,
                'pesan_tidak_eligible' => 'Jenis mata kuliah Tugas Akhir (kode TA) belum dikonfigurasi di sistem.',
                'krs_ta' => null,
            ];
        }

        $krsTa = $this->findKrsTugasAkhirDisetujui($semesterAktif->id, (int) $idJenisTa);

        return [
            'eligible' => $krsTa !== null,
            'semester_aktif' => $semesterAktif,
            'pesan_tidak_eligible' => $krsTa !== null ? null : 'Untuk mengajukan tugas akhir, Anda harus mengontrak mata kuliah dengan jenis Tugas Akhir (kode TA) pada KRS semester yang sedang aktif dan sudah disetujui.',
            'krs_ta' => $krsTa,
        ];
    }

    /**
     * Sama persis dengan TugasAkhirController::findKrsTugasAkhirDisetujui.
     */
    private function findKrsTugasAkhirDisetujui(int $idSemester, int $idJenisTa): ?Krs
    {
        return Krs::with(['kelas.kurikulumMatkul.matkul'])
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->whereNull('deleted_at')
            ->whereNotNull('approved_at')
            ->whereHas('kelas', fn ($q) => $q->where('id_semester', $idSemester))
            ->whereHas('kelas.kurikulumMatkul.matkul', fn ($q) => $q->where('id_jenis_matkul', $idJenisTa))
            ->orderByDesc('approved_at')
            ->first();
    }

    /**
     * Sama persis dengan TugasAkhirController::listTugasAkhirMahasiswa.
     */
    #[Computed]
    public function rows()
    {
        $query = TugasAkhir::with('semester')
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->orderByDesc('id');

        if ($this->filterStatus !== '' && in_array($this->filterStatus, self::STATUSES, true)) {
            $query->where('status', $this->filterStatus);
        }
        if ($this->filterSemester !== '') {
            $query->where('id_semester', (int) $this->filterSemester);
        }

        return $query->get();
    }

    public function render()
    {
        return view('livewire.mahasiswa.tugas-akhir.index')->extends('layouts.mahasiswa');
    }
}
