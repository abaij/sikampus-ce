<?php

use App\Livewire\Admin\Mahasiswa\Form;
use App\Livewire\Admin\Mahasiswa\Show;
use App\Models\Mahasiswa;
use Livewire\Livewire;

beforeEach(function () {
    config(['access.granular_permissions' => true]);
});

it('lets a fresh akademik admin view mahasiswa but not reach create/edit routes', function () {
    $admin = adminUser('admin_akademik');
    $mahasiswa = Mahasiswa::factory()->create();

    $this->actingAs($admin)->get(route('admin.administrasi.mahasiswa'))->assertOk();
    $this->actingAs($admin)->get(route('admin.administrasi.mahasiswa.show', $mahasiswa->id))->assertOk();

    $this->actingAs($admin)->get(route('admin.administrasi.mahasiswa.create'))->assertStatus(403);
    $this->actingAs($admin)->get(route('admin.administrasi.mahasiswa.edit', $mahasiswa->id))->assertStatus(403);
});

it('hides the tambah/ubah/hapus mahasiswa buttons from a view-only akademik admin', function () {
    $admin = adminUser('admin_akademik');
    $mahasiswa = Mahasiswa::factory()->create();

    $this->actingAs($admin)->get(route('admin.administrasi.mahasiswa'))
        ->assertOk()
        ->assertDontSee('Tambah Mahasiswa');

    $this->actingAs($admin)->get(route('admin.administrasi.mahasiswa.show', $mahasiswa->id))
        ->assertOk()
        ->assertDontSee(route('admin.administrasi.mahasiswa.edit', $mahasiswa->id))
        ->assertDontSee('Hapus Mahasiswa');
});

it('blocks a view-only akademik admin from deleting mahasiswa via the livewire method directly', function () {
    $admin = adminUser('admin_akademik');
    $mahasiswa = Mahasiswa::factory()->create();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->call('confirmDeleteMahasiswa')
        ->assertStatus(403);

    expect(Mahasiswa::find($mahasiswa->id))->not->toBeNull();
});

it('lets an akademik admin create, edit, and delete mahasiswa once granted the specific permissions', function () {
    $admin = adminUser('admin_akademik');
    $admin->givePermissionTo(['create mahasiswa', 'update mahasiswa', 'delete mahasiswa']);

    $this->actingAs($admin)->get(route('admin.administrasi.mahasiswa.create'))
        ->assertOk()
        ->assertSee('Tambah Mahasiswa');

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Mahasiswa Baru')
        ->call('save')
        ->assertHasNoErrors();

    $mahasiswa = Mahasiswa::where('nama', 'Mahasiswa Baru')->firstOrFail();

    $this->actingAs($admin)->get(route('admin.administrasi.mahasiswa.edit', $mahasiswa->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->call('confirmDeleteMahasiswa')
        ->call('deleteMahasiswa');

    expect(Mahasiswa::find($mahasiswa->id))->toBeNull();
});

it('still lets superadmin do everything on mahasiswa regardless of granular mode', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create();

    $this->actingAs($admin)->get(route('admin.administrasi.mahasiswa.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.administrasi.mahasiswa.edit', $mahasiswa->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $mahasiswa->id])
        ->call('confirmDeleteMahasiswa')
        ->call('deleteMahasiswa');

    expect(Mahasiswa::find($mahasiswa->id))->toBeNull();
});

it('still blocks keuangan-only admins from mahasiswa entirely in granular mode', function () {
    $admin = adminUser('admin_keuangan');

    $this->actingAs($admin)->get(route('admin.administrasi.mahasiswa'))->assertStatus(403);
});
