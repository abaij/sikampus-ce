<?php

use App\Livewire\Admin\JenisKeringananBiaya\Form;
use App\Livewire\Admin\JenisKeringananBiaya\Index;
use App\Models\JenisKeringananBiaya;
use Livewire\Livewire;

beforeEach(function () {
    config(['access.granular_permissions' => true]);
});

it('lets a fresh keuangan admin view jenis keringanan biaya but not reach create/edit routes', function () {
    $admin = adminUser('admin_keuangan');
    $jenis = JenisKeringananBiaya::factory()->create();

    $this->actingAs($admin)->get(route('admin.keuangan.jenis-keringanan-biaya'))->assertOk();

    $this->actingAs($admin)->get(route('admin.keuangan.jenis-keringanan-biaya.create'))->assertStatus(403);
    $this->actingAs($admin)->get(route('admin.keuangan.jenis-keringanan-biaya.edit', $jenis->id))->assertStatus(403);
});

it('hides the tambah/ubah/hapus buttons from a view-only keuangan admin', function () {
    $admin = adminUser('admin_keuangan');
    $jenis = JenisKeringananBiaya::factory()->create();

    $this->actingAs($admin)->get(route('admin.keuangan.jenis-keringanan-biaya'))
        ->assertOk()
        ->assertDontSee('Tambah Jenis')
        ->assertDontSee(route('admin.keuangan.jenis-keringanan-biaya.edit', $jenis->id));
});

it('blocks a view-only keuangan admin from deleting via the livewire method directly', function () {
    $admin = adminUser('admin_keuangan');
    $jenis = JenisKeringananBiaya::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $jenis->id)
        ->assertStatus(403);

    expect(JenisKeringananBiaya::find($jenis->id))->not->toBeNull();
});

it('lets a keuangan admin create, edit, and delete jenis keringanan biaya once granted the specific permissions', function () {
    $admin = adminUser('admin_keuangan');
    $admin->givePermissionTo(['create jenis keringanan biaya', 'update jenis keringanan biaya', 'delete jenis keringanan biaya']);

    $this->actingAs($admin)->get(route('admin.keuangan.jenis-keringanan-biaya.create'))
        ->assertOk()
        ->assertSee('Tambah Jenis');

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Keringanan Prestasi')
        ->set('is_persentase', false)
        ->set('nominal', '500000')
        ->set('is_active', true)
        ->call('save')
        ->assertRedirect(route('admin.keuangan.jenis-keringanan-biaya'));

    $jenis = JenisKeringananBiaya::where('nama', 'Keringanan Prestasi')->firstOrFail();

    $this->actingAs($admin)->get(route('admin.keuangan.jenis-keringanan-biaya.edit', $jenis->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $jenis->id)
        ->call('delete');

    expect(JenisKeringananBiaya::find($jenis->id))->toBeNull();
});

it('still lets superadmin do everything on jenis keringanan biaya regardless of granular mode', function () {
    $admin = adminUser();
    $jenis = JenisKeringananBiaya::factory()->create();

    $this->actingAs($admin)->get(route('admin.keuangan.jenis-keringanan-biaya.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.keuangan.jenis-keringanan-biaya.edit', $jenis->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $jenis->id)
        ->call('delete');

    expect(JenisKeringananBiaya::find($jenis->id))->toBeNull();
});

it('still blocks akademik-only admins from jenis keringanan biaya entirely in granular mode', function () {
    $admin = adminUser('admin_akademik');

    $this->actingAs($admin)->get(route('admin.keuangan.jenis-keringanan-biaya'))->assertStatus(403);
});
