<?php

namespace App\Livewire\Prodi\Dosen;

use App\Models\Dosen;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    public int $dosenId;

    /**
     * Sama persis dengan DosenController::show — findOrFail biasa, tanpa pengecekan scope
     * (route /prodi/dosen/{dosen} memakai controller yang sama, lihat routes/api.php).
     */
    public function mount(int $id): void
    {
        $this->dosenId = $id;

        Dosen::findOrFail($id);
    }

    #[Computed]
    public function dosen(): Dosen
    {
        return Dosen::with(['provinsi', 'kota', 'negara'])->findOrFail($this->dosenId);
    }

    public function render()
    {
        return view('livewire.prodi.dosen.show')->extends('layouts.prodi');
    }
}
