<?php

namespace App\Livewire\Mahasiswa\TugasAkhir;

use App\Models\JenisMatkul;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Semester;
use App\Models\TugasAkhir;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Pengajuan extends Component
{
    use WithFileUploads;

    private const JENIS_MATKUL_TA = 'TA';

    #[Locked]
    public int $mahasiswaId;

    public string $judul = '';

    public string $judulEn = '';

    public string $topik = '';

    public string $topikEn = '';

    public string $deskripsi = '';

    public bool $isProposal = true;

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    public function mount(): void
    {
        $mahasiswa = Mahasiswa::where('id_user', Auth::id())->firstOrFail();
        $this->mahasiswaId = $mahasiswa->id;

        $existing = $this->ctx['tugas_akhir'];
        if ($existing) {
            $this->judul = $existing->judul;
            $this->judulEn = (string) $existing->judul_en;
            $this->topik = (string) $existing->topik;
            $this->topikEn = (string) $existing->topik_en;
            $this->deskripsi = (string) $existing->deskripsi;
            $this->isProposal = $existing->is_proposal !== false;
        }
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
                'tugas_akhir' => null,
                'can_edit' => false,
            ];
        }

        $idJenisTa = JenisMatkul::where('kode', self::JENIS_MATKUL_TA)->value('id');
        if (! $idJenisTa) {
            return [
                'eligible' => false,
                'semester_aktif' => $semesterAktif,
                'pesan_tidak_eligible' => 'Jenis mata kuliah Tugas Akhir (kode TA) belum dikonfigurasi di sistem.',
                'krs_ta' => null,
                'tugas_akhir' => null,
                'can_edit' => false,
            ];
        }

        $krsTa = $this->findKrsTugasAkhirDisetujui($semesterAktif->id, (int) $idJenisTa);

        $tugasAkhir = TugasAkhir::where('id_mahasiswa', $this->mahasiswaId)
            ->where('id_semester', $semesterAktif->id)
            ->first();

        return [
            'eligible' => $krsTa !== null,
            'semester_aktif' => $semesterAktif,
            'pesan_tidak_eligible' => $krsTa !== null ? null : 'Untuk mengajukan tugas akhir, Anda harus mengontrak mata kuliah dengan jenis Tugas Akhir (kode TA) pada KRS semester yang sedang aktif dan sudah disetujui.',
            'krs_ta' => $krsTa,
            'tugas_akhir' => $tugasAkhir,
            'can_edit' => $tugasAkhir && in_array($tugasAkhir->status, ['draft', 'rejected', 'returned'], true),
        ];
    }

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
     * Sama persis dengan TugasAkhirController::storePengajuanMahasiswa /
     * TugasAkhirController::updatePengajuanMahasiswa — form ini memutuskan sendiri mana yang
     * berlaku berdasarkan ctx(), sama seperti frontend memilih method POST/PUT.
     */
    public function submit()
    {
        $ctx = $this->ctx;
        abort_unless($ctx['eligible'], 422, 'Belum memenuhi syarat pengajuan.');

        $validated = $this->validate([
            'judul' => ['required', 'string', 'max:255'],
            'judulEn' => ['nullable', 'string', 'max:255'],
            'topik' => ['nullable', 'string', 'max:255'],
            'topikEn' => ['nullable', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:12288', 'mimes:pdf,doc,docx'],
        ]);

        $user = Auth::user();
        $actor = trim((string) ($user->name ?? '')) !== '' ? trim((string) $user->name) : (string) ($user->email ?? '');

        $existing = $ctx['tugas_akhir'];

        if ($existing) {
            abort_unless(in_array($existing->status, ['rejected', 'returned'], true), 422, 'Anda sudah memiliki pengajuan tugas akhir untuk semester ini dan tidak dapat diubah lagi (bukan status ditolak/dikembalikan).');

            $pathFile = $existing->file;
            if ($this->file) {
                if ($existing->file) {
                    Storage::disk('public')->delete($existing->file);
                }
                $pathFile = $this->file->store('tugas-akhir', 'public');
            }

            $existing->update([
                'judul' => $validated['judul'],
                'judul_en' => $validated['judulEn'] !== '' ? $validated['judulEn'] : null,
                'topik' => $validated['topik'] !== '' ? $validated['topik'] : null,
                'topik_en' => $validated['topikEn'] !== '' ? $validated['topikEn'] : null,
                'deskripsi' => $validated['deskripsi'] !== '' ? $validated['deskripsi'] : null,
                'is_proposal' => $this->isProposal,
                'file' => $pathFile,
                'updated_by' => $actor,
                'status' => 'submitted',
            ]);
        } else {
            $pathFile = $this->file ? $this->file->store('tugas-akhir', 'public') : null;

            TugasAkhir::create([
                'id_mahasiswa' => $this->mahasiswaId,
                'id_semester' => $ctx['semester_aktif']->id,
                'judul' => $validated['judul'],
                'judul_en' => $validated['judulEn'] !== '' ? $validated['judulEn'] : null,
                'topik' => $validated['topik'] !== '' ? $validated['topik'] : null,
                'topik_en' => $validated['topikEn'] !== '' ? $validated['topikEn'] : null,
                'deskripsi' => $validated['deskripsi'] !== '' ? $validated['deskripsi'] : null,
                'is_proposal' => $this->isProposal,
                'file' => $pathFile,
                'created_by' => $actor,
                'updated_by' => $actor,
                'status' => 'submitted',
            ]);
        }

        session()->flash('status', $existing ? 'Pengajuan telah diajukan ulang.' : 'Pengajuan tugas akhir berhasil dikirim.');

        return redirect()->route('mahasiswa.akhir-studi.tugas-akhir');
    }

    public function render()
    {
        return view('livewire.mahasiswa.tugas-akhir.pengajuan')->extends('layouts.mahasiswa');
    }
}
