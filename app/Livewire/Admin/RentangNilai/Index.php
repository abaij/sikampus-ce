<?php

namespace App\Livewire\Admin\RentangNilai;

use App\Models\Jenjang;
use App\Models\RentangNilai;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    public string $filterJenjang = '';

    public ?int $confirmingDeleteId = null;

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function jenjangOptions(): array
    {
        return Jenjang::whereNull('deleted_at')
            ->orderBy('nama')
            ->get(['id', 'nama', 'kode'])
            ->mapWithKeys(fn ($j) => [$j->id => $j->nama.($j->kode ? ' ('.$j->kode.')' : '')])
            ->all();
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(): void
    {
        if (! $this->confirmingDeleteId) {
            return;
        }

        RentangNilai::findOrFail($this->confirmingDeleteId)->delete();

        $this->confirmingDeleteId = null;
    }

    /**
     * Sama persis dengan RentangNilaiController::index — tidak dipaginasi, mengikuti API yang
     * mengembalikan array polos (bukan { data, total, ... }) karena jumlah barisnya kecil.
     */
    public function render()
    {
        $query = RentangNilai::with('jenjang');

        if ($this->filterJenjang !== '') {
            $query->where('id_jenjang', (int) $this->filterJenjang);
        }

        $rentangNilaiList = $query->orderByDesc('nilai_tinggi')->orderBy('nilai_huruf')->get();

        return view('livewire.admin.rentang-nilai.index', [
            'rentangNilaiList' => $rentangNilaiList,
        ])->extends('layouts.web');
    }
}
