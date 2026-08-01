<?php

use App\Livewire\Admin\StrukturBiaya\Form;
use App\Livewire\Admin\StrukturBiaya\Index;
use App\Models\Semester;
use App\Models\StrukturBiaya;
use Livewire\Livewire;

beforeEach(function () {
    config(['access.granular_permissions' => true]);
});

it('lets a fresh keuangan admin view struktur biaya but not reach create/edit routes', function () {
    $admin = adminUser('admin_keuangan');
    $strukturBiaya = StrukturBiaya::factory()->create();

    $this->actingAs($admin)->get(route('admin.keuangan.struktur-biaya'))->assertOk();

    $this->actingAs($admin)->get(route('admin.keuangan.struktur-biaya.create'))->assertStatus(403);
    $this->actingAs($admin)->get(route('admin.keuangan.struktur-biaya.edit', $strukturBiaya->id))->assertStatus(403);
});

it('hides the tambah/ubah/hapus buttons from a view-only keuangan admin', function () {
    $admin = adminUser('admin_keuangan');
    $strukturBiaya = StrukturBiaya::factory()->create();

    $this->actingAs($admin)->get(route('admin.keuangan.struktur-biaya'))
        ->assertOk()
        ->assertDontSee('Tambah Struktur Biaya')
        ->assertDontSee(route('admin.keuangan.struktur-biaya.edit', $strukturBiaya->id));
});

it('blocks a view-only keuangan admin from deleting via the livewire method directly', function () {
    $admin = adminUser('admin_keuangan');
    $strukturBiaya = StrukturBiaya::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $strukturBiaya->id)
        ->assertStatus(403);

    expect(StrukturBiaya::find($strukturBiaya->id))->not->toBeNull();
});

it('lets a keuangan admin create, edit, and delete struktur biaya once granted the specific permissions', function () {
    $admin = adminUser('admin_keuangan');
    $admin->givePermissionTo(['create struktur biaya', 'update struktur biaya', 'delete struktur biaya']);

    $angkatan = Semester::factory()->create();
    $periode = Semester::factory()->create();

    $this->actingAs($admin)->get(route('admin.keuangan.struktur-biaya.create'))
        ->assertOk()
        ->assertSee('Tambah Struktur Biaya');

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('id_angkatan', $angkatan->id)
        ->set('id_periode', $periode->id)
        ->set('nominal', '5000000')
        ->call('save')
        ->assertRedirect(route('admin.keuangan.struktur-biaya'));

    $strukturBiaya = StrukturBiaya::where('id_angkatan', $angkatan->id)->where('id_periode', $periode->id)->firstOrFail();

    $this->actingAs($admin)->get(route('admin.keuangan.struktur-biaya.edit', $strukturBiaya->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $strukturBiaya->id)
        ->call('delete');

    expect(StrukturBiaya::find($strukturBiaya->id))->toBeNull();
});

it('still lets superadmin do everything on struktur biaya regardless of granular mode', function () {
    $admin = adminUser();
    $strukturBiaya = StrukturBiaya::factory()->create();

    $this->actingAs($admin)->get(route('admin.keuangan.struktur-biaya.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.keuangan.struktur-biaya.edit', $strukturBiaya->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $strukturBiaya->id)
        ->call('delete');

    expect(StrukturBiaya::find($strukturBiaya->id))->toBeNull();
});

it('still blocks akademik-only admins from struktur biaya entirely in granular mode', function () {
    $admin = adminUser('admin_akademik');

    $this->actingAs($admin)->get(route('admin.keuangan.struktur-biaya'))->assertStatus(403);
});
