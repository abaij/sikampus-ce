<?php

use App\Livewire\Admin\JalurMasuk\Index;
use App\Models\JalurMasuk;
use Livewire\Livewire;

beforeEach(function () {
    config(['access.granular_permissions' => true]);
});

it('lets a fresh akademik admin view jalur masuk but not reach create/edit routes', function () {
    $admin = adminUser('admin_akademik');
    $jalurMasuk = JalurMasuk::factory()->create();

    $this->actingAs($admin)->get(route('admin.jalur-masuk.index'))->assertOk();

    $this->actingAs($admin)->get(route('admin.jalur-masuk.create'))->assertStatus(403);
    $this->actingAs($admin)->get(route('admin.jalur-masuk.edit', $jalurMasuk->id))->assertStatus(403);
});

it('hides the tambah/ubah/hapus buttons from a view-only akademik admin', function () {
    $admin = adminUser('admin_akademik');
    $jalurMasuk = JalurMasuk::factory()->create();

    $this->actingAs($admin)->get(route('admin.jalur-masuk.index'))
        ->assertOk()
        ->assertDontSee('Tambah Jalur Masuk')
        ->assertDontSee(route('admin.jalur-masuk.edit', $jalurMasuk->id));
});

it('blocks a view-only akademik admin from deleting via the livewire method directly', function () {
    $admin = adminUser('admin_akademik');
    $jalurMasuk = JalurMasuk::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $jalurMasuk->id)
        ->assertStatus(403);

    expect(JalurMasuk::find($jalurMasuk->id))->not->toBeNull();
});

it('lets an akademik admin create, edit, and delete jalur masuk once granted the specific permissions', function () {
    $admin = adminUser('admin_akademik');
    $admin->givePermissionTo(['create jalur masuk', 'update jalur masuk', 'delete jalur masuk']);
    $jalurMasuk = JalurMasuk::factory()->create();

    $this->actingAs($admin)->get(route('admin.jalur-masuk.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.jalur-masuk.edit', $jalurMasuk->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $jalurMasuk->id)
        ->call('delete');

    expect(JalurMasuk::find($jalurMasuk->id))->toBeNull();
});

it('still lets superadmin do everything on jalur masuk regardless of granular mode', function () {
    $admin = adminUser();
    $jalurMasuk = JalurMasuk::factory()->create();

    $this->actingAs($admin)->get(route('admin.jalur-masuk.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.jalur-masuk.edit', $jalurMasuk->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $jalurMasuk->id)
        ->call('delete');

    expect(JalurMasuk::find($jalurMasuk->id))->toBeNull();
});

it('still blocks keuangan-only admins from jalur masuk entirely in granular mode', function () {
    $admin = adminUser('admin_keuangan');

    $this->actingAs($admin)->get(route('admin.jalur-masuk.index'))->assertStatus(403);
});
