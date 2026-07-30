<?php

namespace App\Livewire\Mahasiswa\Pembayaran;

use App\Models\Mahasiswa;
use App\Models\Pembayaran;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Index extends Component
{
    #[Locked]
    public int $mahasiswaId;

    public function mount(): void
    {
        $mahasiswa = Mahasiswa::where('id_user', Auth::id())->firstOrFail();
        $this->mahasiswaId = $mahasiswa->id;
    }

    /**
     * Sama persis dengan PembayaranController::getPembayaranMahasiswa.
     */
    #[Computed]
    public function pembayaranList()
    {
        return Pembayaran::with(['tagihan.semester', 'tagihan.tagihanRinci.komponenBiaya'])
            ->whereHas('tagihan', fn ($q) => $q->where('id_mahasiswa', $this->mahasiswaId))
            ->whereNull('deleted_at')
            ->orderByDesc('tanggal_pembayaran')
            ->orderByDesc('created_at')
            ->get();
    }

    public function render()
    {
        return view('livewire.mahasiswa.pembayaran.index')->extends('layouts.mahasiswa');
    }
}
