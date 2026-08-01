<?php

use App\Livewire\Admin\Pengumuman\Index;
use App\Models\Pengumuman;
use Livewire\Livewire;

beforeEach(function () {
    config(['access.granular_permissions' => true]);
});

it('lets a fresh akademik admin view pengumuman but not reach create/edit routes', function () {
    $admin = adminUser('admin_akademik');
    $pengumuman = Pengumuman::factory()->create();

    $this->actingAs($admin)->get(route('admin.administrasi.pengumuman'))->assertOk();

    $this->actingAs($admin)->get(route('admin.administrasi.pengumuman.create'))->assertStatus(403);
    $this->actingAs($admin)->get(route('admin.administrasi.pengumuman.edit', $pengumuman->id))->assertStatus(403);
});

it('hides the tambah/ubah/hapus buttons from a view-only akademik admin', function () {
    $admin = adminUser('admin_akademik');
    $pengumuman = Pengumuman::factory()->create();

    $this->actingAs($admin)->get(route('admin.administrasi.pengumuman'))
        ->assertOk()
        ->assertDontSee('Tambah Pengumuman')
        ->assertDontSee(route('admin.administrasi.pengumuman.edit', $pengumuman->id));
});

it('blocks a view-only akademik admin from deleting via the livewire method directly', function () {
    $admin = adminUser('admin_akademik');
    $pengumuman = Pengumuman::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $pengumuman->id)
        ->assertStatus(403);

    expect(Pengumuman::find($pengumuman->id))->not->toBeNull();
});

it('lets an akademik admin create, edit, and delete pengumuman once granted the specific permissions', function () {
    $admin = adminUser('admin_akademik');
    $admin->givePermissionTo(['create pengumuman', 'update pengumuman', 'delete pengumuman']);
    $pengumuman = Pengumuman::factory()->create();

    $this->actingAs($admin)->get(route('admin.administrasi.pengumuman.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.administrasi.pengumuman.edit', $pengumuman->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $pengumuman->id)
        ->call('delete');

    expect(Pengumuman::find($pengumuman->id))->toBeNull();
});

it('still lets superadmin do everything on pengumuman regardless of granular mode', function () {
    $admin = adminUser();
    $pengumuman = Pengumuman::factory()->create();

    $this->actingAs($admin)->get(route('admin.administrasi.pengumuman.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.administrasi.pengumuman.edit', $pengumuman->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $pengumuman->id)
        ->call('delete');

    expect(Pengumuman::find($pengumuman->id))->toBeNull();
});

it('still blocks keuangan-only admins from pengumuman entirely in granular mode', function () {
    $admin = adminUser('admin_keuangan');

    $this->actingAs($admin)->get(route('admin.administrasi.pengumuman'))->assertStatus(403);
});
