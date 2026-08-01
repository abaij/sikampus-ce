<?php

use App\Livewire\Admin\Pengguna\Form;
use App\Livewire\Admin\Pengguna\Show;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

it('renders index and create form as full pages', function () {
    $admin = adminUser();
    User::factory()->create(['name' => 'Budi Santoso', 'role' => 'admin', 'status' => 'active']);

    $this->actingAs($admin)->get(route('admin.pengguna.index'))->assertOk()->assertSee('Budi Santoso');
    $this->actingAs($admin)->get(route('admin.pengguna.create'))->assertOk()->assertSee('Tambah Pengguna');
});

it('creates and updates a pengguna, then shows the detail page', function () {
    $admin = adminUser();
    $superadminRole = Role::where('name', 'Superadmin')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('name', 'Citra Dewi')
        ->set('email', 'citra@example.com')
        ->set('password', 'password123')
        ->set('role', 'admin')
        ->set('spatieRoleId', $superadminRole->id)
        ->call('save')
        ->assertRedirect();

    $pengguna = User::where('email', 'citra@example.com')->firstOrFail();
    expect($pengguna->role)->toBe('admin');
    expect($pengguna->status)->toBe('active');

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $pengguna->id])
        ->assertSet('name', 'Citra Dewi')
        ->set('name', 'Citra Dewi Lestari')
        ->call('save')
        ->assertRedirect(route('admin.pengguna.show', $pengguna->id));

    expect($pengguna->fresh()->name)->toBe('Citra Dewi Lestari');

    $this->actingAs($admin)->get(route('admin.pengguna.show', $pengguna->id))
        ->assertOk()
        ->assertSee('Citra Dewi Lestari')
        ->assertSee('citra@example.com');
});

it('assigns a role and scope to a pengguna from the show page', function () {
    $admin = adminUser();
    $pengguna = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    $akademik = Role::firstOrCreate(['name' => 'Akademik', 'guard_name' => 'web'], ['code' => 'akademik']);
    $keuangan = Role::firstOrCreate(['name' => 'Keuangan', 'guard_name' => 'web'], ['code' => 'keuangan']);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $pengguna->id])
        ->call('openRoleForm')
        ->set('selectedRoleIds', [$akademik->id, $keuangan->id])
        ->call('saveRoleScope');

    expect($pengguna->fresh()->hasRole('Akademik'))->toBeTrue();
    expect($pengguna->fresh()->hasRole('Keuangan'))->toBeTrue();

    // Menghapus satu dari dua role tersisa boleh, tapi menghapus satu-satunya role harus ditolak.
    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $pengguna->id])
        ->call('deleteRole', 'akademik');

    expect($pengguna->fresh()->hasRole('Akademik'))->toBeFalse();
    expect($pengguna->fresh()->hasRole('Keuangan'))->toBeTrue();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $pengguna->id])
        ->call('deleteRole', 'keuangan');

    expect($pengguna->fresh()->hasRole('Keuangan'))->toBeTrue();
});

it('automatically assigns the chosen spatie role — and its permissions — when creating an admin account', function () {
    $admin = adminUser();
    $keuanganRole = Role::where('name', 'Keuangan')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('name', 'Dedi Keuangan')
        ->set('email', 'dedi.keuangan@example.com')
        ->set('password', 'password123')
        ->set('role', 'admin')
        ->set('spatieRoleId', $keuanganRole->id)
        ->call('save')
        ->assertRedirect();

    $pengguna = User::where('email', 'dedi.keuangan@example.com')->firstOrFail();

    expect($pengguna->hasRole('Keuangan'))->toBeTrue();
    expect($pengguna->hasRole('Akademik'))->toBeFalse();
    // Permission diwarisi dari role_has_permissions (diseed PermissionSeeder), bukan disalin manual.
    expect($pengguna->can('manage tagihan'))->toBeTrue();
    expect($pengguna->can('manage mata kuliah'))->toBeFalse();
});

it('requires a spatie role when creating an admin-type account', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('name', 'Tanpa Role')
        ->set('email', 'tanpa.role@example.com')
        ->set('password', 'password123')
        ->set('role', 'admin')
        ->call('save')
        ->assertHasErrors(['spatieRoleId' => 'required']);

    expect(User::where('email', 'tanpa.role@example.com')->exists())->toBeFalse();
});

it('deletes a pengguna from the show page', function () {
    $admin = adminUser();
    $pengguna = User::factory()->create(['role' => 'admin', 'status' => 'active']);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $pengguna->id])
        ->call('confirmDeleteUser')
        ->call('deleteUser')
        ->assertRedirect(route('admin.pengguna.index'));

    expect(User::find($pengguna->id))->toBeNull();
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.pengguna.index'))->assertRedirect(route('login'));
});
