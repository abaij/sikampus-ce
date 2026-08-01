<?php

use App\Livewire\Admin\Prodi\Index;
use App\Models\Prodi;
use Livewire\Livewire;

beforeEach(function () {
    config(['access.granular_permissions' => true]);
});

it('lets a fresh akademik admin view prodi but not reach create/edit routes', function () {
    $admin = adminUser('admin_akademik');
    $prodi = Prodi::factory()->create();

    $this->actingAs($admin)->get(route('admin.prodi.index'))->assertOk();

    $this->actingAs($admin)->get(route('admin.prodi.create'))->assertStatus(403);
    $this->actingAs($admin)->get(route('admin.prodi.edit', $prodi->id))->assertStatus(403);
});

it('hides the tambah/ubah/hapus buttons from a view-only akademik admin', function () {
    $admin = adminUser('admin_akademik');
    $prodi = Prodi::factory()->create();

    $this->actingAs($admin)->get(route('admin.prodi.index'))
        ->assertOk()
        ->assertDontSee('Tambah Prodi')
        ->assertDontSee(route('admin.prodi.edit', $prodi->id));
});

it('blocks a view-only akademik admin from deleting via the livewire method directly', function () {
    $admin = adminUser('admin_akademik');
    $prodi = Prodi::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $prodi->id)
        ->assertStatus(403);

    expect(Prodi::find($prodi->id))->not->toBeNull();
});

it('lets an akademik admin create, edit, and delete prodi once granted the specific permissions', function () {
    $admin = adminUser('admin_akademik');
    $admin->givePermissionTo(['create prodi', 'update prodi', 'delete prodi']);
    $prodi = Prodi::factory()->create();

    $this->actingAs($admin)->get(route('admin.prodi.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.prodi.edit', $prodi->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $prodi->id)
        ->call('delete');

    expect(Prodi::find($prodi->id))->toBeNull();
});

it('still lets superadmin do everything on prodi regardless of granular mode', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create();

    $this->actingAs($admin)->get(route('admin.prodi.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.prodi.edit', $prodi->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $prodi->id)
        ->call('delete');

    expect(Prodi::find($prodi->id))->toBeNull();
});

it('still blocks keuangan-only admins from prodi entirely in granular mode', function () {
    $admin = adminUser('admin_keuangan');

    $this->actingAs($admin)->get(route('admin.prodi.index'))->assertStatus(403);
});
