<?php

namespace App\Livewire\Admin\Pengguna;

use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterRole = '';

    public string $filterStatus = '';

    public int $perPage = 10;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterRole(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    /**
     * Sama persis dengan UserController::index.
     */
    public function render()
    {
        $query = User::query();

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%");
            });
        }

        if ($this->filterRole !== '') {
            $query->where('role', $this->filterRole);
        }

        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }

        $penggunaList = $query->orderBy('name')->paginate($this->perPage);

        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.pengguna.index', [
            'penggunaList' => $penggunaList,
        ])->extends('layouts.web');
    }
}
