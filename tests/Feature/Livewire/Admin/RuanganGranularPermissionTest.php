<?php

use App\Livewire\Admin\Ruangan\Index;
use App\Models\Ruangan;
use Livewire\Livewire;

beforeEach(function () {
    config(['access.granular_permissions' => true]);
});

it('lets a fresh akademik admin view ruangan but not reach create/edit routes', function () {
    $admin = adminUser('admin_akademik');
    $ruangan = Ruangan::factory()->create();

    $this->actingAs($admin)->get(route('admin.administrasi.ruangan'))->assertOk();

    $this->actingAs($admin)->get(route('admin.administrasi.ruangan.create'))->assertStatus(403);
    $this->actingAs($admin)->get(route('admin.administrasi.ruangan.edit', $ruangan->id))->assertStatus(403);
});

it('hides the tambah/ubah/hapus buttons from a view-only akademik admin', function () {
    $admin = adminUser('admin_akademik');
    $ruangan = Ruangan::factory()->create();

    $this->actingAs($admin)->get(route('admin.administrasi.ruangan'))
        ->assertOk()
        ->assertDontSee('Tambah Ruangan')
        ->assertDontSee(route('admin.administrasi.ruangan.edit', $ruangan->id));
});

it('blocks a view-only akademik admin from deleting via the livewire method directly', function () {
    $admin = adminUser('admin_akademik');
    $ruangan = Ruangan::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $ruangan->id)
        ->assertStatus(403);

    expect(Ruangan::find($ruangan->id))->not->toBeNull();
});

it('lets an akademik admin create, edit, and delete ruangan once granted the specific permissions', function () {
    $admin = adminUser('admin_akademik');
    $admin->givePermissionTo(['create ruangan', 'update ruangan', 'delete ruangan']);
    $ruangan = Ruangan::factory()->create();

    $this->actingAs($admin)->get(route('admin.administrasi.ruangan.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.administrasi.ruangan.edit', $ruangan->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $ruangan->id)
        ->call('delete');

    expect(Ruangan::find($ruangan->id))->toBeNull();
});

it('still lets superadmin do everything on ruangan regardless of granular mode', function () {
    $admin = adminUser();
    $ruangan = Ruangan::factory()->create();

    $this->actingAs($admin)->get(route('admin.administrasi.ruangan.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.administrasi.ruangan.edit', $ruangan->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $ruangan->id)
        ->call('delete');

    expect(Ruangan::find($ruangan->id))->toBeNull();
});

it('still blocks keuangan-only admins from ruangan entirely in granular mode', function () {
    $admin = adminUser('admin_keuangan');

    $this->actingAs($admin)->get(route('admin.administrasi.ruangan'))->assertStatus(403);
});
