<?php

namespace App\Livewire\Dosen\Perwalian;

use App\Models\Dosen;
use App\Models\DosenWali;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Locked]
    public int $dosenId;

    public string $search = '';

    public function mount(): void
    {
        $dosen = Dosen::where('id_user', Auth::id())->firstOrFail();
        $this->dosenId = $dosen->id;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Sama persis dengan DosenWaliController::getMyBimbingan, ditambah no_hp (mahasiswa.handphone)
     * yang di API aslinya belum diisi walau sudah ada kolomnya di tipe response frontend.
     */
    public function rows()
    {
        $query = DosenWali::where('id_dosen', $this->dosenId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->with(['mahasiswa.prodi.jenjang', 'mahasiswa.semester_masuk', 'mahasiswa.status_akademik']);

        if ($this->search !== '') {
            $query->whereHas('mahasiswa', function ($q) {
                $q->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('nim', 'like', "%{$this->search}%");
            });
        }

        return $query->orderByDesc('created_at')->paginate(10);
    }

    public function render()
    {
        return view('livewire.dosen.perwalian.index', [
            'rows' => $this->rows(),
        ])->extends('layouts.dosen');
    }
}
