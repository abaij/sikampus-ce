<?php

use App\Livewire\Admin\KomponenBiaya\Form;
use App\Livewire\Admin\KomponenBiaya\Index;
use App\Models\KomponenBiaya;
use Livewire\Livewire;

beforeEach(function () {
    config(['access.granular_permissions' => true]);
});

it('lets a fresh keuangan admin view komponen biaya but not reach create/edit routes', function () {
    $admin = adminUser('admin_keuangan');
    $komponenBiaya = KomponenBiaya::factory()->create();

    $this->actingAs($admin)->get(route('admin.keuangan.komponen-biaya'))->assertOk();

    $this->actingAs($admin)->get(route('admin.keuangan.komponen-biaya.create'))->assertStatus(403);
    $this->actingAs($admin)->get(route('admin.keuangan.komponen-biaya.edit', $komponenBiaya->id))->assertStatus(403);
});

it('hides the tambah/ubah/hapus buttons from a view-only keuangan admin', function () {
    $admin = adminUser('admin_keuangan');
    $komponenBiaya = KomponenBiaya::factory()->create();

    $this->actingAs($admin)->get(route('admin.keuangan.komponen-biaya'))
        ->assertOk()
        ->assertDontSee('Tambah Komponen Biaya')
        ->assertDontSee(route('admin.keuangan.komponen-biaya.edit', $komponenBiaya->id));
});

it('blocks a view-only keuangan admin from deleting via the livewire method directly', function () {
    $admin = adminUser('admin_keuangan');
    $komponenBiaya = KomponenBiaya::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $komponenBiaya->id)
        ->assertStatus(403);

    expect(KomponenBiaya::find($komponenBiaya->id))->not->toBeNull();
});

it('lets a keuangan admin create, edit, and delete komponen biaya once granted the specific permissions', function () {
    $admin = adminUser('admin_keuangan');
    $admin->givePermissionTo(['create komponen biaya', 'update komponen biaya', 'delete komponen biaya']);

    $this->actingAs($admin)->get(route('admin.keuangan.komponen-biaya.create'))
        ->assertOk()
        ->assertSee('Tambah Komponen Biaya');

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('kode', 'UKT')
        ->set('nama', 'Uang Kuliah Tunggal')
        ->set('is_per_semester', true)
        ->set('is_akademik', true)
        ->call('save')
        ->assertRedirect(route('admin.keuangan.komponen-biaya'));

    $komponenBiaya = KomponenBiaya::where('kode', 'UKT')->firstOrFail();

    $this->actingAs($admin)->get(route('admin.keuangan.komponen-biaya.edit', $komponenBiaya->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $komponenBiaya->id)
        ->call('delete');

    expect(KomponenBiaya::find($komponenBiaya->id))->toBeNull();
});

it('still lets superadmin do everything on komponen biaya regardless of granular mode', function () {
    $admin = adminUser();
    $komponenBiaya = KomponenBiaya::factory()->create();

    $this->actingAs($admin)->get(route('admin.keuangan.komponen-biaya.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.keuangan.komponen-biaya.edit', $komponenBiaya->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $komponenBiaya->id)
        ->call('delete');

    expect(KomponenBiaya::find($komponenBiaya->id))->toBeNull();
});

it('still blocks akademik-only admins from komponen biaya entirely in granular mode', function () {
    $admin = adminUser('admin_akademik');

    $this->actingAs($admin)->get(route('admin.keuangan.komponen-biaya'))->assertStatus(403);
});
