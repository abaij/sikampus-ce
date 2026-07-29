<?php

namespace App\Livewire\Dosen\Jadwal;

use App\Models\Dosen;
use App\Models\Jadwal;
use App\Models\JadwalDosen;
use App\Models\JenisKuliah;
use App\Models\KelasDosen;
use App\Models\Ruangan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class Detail extends Component
{
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
