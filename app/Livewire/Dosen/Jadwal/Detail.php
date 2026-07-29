<?php

namespace App\Livewire\Dosen\Jadwal;

use App\Models\Dosen;
use App\Models\Jadwal;
use App\Models\JadwalDosen;
use App\Models\JenisKuliah;
use App\Models\KelasDosen;
use App\Models\MateriPerkuliahan;
use App\Models\Ruangan;
use App\Models\Tugas;
use App\Models\TugasMahasiswa;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Detail extends Component
{
    use WithFileUploads;

    // Locked: saveJadwal()/saveBahasan() memakai jadwalId langsung tanpa mengecek ulang akses
    // dosen (hanya dicek sekali di mount()) — tanpa ini, jadwalId bisa "disentuh" lewat request
    // Livewire yang dimanipulasi untuk mengedit jadwal milik kelas lain.
    #[Locked]
    public int $kelasId;

    #[Locked]
    public int $jadwalId;

    #[Locked]
    public int $dosenId;

    #[Url(as: 'id_semester')]
    public string $idSemester = '';

    public bool $editing = false;

    public string $hari = '';

    public string $tanggal = '';

    public string $id_ruangan = '';

    public string $id_jenis_kuliah = '';

    public string $bahasan = '';

    public string $tab = 'informasi';

    public string $materiNama = '';

    /** @var TemporaryUploadedFile|null */
    public $materiFile = null;

    public string $tugasNama = '';

    public string $tugasDeskripsi = '';

    public string $tugasTanggalMulai = '';

    public string $tugasTanggalSelesai = '';

    /** @var TemporaryUploadedFile|null */
    public $tugasFile = null;

    public function mount(int $kelasId, int $jadwalId): void
    {
        $dosen = Dosen::where('id_user', Auth::id())->firstOrFail();
        $this->dosenId = $dosen->id;

        $jadwal = Jadwal::with('kelas')->find($jadwalId);
        abort_if($jadwal === null || (int) $jadwal->id_kelas !== $kelasId, 404);
        abort_unless($this->dosenCanAccess($dosen, $jadwal), 403, 'Anda tidak memiliki akses ke jadwal ini.');

        $this->kelasId = $kelasId;
        $this->jadwalId = $jadwalId;

        $this->fillFormFrom($jadwal);
    }

    /**
     * Sama persis dengan JadwalDosenController::dosenCanAccessJadwal.
     */
    private function dosenCanAccess(Dosen $dosen, Jadwal $jadwal): bool
    {
        $kelas = $jadwal->kelas;
        if (! $kelas) {
            return false;
        }
        if ((int) $kelas->id_dosen_pic === (int) $dosen->id) {
            return true;
        }
        if (KelasDosen::where('id_dosen', $dosen->id)->where('id_kelas', $kelas->id)->whereNull('deleted_at')->exists()) {
            return true;
        }

        return JadwalDosen::where('id_jadwal', $jadwal->id)
            ->where('id_dosen', $dosen->id)
            ->where('status', 'active')
            ->exists();
    }

    private function fillFormFrom(Jadwal $jadwal): void
    {
        $this->hari = (string) $jadwal->hari;
        $this->tanggal = $jadwal->tanggal?->format('Y-m-d') ?? '';
        $this->id_ruangan = (string) $jadwal->id_ruangan;
        $this->id_jenis_kuliah = (string) $jadwal->id_jenis_kuliah;
        $this->bahasan = (string) $jadwal->bahasan;
    }

    #[Computed]
    public function jadwal(): Jadwal
    {
        return Jadwal::with([
            'kelas.kurikulumMatkul.matkul',
            'kelas.prodi.jenjang',
            'kelas.kelompokKelas',
            'kelas.semester',
            'ruangan',
            'jenisKuliah',
            'dosen.dosen',
        ])->findOrFail($this->jadwalId);
    }

    #[Computed]
    public function ruanganOptions()
    {
        return Ruangan::whereNull('deleted_at')->orderBy('nama')->get(['id', 'nama']);
    }

    #[Computed]
    public function jenisKuliahOptions()
    {
        return JenisKuliah::whereNull('deleted_at')->orderBy('nama')->get(['id', 'nama']);
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['informasi', 'materi', 'tugas'], true) ? $tab : 'informasi';
    }

    /**
     * Sama persis dengan MateriPerkuliahanController::getByJadwal.
     */
    #[Computed]
    public function materiRows()
    {
        return MateriPerkuliahan::where('id_jadwal', $this->jadwalId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Sama persis dengan MateriPerkuliahanController::store.
     */
    public function uploadMateri(): void
    {
        $validated = $this->validate([
            'materiNama' => ['nullable', 'string', 'max:255'],
            'materiFile' => ['required', 'file', 'max:10240'],
        ]);

        $path = $this->materiFile->store('materi_perkuliahan', 'public');

        MateriPerkuliahan::create([
            'id_jadwal' => $this->jadwalId,
            'nama' => $validated['materiNama'] !== '' && $validated['materiNama'] !== null
                ? $validated['materiNama']
                : $this->materiFile->getClientOriginalName(),
            'file' => $path,
        ]);

        $this->reset(['materiNama', 'materiFile']);
        $this->resetValidation();
        unset($this->materiRows);
        session()->flash('status_materi', 'Materi berhasil diunggah.');
    }

    /**
     * Sama persis dengan TugasController::getByJadwal.
     */
    #[Computed]
    public function tugasRows()
    {
        $tugasList = Tugas::where('id_jadwal', $this->jadwalId)
            ->whereNull('deleted_at')
            ->with('dosen:id,nama')
            ->orderByDesc('created_at')
            ->get();

        $tugasIds = $tugasList->pluck('id')->toArray();
        $jumlahSubmit = [];
        if ($tugasIds !== []) {
            $jumlahSubmit = TugasMahasiswa::whereIn('id_tugas', $tugasIds)
                ->whereNull('deleted_at')
                ->selectRaw('id_tugas, COUNT(*) as jumlah')
                ->groupBy('id_tugas')
                ->pluck('jumlah', 'id_tugas')
                ->toArray();
        }

        return $tugasList->map(function (Tugas $tugas) use ($jumlahSubmit) {
            $tugas->jumlah_submit = (int) ($jumlahSubmit[$tugas->id] ?? 0);

            return $tugas;
        });
    }

    /**
     * Sama persis dengan TugasController::getPengumpulanByJadwalForDosen.
     */
    #[Computed]
    public function tugasPengumpulanRows()
    {
        $tugasIds = Tugas::where('id_jadwal', $this->jadwalId)
            ->whereNull('deleted_at')
            ->pluck('id');

        if ($tugasIds->isEmpty()) {
            return collect();
        }

        return TugasMahasiswa::whereIn('id_tugas', $tugasIds)
            ->whereNull('deleted_at')
            ->with(['mahasiswa:id,nim,nama', 'tugas:id,nama'])
            ->orderByDesc('tanggal_submit')
            ->orderBy('id')
            ->get()
            ->sortBy(function (TugasMahasiswa $tm) {
                return [(int) $tm->id_tugas, (string) ($tm->mahasiswa->nim ?? '')];
            })
            ->values();
    }

    /**
     * Sama persis dengan TugasController::store.
     */
    public function submitTugas(): void
    {
        $validated = $this->validate([
            'tugasNama' => ['required', 'string', 'max:255'],
            'tugasDeskripsi' => ['nullable', 'string'],
            'tugasFile' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx', 'max:10240'],
            'tugasTanggalMulai' => ['nullable', 'date'],
            'tugasTanggalSelesai' => ['nullable', 'date', 'after_or_equal:tugasTanggalMulai'],
        ]);

        $filePath = $this->tugasFile ? $this->tugasFile->store('tugas', 'public') : null;

        Tugas::create([
            'id_jadwal' => $this->jadwalId,
            'id_dosen' => $this->dosenId,
            'nama' => $validated['tugasNama'],
            'deskripsi' => $validated['tugasDeskripsi'] ?? null,
            'file' => $filePath,
            'tanggal_mulai' => $validated['tugasTanggalMulai'] !== '' ? $validated['tugasTanggalMulai'] : null,
            'tanggal_selesai' => $validated['tugasTanggalSelesai'] !== '' ? $validated['tugasTanggalSelesai'] : null,
        ]);

        $this->reset(['tugasNama', 'tugasDeskripsi', 'tugasTanggalMulai', 'tugasTanggalSelesai', 'tugasFile']);
        $this->resetValidation();
        unset($this->tugasRows);
        session()->flash('status_tugas', 'Tugas berhasil ditambahkan.');
    }

    /**
     * Sama persis dengan TugasController::updatePengumpulanStatusForDosen, ditambah pengecekan
     * bahwa pengumpulan ini milik tugas pada jadwalId yang sedang dibuka — idTugasMahasiswa
     * datang langsung dari parameter aksi wire:click, jadi bisa "disentuh" lewat request Livewire
     * yang dimanipulasi untuk menunjuk pengumpulan tugas pada jadwal lain.
     */
    public function terimaPengumpulan(int $idTugasMahasiswa): void
    {
        $tm = TugasMahasiswa::whereNull('deleted_at')
            ->with(['tugas' => function ($q): void {
                $q->whereNull('deleted_at');
            }])
            ->find($idTugasMahasiswa);

        abort_if($tm === null || $tm->tugas === null, 404);
        abort_unless((int) $tm->tugas->id_jadwal === $this->jadwalId, 403, 'Pengumpulan ini bukan untuk jadwal ini.');

        $tm->status = 'accepted';
        $tm->updated_by = Dosen::find($this->dosenId)?->nama;
        $tm->save();

        unset($this->tugasPengumpulanRows);
    }

    public function startEdit(): void
    {
        $this->fillFormFrom($this->jadwal);
        $this->editing = true;
    }

    public function cancelEdit(): void
    {
        $this->fillFormFrom($this->jadwal);
        $this->resetValidation();
        $this->editing = false;
    }

    /**
     * Sama persis dengan JadwalDosenController::updateJadwalAmpu.
     */
    public function saveJadwal(): void
    {
        $validated = $this->validate([
            'hari' => ['nullable', 'string', Rule::in(Jadwal::HARI)],
            'tanggal' => ['nullable', 'date'],
            'id_ruangan' => ['nullable', 'integer', 'exists:ruangan,id'],
            'id_jenis_kuliah' => ['nullable', 'integer', 'exists:jenis_kuliah,id'],
        ]);

        $jadwal = Jadwal::findOrFail($this->jadwalId);
        $jadwal->hari = $validated['hari'] !== '' && $validated['hari'] !== null ? strtolower((string) $validated['hari']) : null;
        $jadwal->tanggal = $validated['tanggal'] !== '' ? $validated['tanggal'] : null;
        $jadwal->id_ruangan = $validated['id_ruangan'] !== '' && $validated['id_ruangan'] !== null ? (int) $validated['id_ruangan'] : null;
        $jadwal->id_jenis_kuliah = $validated['id_jenis_kuliah'] !== '' && $validated['id_jenis_kuliah'] !== null ? (int) $validated['id_jenis_kuliah'] : null;
        $jadwal->save();

        unset($this->jadwal);
        $this->editing = false;
        session()->flash('status', 'Jadwal berhasil diperbarui.');
    }

    /**
     * Sama persis dengan JadwalDosenController::updateBahasanJadwalAmpu.
     */
    public function saveBahasan(): void
    {
        $this->validate([
            'bahasan' => ['nullable', 'string', 'max:65535'],
        ]);

        $jadwal = Jadwal::findOrFail($this->jadwalId);
        $jadwal->bahasan = $this->bahasan !== '' ? $this->bahasan : null;
        $jadwal->save();

        unset($this->jadwal);
        session()->flash('status_bahasan', 'Bahasan berhasil disimpan.');
    }

    public function render()
    {
        return view('livewire.dosen.jadwal.detail')->extends('layouts.dosen');
    }
}
