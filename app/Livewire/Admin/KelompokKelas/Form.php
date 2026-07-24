<?php

namespace App\Livewire\Admin\KelompokKelas;

use App\Models\KelompokKelas;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\Semester;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Form extends Component
{
    use WithPagination;

    public ?int $kelompokKelasId = null;

    public string $nama = '';

    public ?int $id_prodi = null;

    public string $keterangan = '';

    // Filter tabel "Mahasiswa dalam kelas mahasiswa ini" — string (bukan ?int) karena dibind ke
    // <select> polos; lihat catatan yang sama di App\Livewire\Admin\Dosen\Show::$selectedSemester.
    public string $mhsFilterProdi = '';

    public string $mhsFilterSemester = '';

    public bool $showAddMahasiswaModal = false;

    public string $mahasiswaSearch = '';

    public ?int $selectedMahasiswaId = null;

    public string $selectedMahasiswaLabel = '';

    public ?int $confirmingRemoveMahasiswaId = null;

    public function mount(?int $id = null): void
    {
        $this->kelompokKelasId = $id;

        if ($id === null) {
            return;
        }

        $kelompokKelas = KelompokKelas::findOrFail($id);

        $this->nama = $kelompokKelas->nama;
        $this->id_prodi = $kelompokKelas->id_prodi;
        $this->keterangan = (string) $kelompokKelas->keterangan;
    }

    /**
     * Rule sama persis dengan KelompokKelasController::store/update.
     */
    protected function rules(): array
    {
        $uniqueNama = Rule::unique('kelompok_kelas', 'nama');

        if ($this->kelompokKelasId) {
            $uniqueNama = $uniqueNama->ignore($this->kelompokKelasId);
        }

        return [
            'nama' => ['required', 'string', 'max:255', $uniqueNama],
            'id_prodi' => ['nullable', 'integer', 'exists:prodi,id'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function save()
    {
        $validated = $this->validate();

        if ($validated['keterangan'] === '') {
            $validated['keterangan'] = null;
        }

        if ($this->kelompokKelasId) {
            KelompokKelas::findOrFail($this->kelompokKelasId)->update($validated);
        } else {
            KelompokKelas::create($validated);
        }

        session()->flash('status', 'Kelas mahasiswa berhasil disimpan.');

        return redirect()->route('admin.administrasi.kelas-mahasiswa');
    }

    public function updatingMhsFilterProdi(): void
    {
        $this->resetPage();
    }

    public function updatingMhsFilterSemester(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function prodiFilterOptions()
    {
        return Prodi::whereNull('deleted_at')->orderBy('nama')->get(['id', 'nama']);
    }

    #[Computed]
    public function semesterFilterOptions()
    {
        return Semester::orderByDesc('kode')->get(['id', 'kode', 'nama']);
    }

    /**
     * Sama persis dengan MahasiswaController::index dengan filter id_kelompok_kelas terkunci
     * ke kelas mahasiswa ini — mirror dari tabel di app/admin/kelompok-kelas/[id]/page.tsx.
     */
    #[Computed]
    public function mahasiswaInGroup()
    {
        if (! $this->kelompokKelasId) {
            return null;
        }

        $query = Mahasiswa::query()
            ->with(['prodi', 'semester_masuk', 'status_akademik'])
            ->where('id_kelompok_kelas', $this->kelompokKelasId);

        if ($this->mhsFilterProdi !== '') {
            $query->where('id_prodi', (int) $this->mhsFilterProdi);
        }

        if ($this->mhsFilterSemester !== '') {
            $query->where('id_semester_masuk', (int) $this->mhsFilterSemester);
        }

        return $query->orderBy('nama')->paginate(10);
    }

    #[Computed]
    public function mahasiswaSearchResults()
    {
        if ($this->mahasiswaSearch === '') {
            return collect();
        }

        return Mahasiswa::query()
            ->where(function ($q) {
                $q->where('nama', 'like', "%{$this->mahasiswaSearch}%")
                    ->orWhere('nim', 'like', "%{$this->mahasiswaSearch}%");
            })
            ->where(function ($q) {
                $q->whereNull('id_kelompok_kelas')
                    ->orWhere('id_kelompok_kelas', '!=', $this->kelompokKelasId);
            })
            ->orderBy('nama')
            ->limit(20)
            ->get(['id', 'nama', 'nim']);
    }

    public function openAddMahasiswaModal(): void
    {
        $this->selectedMahasiswaId = null;
        $this->selectedMahasiswaLabel = '';
        $this->mahasiswaSearch = '';
        $this->showAddMahasiswaModal = true;
    }

    public function closeAddMahasiswaModal(): void
    {
        $this->showAddMahasiswaModal = false;
        $this->selectedMahasiswaId = null;
        $this->selectedMahasiswaLabel = '';
        $this->mahasiswaSearch = '';
    }

    public function selectMahasiswaOption(int $id, string $label): void
    {
        $this->selectedMahasiswaId = $id;
        $this->selectedMahasiswaLabel = $label;
        $this->mahasiswaSearch = '';
    }

    public function addMahasiswaToKelompok(): void
    {
        if (! $this->kelompokKelasId || ! $this->selectedMahasiswaId) {
            return;
        }

        Mahasiswa::findOrFail($this->selectedMahasiswaId)->update([
            'id_kelompok_kelas' => $this->kelompokKelasId,
        ]);

        unset($this->mahasiswaInGroup);
        $this->closeAddMahasiswaModal();
        session()->flash('status', 'Mahasiswa ditambahkan ke kelas mahasiswa.');
    }

    public function confirmRemoveMahasiswa(int $id): void
    {
        $this->confirmingRemoveMahasiswaId = $id;
    }

    public function cancelRemoveMahasiswa(): void
    {
        $this->confirmingRemoveMahasiswaId = null;
    }

    public function removeMahasiswaFromKelompok(): void
    {
        if (! $this->confirmingRemoveMahasiswaId) {
            return;
        }

        Mahasiswa::findOrFail($this->confirmingRemoveMahasiswaId)->update([
            'id_kelompok_kelas' => null,
        ]);

        $this->confirmingRemoveMahasiswaId = null;
        unset($this->mahasiswaInGroup);
        session()->flash('status', 'Mahasiswa dikeluarkan dari kelas mahasiswa.');
    }

    public function render()
    {
        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.kelompok-kelas.form', [
            'prodiOptions' => Prodi::whereNull('deleted_at')->orderBy('nama')->get(['id', 'nama']),
        ])->extends('layouts.web');
    }
}
