<?php

namespace App\Livewire\Admin\Semester;

use App\Models\Semester;
use App\Support\PanelAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public int $perPage = 10;

    public ?int $confirmingDeleteId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'semester', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus semester.');

        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(): void
    {
        abort_unless(PanelAccess::can(Auth::user(), 'semester', 'delete'), 403, 'Anda tidak memiliki hak untuk menghapus semester.');

        if (! $this->confirmingDeleteId) {
            return;
        }

        Semester::findOrFail($this->confirmingDeleteId)->delete();

        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    /**
     * Sama persis dengan SemesterController::index.
     */
    public function render()
    {
        $query = Semester::query();

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('kode', 'like', "%{$this->search}%")
                    ->orWhere('nama', 'like', "%{$this->search}%");
            });
        }

        $semesterList = $query->orderBy('kode', 'desc')->paginate($this->perPage);

        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.semester.index', [
            'semesterList' => $semesterList,
        ])->extends('layouts.web');
    }
}
