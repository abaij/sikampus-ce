<?php

namespace App\Livewire\Admin\Dosen;

use App\Livewire\Admin\Dosen\Concerns\ForwardsIndexState;
use App\Models\Dosen;
use App\Models\DosenWali;
use App\Models\KelasDosen;
use App\Models\Mahasiswa;
use App\Models\Semester;
use App\Support\PanelAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Show extends Component
{
    use ForwardsIndexState, WithPagination;

    public int $dosenId;

    public string $activeTab = 'biodata';

    // Tab Kelas Kuliah — sengaja tidak diberi type hint ?int: wire:model.live pada <select>
    // mengirim string kosong "" untuk opsi "Semua semester", dan PHP tidak bisa
    // meng-koersi "" ke int (typed property akan melempar TypeError).
    public $selectedSemester = null;

    // Tab Mahasiswa Bimbingan
    public string $bimbinganSearch = '';

    public int $bimbinganPerPage = 10;

    public bool $showModal = false;

    public ?int $editingBimbinganId = null;

    public string $mahasiswaSearch = '';

    public ?int $selectedMahasiswaId = null;

    public string $selectedMahasiswaLabel = '';

    public string $bimbinganStatus = 'active';

    public ?int $confirmingBimbinganDeleteId = null;

    public bool $confirmingDosenDelete = false;

    public function mount(int $id): void
    {
        $this->dosenId = $id;

        Dosen::findOrFail($id);

        $this->resolveBackUrl();

        $semesterAktif = Semester::where('is_active', true)->first();
        $this->selectedSemester = $semesterAktif?->id;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function updatingBimbinganSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function dosen(): Dosen
    {
        return Dosen::with(['provinsi', 'kota', 'negara'])->findOrFail($this->dosenId);
    }

    #[Computed]
    public function semesterOptions()
    {
        return Semester::orderByDesc('kode')->get(['id', 'kode', 'nama', 'is_active']);
    }

    /**
     * Sama dengan JadwalDosenController::getKelasDiampuByDosen — daftar dari kelas_dosen,
     * bukan slot jadwal per hari.
     */
    #[Computed]
    public function kelasDiampu()
    {
        $query = KelasDosen::query()
            ->where('id_dosen', $this->dosenId)
            ->with([
                'kelas' => function ($q) {
                    $q->with([
                        'kurikulumMatkul.matkul',
                        'kurikulumMatkul.kurikulum',
                        'prodi.jenjang',
                        'semester',
                        'angkatan',
                        'kelompokKelas',
                        'dosenPic',
                    ]);
                    if ($this->selectedSemester) {
                        $q->where('id_semester', (int) $this->selectedSemester);
                    }
                },
            ])
            ->orderBy('id');

        return $query->get()->filter(fn (KelasDosen $kd) => $kd->kelas !== null)->values();
    }

    #[Computed]
    public function bimbinganList()
    {
        $query = DosenWali::query()
            ->where('id_dosen', $this->dosenId)
            ->with(['mahasiswa.prodi']);

        if ($this->bimbinganSearch !== '') {
            $query->whereHas('mahasiswa', function ($q) {
                $q->where('nama', 'like', "%{$this->bimbinganSearch}%")
                    ->orWhere('nim', 'like', "%{$this->bimbinganSearch}%");
            });
        }

        return $query->orderByDesc('id')->paginate($this->bimbinganPerPage);
    }

    #[Computed]
    public function mahasiswaResults()
    {
        if ($this->mahasiswaSearch === '') {
            return collect();
        }

        return Mahasiswa::query()
            ->where(function ($q) {
                $q->where('nama', 'like', "%{$this->mahasiswaSearch}%")
                    ->orWhere('nim', 'like', "%{$this->mahasiswaSearch}%");
            })
            ->orderBy('nama')
            ->limit(20)
            ->get(['id', 'nama', 'nim']);
    }

    public function openModal(?int $id = null): void
    {
        // Tombol pemicu tambah/ubah/hapus bimbingan dan hapus dosen disembunyikan di Blade untuk
        // user tanpa hak, tapi method Livewire tetap bisa dipanggil langsung lewat request yang
        // dipalsukan — pengecekan di sini dan di saveBimbingan/confirmDeleteBimbingan/
        // deleteBimbingan/confirmDeleteDosen/deleteDosen adalah otoritas sebenarnya, bukan
        // sekadar UI. Bimbingan (dosen wali) dan Dosen adalah dua resource/permission berbeda
        // walau diakses dari halaman yang sama.
        abort_unless(PanelAccess::can(Auth::user(), 'dosen wali', 'update'), 403, 'Anda tidak memiliki hak untuk mengubah data bimbingan.');

        $this->resetValidation();

        if ($id) {
            $item = DosenWali::with('mahasiswa')->findOrFail($id);
            $this->editingBimbinganId = $item->id;
            $this->selectedMahasiswaId = $item->id_mahasiswa;
            $this->selectedMahasiswaLabel = trim(($item->mahasiswa->nim ?? '').' - '.($item->mahasiswa->nama ?? ''));
            $this->bimbinganStatus = $item->status;
        } else {
            $this->editingBimbinganId = null;
            $this->selectedMahasiswaId = null;
            $this->selectedMahasiswaLabel = '';
            $this->bimbinganStatus = 'active';
        }

        $this->mahasiswaSearch = '';
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->editingBimbinganId = null;
        $this->selectedMahasiswaId = null;
        $this->selectedMahasiswaLabel = '';
        $this->mahasiswaSearch = '';
    }

    public function selectMahasiswa(int $id, string $label): void
    {
        $this->selectedMahasiswaId = $id;
        $this->selectedMahasiswaLabel = $label;
        $this->mahasiswaSearch = '';
    }

    public function saveBimbingan(): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'dosen wali', 'update'), 403, 'Anda tidak memiliki hak untuk mengubah data bimbingan.');

        $this->validate([
            'selectedMahasiswaId' => ['required', 'integer', 'exists:mahasiswa,id'],
            'bimbinganStatus' => ['required', 'string', 'in:active,inactive'],
        ], [], [
            'selectedMahasiswaId' => 'mahasiswa',
            'bimbinganStatus' => 'status',
        ]);

        if ($this->editingBimbinganId) {
            DosenWali::findOrFail($this->editingBimbinganId)->update([
                'id_mahasiswa' => $this->selectedMahasiswaId,
                'status' => $this->bimbinganStatus,
            ]);
        } else {
            DosenWali::create([
                'id_dosen' => $this->dosenId,
                'id_mahasiswa' => $this->selectedMahasiswaId,
                'status' => $this->bimbinganStatus,
            ]);
        }

        unset($this->bimbinganList);
        $this->closeModal();
        session()->flash('status', 'Data bimbingan berhasil disimpan.');
    }

    public function confirmDeleteBimbingan(int $id): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'dosen wali', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus data bimbingan.');

        $this->confirmingBimbinganDeleteId = $id;
    }

    public function cancelDeleteBimbingan(): void
    {
        $this->confirmingBimbinganDeleteId = null;
    }

    public function deleteBimbingan(): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'dosen wali', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus data bimbingan.');

        if (! $this->confirmingBimbinganDeleteId) {
            return;
        }

        DosenWali::findOrFail($this->confirmingBimbinganDeleteId)->delete();
        $this->confirmingBimbinganDeleteId = null;
        unset($this->bimbinganList);
        session()->flash('status', 'Data bimbingan dihapus.');
    }

    public function confirmDeleteDosen(): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'dosen', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus dosen.');

        $this->confirmingDosenDelete = true;
    }

    public function cancelDeleteDosen(): void
    {
        $this->confirmingDosenDelete = false;
    }

    public function deleteDosen()
    {
        abort_unless(PanelAccess::can(Auth::user(), 'dosen', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus dosen.');

        Dosen::findOrFail($this->dosenId)->delete();

        session()->flash('status', 'Dosen berhasil dihapus.');

        return redirect()->route('admin.administrasi.dosen');
    }

    public function render()
    {
        return view('livewire.admin.dosen.show')->extends('layouts.web');
    }
}
