<?php

namespace App\Livewire\Admin\Pengguna;

use App\Models\Fakultas;
use App\Models\Prodi;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class Show extends Component
{
    public int $penggunaId;

    public string $activeTab = 'role';

    public bool $showRoleForm = false;

    public array $selectedRoleIds = [];

    public array $selectedFakultasIds = [];

    public array $selectedProdiIds = [];

    public array $permissionForm = [];

    public bool $confirmingDelete = false;

    public function mount(int $id): void
    {
        $this->penggunaId = $id;

        User::findOrFail($id);
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;

        if ($tab === 'permission' && $this->permissionForm === []) {
            $this->loadPermissionForm();
        }
    }

    public function isSuperadminActor(): bool
    {
        return (bool) Auth::user()?->isSuperadmin();
    }

    #[Computed]
    public function pengguna(): User
    {
        return User::findOrFail($this->penggunaId);
    }

    #[Computed]
    public function roleOptions()
    {
        return Role::orderBy('name')->get(['id', 'code', 'name']);
    }

    #[Computed]
    public function fakultasOptions()
    {
        return Fakultas::whereNull('deleted_at')->orderBy('nama')->get(['id', 'nama']);
    }

    #[Computed]
    public function prodiOptions()
    {
        return Prodi::whereNull('deleted_at')->orderBy('nama')->get(['id', 'nama', 'id_fakultas']);
    }

    /**
     * Sama dengan UserController::getRolesAndScopes.
     */
    #[Computed]
    public function rolesData(): array
    {
        $pengguna = $this->pengguna;
        $pengguna->load('roles');

        $data = [];
        foreach ($pengguna->roles as $role) {
            $roleCode = $role->code ?? $role->name;
            $data[$roleCode] = [
                'role_id' => $role->id,
                'scopes' => UserRoleScope::buildScopesPayload($pengguna->id, $role->id),
            ];
        }

        return $data;
    }

    public function openRoleForm(): void
    {
        $rolesData = $this->rolesData;

        $fakultasIds = [];
        $prodiIds = [];
        foreach ($rolesData as $row) {
            $scopes = is_array($row['scopes']) ? $row['scopes'] : [];
            foreach (($scopes['fakultas'] ?? []) as $id) {
                $fakultasIds[] = (int) $id;
            }
            foreach (($scopes['prodi'] ?? []) as $id) {
                $prodiIds[] = (int) $id;
            }
        }

        $this->selectedRoleIds = collect($rolesData)->pluck('role_id')->all();
        $this->selectedFakultasIds = array_values(array_unique($fakultasIds));
        $this->selectedProdiIds = array_values(array_unique($prodiIds));
        $this->resetErrorBag();
        $this->showRoleForm = true;
    }

    public function cancelRoleForm(): void
    {
        $this->showRoleForm = false;
        $this->selectedRoleIds = [];
        $this->selectedFakultasIds = [];
        $this->selectedProdiIds = [];
        $this->resetErrorBag();
    }

    public function updatedSelectedFakultasIds(): void
    {
        if ($this->selectedFakultasIds !== []) {
            $this->selectedProdiIds = [];
        }
    }

    public function updatedSelectedProdiIds(): void
    {
        if ($this->selectedProdiIds !== []) {
            $this->selectedFakultasIds = [];
        }
    }

    /**
     * Sama dengan UserController::storeRolesAndScopes.
     */
    public function saveRoleScope(): void
    {
        abort_unless($this->isSuperadminActor(), 403);

        $this->validate([
            'selectedRoleIds' => ['required', 'array', 'min:1'],
            'selectedRoleIds.*' => ['integer', 'exists:roles,id'],
            'selectedFakultasIds.*' => ['integer', 'exists:fakultas,id'],
            'selectedProdiIds.*' => ['integer', 'exists:prodi,id'],
        ]);

        if ($this->selectedFakultasIds !== [] && $this->selectedProdiIds !== []) {
            $this->addError('selectedProdiIds', 'Pilih salah satu: scope Fakultas (akses semua prodi di fakultas tsb) atau scope Program Studi (akses prodi tertentu saja). Tidak bisa keduanya sekaligus.');

            return;
        }

        if (count($this->selectedProdiIds) > 1) {
            $jumlahFakultasBerbeda = Prodi::whereIn('id', $this->selectedProdiIds)->distinct()->count('id_fakultas');
            if ($jumlahFakultasBerbeda > 1) {
                $this->addError('selectedProdiIds', 'Scope prodi untuk satu role tidak boleh lintas fakultas. Pilih prodi dari satu fakultas yang sama, atau gunakan scope fakultas.');

                return;
            }
        }

        $pengguna = $this->pengguna;

        DB::beginTransaction();
        try {
            $roleModels = Role::whereIn('id', $this->selectedRoleIds)->get();
            $pengguna->syncRoles($roleModels);

            DB::table('user_role_scopes')
                ->where('id_user', $pengguna->id)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);

            foreach ($this->selectedRoleIds as $roleId) {
                foreach ($this->selectedFakultasIds as $fakultasId) {
                    DB::table('user_role_scopes')->insert([
                        'id_user' => $pengguna->id,
                        'id_role' => $roleId,
                        'id_scope' => $fakultasId,
                        'scope_type' => 'fakultas',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                foreach ($this->selectedProdiIds as $prodiId) {
                    DB::table('user_role_scopes')->insert([
                        'id_user' => $pengguna->id,
                        'id_role' => $roleId,
                        'id_scope' => $prodiId,
                        'scope_type' => 'prodi',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        unset($this->rolesData);
        $this->cancelRoleForm();
        session()->flash('status', 'Role dan scope berhasil disimpan.');
    }

    public function deleteRole(string $roleCode): void
    {
        abort_unless($this->isSuperadminActor(), 403);

        $rolesData = $this->rolesData;
        $removeId = $rolesData[$roleCode]['role_id'] ?? null;
        $remainingIds = array_values(array_diff(collect($rolesData)->pluck('role_id')->all(), [$removeId]));

        if ($remainingIds === []) {
            session()->flash('error', 'Pengguna harus memiliki minimal satu role.');

            return;
        }

        $pengguna = $this->pengguna;
        $pengguna->syncRoles(Role::whereIn('id', $remainingIds)->get());

        DB::table('user_role_scopes')
            ->where('id_user', $pengguna->id)
            ->where('id_role', $removeId)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);

        unset($this->rolesData);
        session()->flash('status', 'Role berhasil dihapus.');
    }

    public function deleteScope(string $scopeType, int $scopeId): void
    {
        abort_unless($this->isSuperadminActor(), 403);

        DB::table('user_role_scopes')
            ->where('id_user', $this->penggunaId)
            ->where('scope_type', $scopeType)
            ->where('id_scope', $scopeId)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);

        unset($this->rolesData);
        session()->flash('status', 'Scope berhasil dihapus.');
    }

    /**
     * Sama dengan pola parsing nama permission di UserController::getPermissions.
     *
     * @return array{0: string, 1: string}
     */
    private function groupPermissionName(string $name): array
    {
        $parts = explode(' ', $name);
        $actions = ['read', 'write', 'delete', 'view', 'manage', 'create', 'update'];

        $resource = '';
        $action = '';

        if (count($parts) >= 2) {
            if (in_array(strtolower($parts[0]), $actions)) {
                $action = strtolower($parts[0]);
                $resource = implode(' ', array_slice($parts, 1));
            } else {
                $lastPart = strtolower($parts[count($parts) - 1]);
                if (in_array($lastPart, $actions)) {
                    $action = $lastPart;
                    $resource = implode(' ', array_slice($parts, 0, -1));
                } else {
                    $resource = $name;
                    $action = 'manage';
                }
            }
        } else {
            $resource = $name;
            $action = 'manage';
        }

        if (in_array($action, ['view', 'manage'])) {
            $action = 'read';
        } elseif (in_array($action, ['create', 'update'])) {
            $action = 'write';
        }

        return [$resource, $action];
    }

    private function loadPermissionForm(): void
    {
        $pengguna = $this->pengguna;
        $directPermissions = $pengguna->getDirectPermissions()->pluck('name')->toArray();

        $allPermissions = Permission::where('guard_name', 'web')->orderBy('name')->get();

        $grouped = [];
        foreach ($allPermissions as $permission) {
            [$resource, $action] = $this->groupPermissionName($permission->name);

            if (! isset($grouped[$resource])) {
                $grouped[$resource] = ['resource' => $resource, 'read' => false, 'write' => false, 'delete' => false];
            }

            if (in_array($permission->name, $directPermissions, true)) {
                $grouped[$resource][$action] = true;
            }
        }

        $this->permissionForm = array_values($grouped);
    }

    /**
     * Sama dengan UserController::storeUserPermissions.
     */
    public function savePermissions(): void
    {
        abort_unless($this->isSuperadminActor(), 403);

        $pengguna = $this->pengguna;

        $allPermissions = Permission::where('guard_name', 'web')->get();
        $permissionMap = [];
        foreach ($allPermissions as $perm) {
            [$resource, $action] = $this->groupPermissionName($perm->name);
            $permissionMap[$resource][$action] = $perm->name;
        }

        $permissionsToAssign = [];
        foreach ($this->permissionForm as $row) {
            $resource = $row['resource'];
            if (! isset($permissionMap[$resource])) {
                continue;
            }

            foreach (['read', 'write', 'delete'] as $action) {
                if (! empty($row[$action]) && isset($permissionMap[$resource][$action])) {
                    $permissionsToAssign[] = $permissionMap[$resource][$action];
                }
            }
        }

        $pengguna->syncPermissions($permissionsToAssign);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        session()->flash('status', 'Permissions berhasil disimpan.');
    }

    public function confirmDeleteUser(): void
    {
        $this->confirmingDelete = true;
    }

    public function cancelDeleteUser(): void
    {
        $this->confirmingDelete = false;
    }

    public function deleteUser()
    {
        User::findOrFail($this->penggunaId)->delete();

        session()->flash('status', 'Pengguna berhasil dihapus.');

        return redirect()->route('admin.pengguna.index');
    }

    public function render()
    {
        // ->extends() (bukan #[Layout] attribute) — lihat catatan di App\Livewire\Admin\Fakultas\Index::render()
        return view('livewire.admin.pengguna.show')->extends('layouts.web');
    }
}
