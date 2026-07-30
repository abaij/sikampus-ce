<?php

namespace App\Livewire\Mahasiswa\UjianSidang;

use App\Models\Mahasiswa;
use App\Models\Semester;
use App\Models\TugasAkhir;
use App\Models\UjianSidang;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Pengajuan extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $mahasiswaId;

    public string $idTugasAkhir = '';

    public string $idSemester = '';

    /** @var TemporaryUploadedFile|null */
    public $fileLaporan = null;

    public function mount(): void
    {
        $mahasiswa = Mahasiswa::where('id_user', Auth::id())->firstOrFail();
        $this->mahasiswaId = $mahasiswa->id;
    }

    /**
     * Sama persis dengan TugasAkhirController::ujianSidangContextMahasiswa.
     */
    #[Computed]
    public function ctx(): array
    {
        $hasTugasAkhir = TugasAkhir::where('id_mahasiswa', $this->mahasiswaId)->exists();

        $tugasAkhirApproved = TugasAkhir::with('semester')
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->where('status', 'approved')
            ->orderByDesc('id')
            ->get();

        $usedSemesterIdsPerTa = UjianSidang::whereIn('id_tugas_akhir', $tugasAkhirApproved->pluck('id'))
            ->get()
            ->groupBy('id_tugas_akhir')
            ->map(fn ($rows) => $rows->pluck('id_semester')->unique()->all());

        $allSemesters = Semester::orderByDesc('kode')->get(['id', 'kode', 'nama', 'is_active']);

        $semesterPerTa = [];
        foreach ($tugasAkhirApproved as $ta) {
            $used = $usedSemesterIdsPerTa->get($ta->id, []);
            $semesterPerTa[$ta->id] = $allSemesters->filter(fn (Semester $s) => ! in_array($s->id, $used, true))->values();
        }

        $eligible = $tugasAkhirApproved->isNotEmpty() && collect($semesterPerTa)->flatten()->isNotEmpty();

        $pesanTidakEligible = null;
        if ($tugasAkhirApproved->isEmpty()) {
            $pesanTidakEligible = 'Pengajuan ujian sidang hanya dapat dilakukan setelah judul tugas akhir Anda disetujui (status: disetujui).';
        } elseif (! $eligible) {
            $pesanTidakEligible = 'Semua semester yang tersedia sudah digunakan untuk pengajuan ujian sidang pada tugas akhir yang disetujui.';
        }

        return [
            'has_tugas_akhir' => $hasTugasAkhir,
            'tugas_akhir_approved' => $tugasAkhirApproved,
            'eligible_pengajuan' => $eligible,
            'pesan_tidak_eligible' => $pesanTidakEligible,
            'semester_untuk_pengajuan_per_ta' => $semesterPerTa,
        ];
    }

    #[Computed]
    public function taOptions(): array
    {
        return $this->ctx['tugas_akhir_approved']
            ->mapWithKeys(fn (TugasAkhir $t) => [$t->id => $t->judul.($t->semester?->kode ? " · {$t->semester->kode}" : '')])
            ->all();
    }

    #[Computed]
    public function semesterOptions(): array
    {
        if ($this->idTugasAkhir === '') {
            return [];
        }

        $list = $this->ctx['semester_untuk_pengajuan_per_ta'][(int) $this->idTugasAkhir] ?? collect();

        return $list->mapWithKeys(fn (Semester $s) => [
            $s->id => "{$s->nama} ({$s->kode})".($s->is_active ? ' · aktif' : ''),
        ])->all();
    }

    public function updatedIdTugasAkhir(): void
    {
        $this->idSemester = '';
        unset($this->semesterOptions);
    }

    /**
     * Sama persis dengan TugasAkhirController::storeUjianSidangMahasiswa.
     */
    public function submit()
    {
        $validated = $this->validate([
            'idTugasAkhir' => ['required', 'integer', 'exists:tugas_akhir,id'],
            'idSemester' => ['required', 'integer', 'exists:semester,id'],
            'fileLaporan' => ['required', 'file', 'max:12288', 'mimes:pdf,doc,docx'],
        ], [], [
            'idTugasAkhir' => 'tugas akhir',
            'idSemester' => 'semester',
            'fileLaporan' => 'file laporan',
        ]);

        $tugasAkhir = TugasAkhir::where('id_mahasiswa', $this->mahasiswaId)
            ->where('id', $validated['idTugasAkhir'])
            ->first();

        if (! $tugasAkhir) {
            $this->addError('idTugasAkhir', 'Data tugas akhir tidak ditemukan atau bukan milik Anda.');

            return;
        }
        if ($tugasAkhir->status !== 'approved') {
            $this->addError('idTugasAkhir', 'Judul tugas akhir harus disetujui terlebih dahulu sebelum mengajukan ujian sidang.');

            return;
        }

        $exists = UjianSidang::where('id_tugas_akhir', $tugasAkhir->id)
            ->where('id_semester', $validated['idSemester'])
            ->exists();
        if ($exists) {
            $this->addError('idSemester', 'Anda sudah memiliki pengajuan ujian sidang untuk semester ini pada tugas akhir tersebut.');

            return;
        }

        $usedSemesterIds = UjianSidang::where('id_tugas_akhir', $tugasAkhir->id)->pluck('id_semester')->unique()->all();
        $semesterAllowed = Semester::orderByDesc('kode')->pluck('id')
            ->filter(fn ($id) => ! in_array($id, $usedSemesterIds, true))
            ->all();

        if (! in_array((int) $validated['idSemester'], $semesterAllowed, true)) {
            $this->addError('idSemester', 'Semester yang dipilih tidak tersedia untuk pengajuan pada tugas akhir ini.');

            return;
        }

        $user = Auth::user();
        $actor = trim((string) ($user->name ?? '')) !== '' ? trim((string) $user->name) : (string) ($user->email ?? '');

        $pathFile = $this->fileLaporan->store('ujian-sidang', 'public');

        UjianSidang::create([
            'id_tugas_akhir' => $tugasAkhir->id,
            'id_semester' => $validated['idSemester'],
            'tanggal_daftar' => now(),
            'status' => 'submitted',
            'file_proposal' => $pathFile,
            'created_by' => $actor,
            'updated_by' => $actor,
        ]);

        session()->flash('status', 'Pengajuan ujian sidang berhasil dikirim.');

        return redirect()->route('mahasiswa.akhir-studi.ujian-sidang');
    }

    public function render()
    {
        return view('livewire.mahasiswa.ujian-sidang.pengajuan')->extends('layouts.mahasiswa');
    }
}
