<?php

use App\Livewire\Admin\JenisDaftar\Index;
use App\Models\JenisDaftar;
use Livewire\Livewire;

beforeEach(function () {
    config(['access.granular_permissions' => true]);
});

it('lets a fresh akademik admin view jenis daftar but not reach create/edit routes', function () {
    $admin = adminUser('admin_akademik');
    $jenisDaftar = JenisDaftar::factory()->create();

    $this->actingAs($admin)->get(route('admin.jenis-daftar.index'))->assertOk();

    $this->actingAs($admin)->get(route('admin.jenis-daftar.create'))->assertStatus(403);
    $this->actingAs($admin)->get(route('admin.jenis-daftar.edit', $jenisDaftar->id))->assertStatus(403);
});

it('hides the tambah/ubah/hapus buttons from a view-only akademik admin', function () {
    $admin = adminUser('admin_akademik');
    $jenisDaftar = JenisDaftar::factory()->create();

    $this->actingAs($admin)->get(route('admin.jenis-daftar.index'))
        ->assertOk()
        ->assertDontSee('Tambah Jenis Daftar')
        ->assertDontSee(route('admin.jenis-daftar.edit', $jenisDaftar->id));
});

it('blocks a view-only akademik admin from deleting via the livewire method directly', function () {
    $admin = adminUser('admin_akademik');
    $jenisDaftar = JenisDaftar::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $jenisDaftar->id)
        ->assertStatus(403);

    expect(JenisDaftar::find($jenisDaftar->id))->not->toBeNull();
});

it('lets an akademik admin create, edit, and delete jenis daftar once granted the specific permissions', function () {
    $admin = adminUser('admin_akademik');
    $admin->givePermissionTo(['create jenis pendaftaran', 'update jenis pendaftaran', 'delete jenis pendaftaran']);
    $jenisDaftar = JenisDaftar::factory()->create();

    $this->actingAs($admin)->get(route('admin.jenis-daftar.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.jenis-daftar.edit', $jenisDaftar->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $jenisDaftar->id)
        ->call('delete');

    expect(JenisDaftar::find($jenisDaftar->id))->toBeNull();
});

it('still lets superadmin do everything on jenis daftar regardless of granular mode', function () {
    $admin = adminUser();
    $jenisDaftar = JenisDaftar::factory()->create();

    $this->actingAs($admin)->get(route('admin.jenis-daftar.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.jenis-daftar.edit', $jenisDaftar->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $jenisDaftar->id)
        ->call('delete');

    expect(JenisDaftar::find($jenisDaftar->id))->toBeNull();
});

it('still blocks keuangan-only admins from jenis daftar entirely in granular mode', function () {
    $admin = adminUser('admin_keuangan');

    $this->actingAs($admin)->get(route('admin.jenis-daftar.index'))->assertStatus(403);
});
