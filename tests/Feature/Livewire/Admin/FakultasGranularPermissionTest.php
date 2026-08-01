<?php

use App\Livewire\Admin\Fakultas\Index;
use App\Models\Fakultas;
use Livewire\Livewire;

beforeEach(function () {
    config(['access.granular_permissions' => true]);
});

it('lets a fresh akademik admin view fakultas but not reach create/edit routes', function () {
    $admin = adminUser('admin_akademik');
    $fakultas = Fakultas::factory()->create();

    $this->actingAs($admin)->get(route('admin.fakultas.index'))->assertOk();

    $this->actingAs($admin)->get(route('admin.fakultas.create'))->assertStatus(403);
    $this->actingAs($admin)->get(route('admin.fakultas.edit', $fakultas->id))->assertStatus(403);
});

it('hides the tambah/ubah/hapus buttons from a view-only akademik admin', function () {
    $admin = adminUser('admin_akademik');
    $fakultas = Fakultas::factory()->create();

    $this->actingAs($admin)->get(route('admin.fakultas.index'))
        ->assertOk()
        ->assertDontSee('Tambah Fakultas')
        ->assertDontSee(route('admin.fakultas.edit', $fakultas->id));
});

it('blocks a view-only akademik admin from deleting via the livewire method directly', function () {
    $admin = adminUser('admin_akademik');
    $fakultas = Fakultas::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $fakultas->id)
        ->assertStatus(403);

    expect(Fakultas::find($fakultas->id))->not->toBeNull();
});

it('lets an akademik admin create, edit, and delete fakultas once granted the specific permissions', function () {
    $admin = adminUser('admin_akademik');
    $admin->givePermissionTo(['create fakultas', 'update fakultas', 'delete fakultas']);
    $fakultas = Fakultas::factory()->create();

    $this->actingAs($admin)->get(route('admin.fakultas.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.fakultas.edit', $fakultas->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $fakultas->id)
        ->call('delete');

    expect(Fakultas::find($fakultas->id))->toBeNull();
});

it('still lets superadmin do everything on fakultas regardless of granular mode', function () {
    $admin = adminUser();
    $fakultas = Fakultas::factory()->create();

    $this->actingAs($admin)->get(route('admin.fakultas.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.fakultas.edit', $fakultas->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $fakultas->id)
        ->call('delete');

    expect(Fakultas::find($fakultas->id))->toBeNull();
});

it('still blocks keuangan-only admins from fakultas entirely in granular mode', function () {
    $admin = adminUser('admin_keuangan');

    $this->actingAs($admin)->get(route('admin.fakultas.index'))->assertStatus(403);
});
