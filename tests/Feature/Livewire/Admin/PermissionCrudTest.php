<?php

use App\Livewire\Admin\Permission\Form;
use App\Livewire\Admin\Permission\Index;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

it('renders index and create form as full pages', function () {
    $admin = adminUser();
    Permission::create(['name' => 'manage semester', 'guard_name' => 'web']);

    $this->actingAs($admin)->get(route('admin.pengguna.permission.index'))->assertOk()->assertSee('manage semester');
    $this->actingAs($admin)->get(route('admin.pengguna.permission.create'))->assertOk()->assertSee('Tambah Permission');
});

it('creates, updates, and deletes a permission', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('name', 'manage ruangan')
        ->call('save')
        ->assertRedirect(route('admin.pengguna.permission.index'));

    $permission = Permission::where('name', 'manage ruangan')->firstOrFail();
    expect($permission->guard_name)->toBe('web');

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $permission->id])
        ->assertSet('name', 'manage ruangan')
        ->set('name', 'write ruangan')
        ->call('save');

    expect($permission->fresh()->name)->toBe('write ruangan');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $permission->id)
        ->call('delete');

    expect(Permission::find($permission->id))->toBeNull();
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.pengguna.permission.index'))->assertRedirect(route('login'));
});
