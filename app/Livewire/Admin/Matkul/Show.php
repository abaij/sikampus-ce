<?php

namespace App\Livewire\Admin\Matkul;

use App\Livewire\Admin\Matkul\Concerns\ForwardsIndexState;
use App\Models\Matkul;
use App\Models\MatkulPrasyarat;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    use ForwardsIndexState;

    public int $matkulId;

    public Matkul $matkul;

    public bool $showPrasyaratModal = false;

    public ?int $editingPrasyaratId = null;

    public string $prasyaratSearch = '';

    public ?int $selectedPrasyaratId = null;

    public string $selectedPrasyaratLabel = '';

    public ?int $confirmingDeletePrasyaratId = null;

    public bool $confirmingDeleteMatkul = false;

    public function mount(int $id): void
    {
        $this->matkulId = $id;

        $matkul = Matkul::with(['prodi.jenjang', 'jenisMatkul'])->findOrFail($id);

        $this->ensureAccess($matkul);

        $this->matkul = $matkul;

        $this->resolveBackUrl();
    }

    /**
     * Sama persis dengan MatkulPrasyaratController::ensureMatkulAccess.
     */
    private function ensureAccess(Matkul $matkul): void
    {
        $user = Auth::user();
        if ($user && $user->hasScopeRestriction()) {
            $allowedProdiIds = $user->getAllowedProdiIds();
            if ($allowedProdiIds !== null && ! in_array((int) $matkul->id_prodi, $allowedProdiIds, true)) {
                abort(403, 'Anda tidak memiliki akses ke mata kuliah ini.');
            }
        }
    }

    #[Computed]
    public function prasyaratList()
    {
        return MatkulPrasyarat::with(['matkulPrasyarat.prodi.jenjang', 'matkulPrasyarat.jenisMatkul'])
            ->where('id_matkul', $this->matkulId)
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function prasyaratSearchResults()
    {
        if ($this->prasyaratSearch === '') {
            return collect();
        }

        $excludedIds = $this->prasyaratList
            ->when($this->editingPrasyaratId, fn ($rows) => $rows->where('id', '!=', $this->editingPrasyaratId))
            ->pluck('id_matkul_prasyarat');

        return Matkul::query()
            ->where(function ($q) {
                $q->where('kode', 'like', "%{$this->prasyaratSearch}%")
                    ->orWhere('nama', 'like', "%{$this->prasyaratSearch}%");
            })
            ->where('id', '!=', $this->matkulId)
            ->whereNotIn('id', $excludedIds)
            ->when(
                $this->matkul->id_prodi !== null,
                fn ($q) => $q->where('id_prodi', $this->matkul->id_prodi),
                fn ($q) => $q->whereNull('id_prodi')
            )
            ->orderBy('kode')
            ->limit(20)
            ->get(['id', 'kode', 'nama']);
    }

    public function openAddPrasyaratModal(): void
    {
        $this->editingPrasyaratId = null;
        $this->selectedPrasyaratId = null;
        $this->selectedPrasyaratLabel = '';
        $this->prasyaratSearch = '';
        $this->showPrasyaratModal = true;
    }

    public function openEditPrasyaratModal(int $id): void
    {
        $row = $this->prasyaratList->firstWhere('id', $id);
        if (! $row) {
            return;
        }

        $this->editingPrasyaratId = $row->id;
        $this->selectedPrasyaratId = $row->id_matkul_prasyarat;
        $this->selectedPrasyaratLabel = "{$row->matkulPrasyarat->kode} — {$row->matkulPrasyarat->nama}";
        $this->prasyaratSearch = '';
        $this->showPrasyaratModal = true;
    }

    public function closePrasyaratModal(): void
    {
        $this->showPrasyaratModal = false;
        $this->editingPrasyaratId = null;
        $this->selectedPrasyaratId = null;
        $this->selectedPrasyaratLabel = '';
        $this->prasyaratSearch = '';
    }

    public function selectPrasyaratOption(int $id, string $label): void
    {
        $this->selectedPrasyaratId = $id;
        $this->selectedPrasyaratLabel = $label;
        $this->prasyaratSearch = '';
    }

    /**
     * Sama persis dengan MatkulPrasyaratController::store/update — pengecekan prodi sama,
     * duplikat, dan siklus disalin apa adanya.
     */
    public function savePrasyarat(): void
    {
        if (! $this->selectedPrasyaratId) {
            $this->addError('selectedPrasyaratId', 'Pilih mata kuliah prasyarat terlebih dahulu.');

            return;
        }

        $prasyaratId = $this->selectedPrasyaratId;

        if ($prasyaratId === $this->matkulId) {
            $this->addError('selectedPrasyaratId', 'Mata kuliah tidak dapat menjadi prasyarat untuk dirinya sendiri.');

            return;
        }

        $prasyaratMk = Matkul::find($prasyaratId);
        if (! $prasyaratMk) {
            $this->addError('selectedPrasyaratId', 'Mata kuliah prasyarat tidak ditemukan.');

            return;
        }

        if ($this->matkul->id_prodi != $prasyaratMk->id_prodi) {
            $this->addError('selectedPrasyaratId', 'Mata kuliah prasyarat harus dari program studi yang sama.');

            return;
        }

        $duplicate = MatkulPrasyarat::where('id_matkul', $this->matkulId)
            ->where('id_matkul_prasyarat', $prasyaratId)
            ->when($this->editingPrasyaratId, fn ($q) => $q->where('id', '!=', $this->editingPrasyaratId))
            ->exists();
        if ($duplicate) {
            $this->addError('selectedPrasyaratId', 'Mata kuliah prasyarat ini sudah terdaftar.');

            return;
        }

        $cycle = MatkulPrasyarat::where('id_matkul', $prasyaratId)
            ->where('id_matkul_prasyarat', $this->matkulId)
            ->exists();
        if ($cycle) {
            $this->addError('selectedPrasyaratId', 'Tidak dapat menambahkan prasyarat: mata kuliah yang dipilih sudah mensyaratkan mata kuliah ini (siklus).');

            return;
        }

        if ($this->editingPrasyaratId) {
            MatkulPrasyarat::findOrFail($this->editingPrasyaratId)->update([
                'id_matkul_prasyarat' => $prasyaratId,
            ]);
        } else {
            MatkulPrasyarat::create([
                'id_matkul' => $this->matkulId,
                'id_matkul_prasyarat' => $prasyaratId,
            ]);
        }

        unset($this->prasyaratList);
        $this->closePrasyaratModal();
        session()->flash('status', 'Prasyarat berhasil disimpan.');
    }

    public function confirmDeletePrasyarat(int $id): void
    {
        $this->confirmingDeletePrasyaratId = $id;
    }

    public function cancelDeletePrasyarat(): void
    {
        $this->confirmingDeletePrasyaratId = null;
    }

    public function deletePrasyarat(): void
    {
        if (! $this->confirmingDeletePrasyaratId) {
            return;
        }

        MatkulPrasyarat::where('id_matkul', $this->matkulId)
            ->where('id', $this->confirmingDeletePrasyaratId)
            ->firstOrFail()
            ->delete();

        $this->confirmingDeletePrasyaratId = null;
        unset($this->prasyaratList);
        session()->flash('status', 'Prasyarat dihapus.');
    }

    public function confirmDeleteMatkul(): void
    {
        $this->confirmingDeleteMatkul = true;
    }

    public function cancelDeleteMatkul(): void
    {
        $this->confirmingDeleteMatkul = false;
    }

    /**
     * Sama persis dengan MatkulController::destroy.
     */
    public function deleteMatkul()
    {
        $this->ensureAccess($this->matkul);

        $this->matkul->delete();

        session()->flash('status', 'Mata kuliah dihapus.');

        return redirect()->route('admin.akademik.matkul');
    }

    public function render()
    {
        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.matkul.show')->extends('layouts.web');
    }
}
