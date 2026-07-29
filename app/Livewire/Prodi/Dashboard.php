<?php

namespace App\Livewire\Prodi;

use Livewire\Component;

class Dashboard extends Component
{
    /**
     * Sama persis dengan app/prodi/page.tsx: dashboard-nya sendiri masih stub di frontend
     * (kartu statistik semua placeholder, tidak ada endpoint agregat) — jangan mengarang metrik
     * baru di sini, mirror apa adanya sampai frontend punya endpoint sungguhan.
     */
    public function render()
    {
        return view('livewire.prodi.dashboard')->extends('layouts.prodi');
    }
}
