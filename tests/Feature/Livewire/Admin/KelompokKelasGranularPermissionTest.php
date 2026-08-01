<?php

use App\Livewire\Admin\KelompokKelas\Index;
use App\Models\KelompokKelas;
use Livewire\Livewire;

beforeEach(function () {
    config(['access.granular_permissions' => true]);
});

it('lets a fresh akademik admin view grup mahasiswa but not reach create/edit routes', function () {
    $admin = adminUser('admin_akademik');
    $kelompokKelas = KelompokKelas::factory()->create();

    $this->actingAs($admin)->get(route('admin.administrasi.kelas-mahasiswa'))->assertOk();

    $this->actingAs($admin)->get(route('admin.administrasi.kelas-mahasiswa.create'))->assertStatus(403);
    $this->actingAs($admin)->get(route('admin.administrasi.kelas-mahasiswa.edit', $kelompokKelas->id))->assertStatus(403);
});

it('hides the tambah/ubah/hapus buttons from a view-only akademik admin', function () {
    $admin = adminUser('admin_akademik');
    $kelompokKelas = KelompokKelas::factory()->create();

    $this->actingAs($admin)->get(route('admin.administrasi.kelas-mahasiswa'))
        ->assertOk()
        ->assertDontSee('Tambah Kelas Mahasiswa')
        ->assertDontSee(route('admin.administrasi.kelas-mahasiswa.edit', $kelompokKelas->id));
});

it('blocks a view-only akademik admin from deleting via the livewire method directly', function () {
    $admin = adminUser('admin_akademik');
    $kelompokKelas = KelompokKelas::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $kelompokKelas->id)
        ->assertStatus(403);

    expect(KelompokKelas::find($kelompokKelas->id))->not->toBeNull();
});

it('lets an akademik admin create, edit, and delete grup mahasiswa once granted the specific permissions', function () {
    $admin = adminUser('admin_akademik');
    $admin->givePermissionTo(['create grup mahasiswa', 'update grup mahasiswa', 'delete grup mahasiswa']);
    $kelompokKelas = KelompokKelas::factory()->create();

    $this->actingAs($admin)->get(route('admin.administrasi.kelas-mahasiswa.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.administrasi.kelas-mahasiswa.edit', $kelompokKelas->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $kelompokKelas->id)
        ->call('delete');

    expect(KelompokKelas::find($kelompokKelas->id))->toBeNull();
});

it('still lets superadmin do everything on grup mahasiswa regardless of granular mode', function () {
    $admin = adminUser();
    $kelompokKelas = KelompokKelas::factory()->create();

    $this->actingAs($admin)->get(route('admin.administrasi.kelas-mahasiswa.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.administrasi.kelas-mahasiswa.edit', $kelompokKelas->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $kelompokKelas->id)
        ->call('delete');

    expect(KelompokKelas::find($kelompokKelas->id))->toBeNull();
});

it('still blocks keuangan-only admins from grup mahasiswa entirely in granular mode', function () {
    $admin = adminUser('admin_keuangan');

    $this->actingAs($admin)->get(route('admin.administrasi.kelas-mahasiswa'))->assertStatus(403);
});
