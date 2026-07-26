<?php

namespace App\Livewire\Admin\Permission;

use Illuminate\Validation\Rule;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class Form extends Component
{
    public ?int $permissionId = null;

    public string $name = '';

    public string $guard_name = 'web';

    public function mount(?int $id = null): void
    {
        $this->permissionId = $id;

        if ($id === null) {
            return;
        }

        $permission = Permission::findOrFail($id);

        $this->name = $permission->name;
        $this->guard_name = $permission->guard_name;
    }

    /**
     * Rule sama persis dengan PermissionController::store/update.
     */
    protected function rules(): array
    {
        $uniqueName = Rule::unique('permissions', 'name')->where(fn ($query) => $query->where('guard_name', $this->guard_name ?: 'web'));

        if ($this->permissionId) {
            $uniqueName = $uniqueName->ignore($this->permissionId);
        }

        return [
            'name' => ['required', 'string', 'max:255', $uniqueName],
            'guard_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function save()
    {
        $validated = $this->validate();
        $validated['guard_name'] = $validated['guard_name'] ?: 'web';

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        if ($this->permissionId) {
            Permission::findOrFail($this->permissionId)->update($validated);
        } else {
            Permission::create($validated);
        }

        session()->flash('status', 'Permission berhasil disimpan.');

        return redirect()->route('admin.pengguna.permission.index');
    }

    public function render()
    {
        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.permission.form')->extends('layouts.web');
    }
}
