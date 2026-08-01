<?php

namespace App\Livewire\Admin\JalurMasuk;

use App\Models\JalurMasuk;
use App\Support\PanelAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public int $perPage = 10;

    public ?int $confirmingDeleteId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'jalur masuk', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus jalur masuk.');

        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'jalur masuk', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus jalur masuk.');

        if (! $this->confirmingDeleteId) {
            return;
        }

        JalurMasuk::findOrFail($this->confirmingDeleteId)->delete();

        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    /**
     * Sama persis dengan JalurMasukController::index.
     */
    public function render()
    {
        $query = JalurMasuk::query();

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('nama', 'like', "%{$this->search}%")
                    ->orWhere('deskripsi', 'like', "%{$this->search}%");
            });
        }

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        $jalurMasukList = $query->orderBy('nama')->paginate($this->perPage);

        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.jalur-masuk.index', [
            'jalurMasukList' => $jalurMasukList,
        ])->extends('layouts.web');
    }
}
