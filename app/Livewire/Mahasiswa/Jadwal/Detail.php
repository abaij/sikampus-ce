<?php

namespace App\Livewire\Mahasiswa\Jadwal;

use App\Models\Jadwal;
use App\Models\Kehadiran;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Perkuliahan;
use App\Models\Tugas;
use App\Models\TugasMahasiswa;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Detail extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $mahasiswaId;

    #[Locked]
    public int $jadwalId;

    public string $tab = 'detail';

    public ?int $submittingTugasId = null;

    public string $tugasKeterangan = '';

    /** @var TemporaryUploadedFile|null */
    public $tugasFile = null;

    public function mount(int $id): void
    {
        $mahasiswa = Mahasiswa::where('id_user', Auth::id())->firstOrFail();
        $this->mahasiswaId = $mahasiswa->id;

        $jadwal = Jadwal::whereNull('deleted_at')->find($id);
        abort_if($jadwal === null, 404, 'Jadwal tidak ditemukan.');

        $hasKrs = Krs::where('id_mahasiswa', $this->mahasiswaId)
            ->where('id_kelas', $jadwal->id_kelas)
            ->whereNull('deleted_at')
            ->exists();
        abort_unless($hasKrs, 403, 'Anda tidak memiliki KRS untuk kelas pada jadwal ini.');

        $this->jadwalId = $id;
    }

    #[Computed]
    public function jadwal(): Jadwal
    {
        return Jadwal::with([
            'kelas.kurikulumMatkul.matkul',
            'kelas.semester',
            'ruangan',
            'jenisKuliah',
            'dosen.dosen',
        ])->findOrFail($this->jadwalId);
    }

    #[Computed]
    public function krsStatus(): string
    {
        $krs = Krs::where('id_mahasiswa', $this->mahasiswaId)
            ->where('id_kelas', $this->jadwal->id_kelas)
            ->whereNull('deleted_at')
            ->first();

        return $krs && $krs->approved_at ? 'approved' : 'pending';
    }

    /**
     * Sama persis dengan KrsController::getJadwalDetailMahasiswa.
     */
    #[Computed]
    public function perkuliahanRows()
    {
        $rows = Perkuliahan::with('materiPerkuliahan')
            ->where('id_jadwal', $this->jadwalId)
            ->whereNull('deleted_at')
            ->orderByRaw('waktu_mulai IS NULL')
            ->orderBy('waktu_mulai', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return $rows->values()->map(function (Perkuliahan $p, int $idx) {
            $kehadiran = Kehadiran::where('id_perkuliahan', $p->id)
                ->where('id_mhs', $this->mahasiswaId)
                ->whereNull('deleted_at')
                ->first();

            $realisasi = trim((string) $p->realisasi_materi) !== '' ? $p->realisasi_materi : null;

            return (object) [
                'id' => $p->id,
                'pertemuan_ke' => $idx + 1,
                'tanggal' => $p->waktu_mulai?->format('Y-m-d'),
                'materi' => $p->materi,
                'realisasi_materi' => $realisasi,
                'materi_perkuliahan' => $p->materiPerkuliahan,
                'kehadiran_saya' => $kehadiran,
            ];
        });
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['detail', 'tugas', 'kehadiran'], true) ? $tab : 'detail';
    }

    /**
     * Sama persis dengan TugasController::getByJadwalForMahasiswa.
     */
    #[Computed]
    public function tugasRows()
    {
        $tugasList = Tugas::where('id_jadwal', $this->jadwalId)
            ->whereNull('deleted_at')
            ->with('dosen:id,nama')
            ->orderByDesc('created_at')
            ->get();

        $mySubmits = TugasMahasiswa::whereIn('id_tugas', $tugasList->pluck('id'))
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('id_tugas');

        return $tugasList->map(function (Tugas $tugas) use ($mySubmits) {
            $tugas->pengumpulan_saya = $mySubmits->get($tugas->id);
            $tugas->terbuka = $this->tugasPengumpulanTerbuka($tugas);

            return $tugas;
        });
    }

    private function tugasPengumpulanTerbuka(Tugas $tugas): bool
    {
        $now = now();
        if ($tugas->tanggal_mulai && $now->lt($tugas->tanggal_mulai)) {
            return false;
        }

        return ! ($tugas->tanggal_selesai && $now->gt($tugas->tanggal_selesai));
    }

    public function startSubmit(int $tugasId): void
    {
        $existing = TugasMahasiswa::where('id_tugas', $tugasId)
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->whereNull('deleted_at')
            ->first();

        $this->submittingTugasId = $tugasId;
        $this->tugasKeterangan = (string) ($existing->keterangan ?? '');
        $this->tugasFile = null;
        $this->resetValidation();
    }

    public function cancelSubmit(): void
    {
        $this->submittingTugasId = null;
        $this->tugasKeterangan = '';
        $this->tugasFile = null;
        $this->resetValidation();
    }

    /**
     * Sama persis dengan TugasController::submitKumpulkanMahasiswa.
     */
    public function submitTugas(): void
    {
        $tugas = Tugas::whereNull('deleted_at')->findOrFail($this->submittingTugasId);
        abort_unless((int) $tugas->id_jadwal === $this->jadwalId, 403);

        if (! $this->tugasPengumpulanTerbuka($tugas)) {
            $this->addError('tugasFile', 'Masa pengumpulan tugas belum dibuka atau telah berakhir.');

            return;
        }

        $tm = TugasMahasiswa::withTrashed()
            ->where('id_tugas', $tugas->id)
            ->where('id_mahasiswa', $this->mahasiswaId)
            ->first();

        if ($tm && $tm->trashed()) {
            $tm->restore();
        }

        if (! $tm) {
            $tm = new TugasMahasiswa;
            $tm->id_tugas = $tugas->id;
            $tm->id_mahasiswa = $this->mahasiswaId;
        }

        $validated = $this->validate([
            'tugasFile' => [$tm->file ? 'nullable' : 'required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx', 'max:10240'],
            'tugasKeterangan' => ['nullable', 'string', 'max:65535'],
        ]);

        $mahasiswa = Mahasiswa::find($this->mahasiswaId);

        if ($this->tugasFile) {
            if ($tm->file) {
                Storage::disk('public')->delete($tm->file);
            }
            $filename = 'tugas_mhs_'.$this->mahasiswaId.'_'.$tugas->id.'_'.time().'.'.$this->tugasFile->getClientOriginalExtension();
            $tm->file = $this->tugasFile->storeAs('tugas_mahasiswa', $filename, 'public');
        }

        $tm->keterangan = $validated['tugasKeterangan'] !== '' ? $validated['tugasKeterangan'] : null;
        $tm->tanggal_submit = now();
        $tm->status = 'submitted';

        if (! $tm->exists) {
            $tm->created_by = $mahasiswa?->nama;
        }
        $tm->updated_by = $mahasiswa?->nama;
        $tm->save();

        $this->cancelSubmit();
        unset($this->tugasRows);
        session()->flash('status_tugas', 'Pengumpulan berhasil disimpan.');
    }

    public function render()
    {
        return view('livewire.mahasiswa.jadwal.detail')->extends('layouts.mahasiswa');
    }
}
