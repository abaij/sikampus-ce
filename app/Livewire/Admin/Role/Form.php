<?php

namespace App\Livewire\Admin\Role;

use App\Models\Role;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?int $roleId = null;

    public string $code = '';

    public string $name = '';

    public function mount(?int $id = null): void
    {
        $this->roleId = $id;

        if ($id === null) {
            return;
        }

        $role = Role::findOrFail($id);

        $this->code = (string) $role->code;
        $this->name = $role->name;
    }

    /**
     * Rule sama persis dengan RoleController::store/update.
     */
    protected function rules(): array
    {
        $uniqueCode = Rule::unique('roles', 'code');

        if ($this->roleId) {
            $uniqueCode = $uniqueCode->ignore($this->roleId);
        }

        return [
            'code' => ['required', 'string', 'max:255', $uniqueCode],
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function save()
    {
        $validated = $this->validate();

        if ($this->roleId) {
            Role::findOrFail($this->roleId)->update($validated);
        } else {
            Role::create($validated);
        }

        session()->flash('status', 'Role berhasil disimpan.');

        return redirect()->route('admin.pengguna.role.index');
    }

    public function render()
    {
        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.role.form')->extends('layouts.web');
    }
}
