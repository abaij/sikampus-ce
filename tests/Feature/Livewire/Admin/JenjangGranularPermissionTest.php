<?php

use App\Livewire\Admin\Jenjang\Index;
use App\Models\Jenjang;
use Livewire\Livewire;

beforeEach(function () {
    config(['access.granular_permissions' => true]);
});

it('lets a fresh akademik admin view jenjang but not reach create/edit routes', function () {
    $admin = adminUser('admin_akademik');
    $jenjang = Jenjang::factory()->create();

    $this->actingAs($admin)->get(route('admin.jenjang.index'))->assertOk();

    $this->actingAs($admin)->get(route('admin.jenjang.create'))->assertStatus(403);
    $this->actingAs($admin)->get(route('admin.jenjang.edit', $jenjang->id))->assertStatus(403);
});

it('hides the tambah/ubah/hapus buttons from a view-only akademik admin', function () {
    $admin = adminUser('admin_akademik');
    $jenjang = Jenjang::factory()->create();

    $this->actingAs($admin)->get(route('admin.jenjang.index'))
        ->assertOk()
        ->assertDontSee('Tambah Jenjang')
        ->assertDontSee(route('admin.jenjang.edit', $jenjang->id));
});

it('blocks a view-only akademik admin from deleting via the livewire method directly', function () {
    $admin = adminUser('admin_akademik');
    $jenjang = Jenjang::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $jenjang->id)
        ->assertStatus(403);

    expect(Jenjang::find($jenjang->id))->not->toBeNull();
});

it('lets an akademik admin create, edit, and delete jenjang once granted the specific permissions', function () {
    $admin = adminUser('admin_akademik');
    $admin->givePermissionTo(['create jenjang', 'update jenjang', 'delete jenjang']);
    $jenjang = Jenjang::factory()->create();

    $this->actingAs($admin)->get(route('admin.jenjang.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.jenjang.edit', $jenjang->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $jenjang->id)
        ->call('delete');

    expect(Jenjang::find($jenjang->id))->toBeNull();
});

it('still lets superadmin do everything on jenjang regardless of granular mode', function () {
    $admin = adminUser();
    $jenjang = Jenjang::factory()->create();

    $this->actingAs($admin)->get(route('admin.jenjang.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.jenjang.edit', $jenjang->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $jenjang->id)
        ->call('delete');

    expect(Jenjang::find($jenjang->id))->toBeNull();
});

it('still blocks keuangan-only admins from jenjang entirely in granular mode', function () {
    $admin = adminUser('admin_keuangan');

    $this->actingAs($admin)->get(route('admin.jenjang.index'))->assertStatus(403);
});
