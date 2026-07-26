<?php

use App\Livewire\Admin\Role\Form;
use App\Livewire\Admin\Role\Index;
use App\Models\Role;
use Livewire\Livewire;

it('renders index and create form as full pages', function () {
    $admin = adminUser();
    Role::create(['code' => 'keuangan', 'name' => 'Keuangan', 'guard_name' => 'web']);

    $this->actingAs($admin)->get(route('admin.pengguna.role.index'))->assertOk()->assertSee('Keuangan');
    $this->actingAs($admin)->get(route('admin.pengguna.role.create'))->assertOk()->assertSee('Tambah Role');
});

it('creates, updates, and deletes a role', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('code', 'humas')
        ->set('name', 'Humas')
        ->call('save')
        ->assertRedirect(route('admin.pengguna.role.index'));

    $role = Role::where('code', 'humas')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $role->id])
        ->assertSet('name', 'Humas')
        ->set('name', 'Hubungan Masyarakat')
        ->call('save');

    expect($role->fresh()->name)->toBe('Hubungan Masyarakat');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $role->id)
        ->call('delete');

    expect(Role::find($role->id))->toBeNull();
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.pengguna.role.index'))->assertRedirect(route('login'));
});
