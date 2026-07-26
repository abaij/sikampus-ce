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

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('name', 'Citra Dewi')
        ->set('email', 'citra@example.com')
        ->set('password', 'password123')
        ->set('role', 'admin')
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
    $akademik = Role::create(['code' => 'akademik', 'name' => 'Akademik', 'guard_name' => 'web']);
    $keuangan = Role::create(['code' => 'keuangan', 'name' => 'Keuangan', 'guard_name' => 'web']);

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
