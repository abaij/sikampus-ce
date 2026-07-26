<?php

namespace App\Livewire\Admin\Permission;

use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

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

        Permission::findOrFail($this->confirmingDeleteId)->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->confirmingDeleteId = null;
        $this->resetPage();
    }

    /**
     * Sama persis dengan PermissionController::index (guard_name default 'web').
     */
    public function render()
    {
        $query = Permission::where('guard_name', 'web');

        if ($this->search !== '') {
            $query->where('name', 'like', "%{$this->search}%");
        }

        $permissionList = $query->orderBy('name')->paginate($this->perPage);

        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.permission.index', [
            'permissionList' => $permissionList,
        ])->extends('layouts.web');
    }
}
