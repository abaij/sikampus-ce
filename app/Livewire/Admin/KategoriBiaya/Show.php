<?php

namespace App\Livewire\Admin\KategoriBiaya;

use App\Models\KategoriBiaya;
use App\Models\KategoriBiayaMahasiswa;
use App\Models\Mahasiswa;
use App\Models\Semester;
use App\Support\PanelAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Show extends Component
{
    use WithPagination;

    public int $kategoriBiayaId;

    public string $search = '';

    public int $perPage = 10;

    public bool $showModal = false;

    public string $mahasiswaSearch = '';

    public ?int $selectedMahasiswaId = null;

    public string $selectedMahasiswaLabel = '';

    // string, bukan ?int — <select> mengirim '' untuk opsi placeholder, dan itu tidak bisa
    // dicast ke ?int (lihat catatan TypeError pada properti filter di SKILL.md).
    public string $selectedSemesterId = '';

    public function mount(int $id): void
    {
        $this->kategoriBiayaId = $id;

        KategoriBiaya::findOrFail($id);

        $this->selectedSemesterId = (string) Semester::where('is_active', true)->value('id');
    }

    public function updatingSearch(): void
    {
        // pageName khusus 'mhs_page' (bukan default 'page') mengikuti pola di
        // DosenWali\Show / Kurikulum\Show — mencegah paginator daftar mahasiswa di halaman ini
        // bentrok dengan query 'page' lain (mis. kalau nanti halaman ini ikut memforward state
        // pencarian/halaman dari Index seperti pola ForwardsIndexState).
        $this->resetPage('mhs_page');
    }

    #[Computed]
    public function kategoriBiaya(): KategoriBiaya
    {
        return KategoriBiaya::withCount([
            'kategori_biaya_mahasiswa as jumlah_mahasiswa' => function ($q) {
                $q->where('status', 'active');
            },
        ])->findOrFail($this->kategoriBiayaId);
    }

    /**
     * Sama persis dengan KategoriBiayaController::mahasiswa.
     */
    #[Computed]
    public function mahasiswaList()
    {
        $query = Mahasiswa::with([
            'prodi',
            'status_akademik',
            'kategori_biaya_mahasiswa' => function ($q) {
                $q->where('id_kategori_biaya', $this->kategoriBiayaId)
                    ->where('status', 'active')
                    ->with('semester');
            },
        ])->whereHas('kategori_biaya_mahasiswa', function ($q) {
            $q->where('id_kategori_biaya', $this->kategoriBiayaId)
                ->where('status', 'active');
        });

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('nim', 'like', "%{$this->search}%");
            });
        }

        // pageName 'mhs_page' — lihat catatan di updatingSearch().
        return $query->orderBy('nama')->paginate($this->perPage, ['*'], 'mhs_page');
    }

    #[Computed]
    public function semesterOptions()
    {
        return Semester::orderByDesc('kode')->get(['id', 'nama', 'kode']);
    }

    /**
     * Cari-lalu-pilih untuk modal tambah mahasiswa — mirip DosenWali\Show::mahasiswaResults.
     */
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

    public function openModal(): void
    {
        // Tombol pemicu ini disembunyikan di Blade untuk user tanpa hak ubah, tapi method
        // Livewire tetap bisa dipanggil langsung lewat request yang dipalsukan — pengecekan di
        // sini dan di save() adalah otoritas sebenarnya, bukan sekadar UI.
        abort_unless(PanelAccess::can(Auth::user(), 'kategori biaya', 'update'), 403, 'Anda tidak memiliki hak untuk mengubah kategori biaya.');

        $this->resetValidation();
        $this->selectedMahasiswaId = null;
        $this->selectedMahasiswaLabel = '';
        $this->mahasiswaSearch = '';
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function selectMahasiswa(int $id, string $label): void
    {
        $this->selectedMahasiswaId = $id;
        $this->selectedMahasiswaLabel = $label;
        $this->mahasiswaSearch = '';
    }

    /**
     * Sama persis dengan KategoriBiayaMahasiswaController::store — status baru selalu 'active',
     * dan penetapan kategori biaya aktif mahasiswa ini yang lama (jika ada) otomatis dinonaktifkan.
     */
    public function save(): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'kategori biaya', 'update'), 403, 'Anda tidak memiliki hak untuk mengubah kategori biaya.');

        $this->validate([
            'selectedMahasiswaId' => ['required', 'integer', 'exists:mahasiswa,id'],
            'selectedSemesterId' => ['required', 'integer', 'exists:semester,id'],
        ], [], ['selectedMahasiswaId' => 'mahasiswa', 'selectedSemesterId' => 'semester']);

        $idSemester = (int) $this->selectedSemesterId;

        $exists = KategoriBiayaMahasiswa::where('id_mahasiswa', $this->selectedMahasiswaId)
            ->where('id_kategori_biaya', $this->kategoriBiayaId)
            ->where('id_semester', $idSemester)
            ->exists();

        if ($exists) {
            $this->addError('selectedMahasiswaId', 'Kombinasi mahasiswa, kategori biaya, dan semester sudah ada.');

            return;
        }

        $row = KategoriBiayaMahasiswa::create([
            'id_mahasiswa' => $this->selectedMahasiswaId,
            'id_kategori_biaya' => $this->kategoriBiayaId,
            'id_semester' => $idSemester,
            'status' => 'active',
        ]);

        KategoriBiayaMahasiswa::where('id_mahasiswa', $this->selectedMahasiswaId)
            ->where('id', '!=', $row->id)
            ->update(['status' => 'inactive']);

        unset($this->kategoriBiaya, $this->mahasiswaList);
        $this->closeModal();
        session()->flash('status', 'Mahasiswa berhasil ditambahkan ke kategori biaya.');
    }

    public function render()
    {
        return view('livewire.admin.kategori-biaya.show')->extends('layouts.web');
    }
}
