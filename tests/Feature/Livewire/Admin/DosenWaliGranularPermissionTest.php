<?php

use App\Livewire\Admin\DosenWali\Index;
use App\Livewire\Admin\DosenWali\Show;
use App\Models\Dosen;
use App\Models\DosenWali;
use App\Models\Mahasiswa;
use Livewire\Livewire;

beforeEach(function () {
    config(['access.granular_permissions' => true]);
});

it('lets a fresh akademik admin view dosen wali but not manage kuota or bimbingan', function () {
    $admin = adminUser('admin_akademik');
    $dosen = Dosen::factory()->create();

    $this->actingAs($admin)->get(route('admin.administrasi.dosen-wali'))->assertOk();
    $this->actingAs($admin)->get(route('admin.administrasi.dosen-wali.show', $dosen->id))->assertOk();
});

it('hides the tetapkan kuota, import, and tambah bimbingan buttons from a view-only akademik admin', function () {
    $admin = adminUser('admin_akademik');
    $dosen = Dosen::factory()->create();

    $this->actingAs($admin)->get(route('admin.administrasi.dosen-wali'))
        ->assertOk()
        ->assertDontSee('Tetapkan Kuota');

    $this->actingAs($admin)->get(route('admin.administrasi.dosen-wali.show', $dosen->id))
        ->assertOk()
        ->assertDontSee('Tambah Mahasiswa Bimbingan');
});

it('blocks a view-only akademik admin from managing kuota or bimbingan via the livewire methods directly', function () {
    $admin = adminUser('admin_akademik');
    $dosen = Dosen::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create();
    $dosenWali = DosenWali::factory()->create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $mahasiswa->id]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('openKuotaModal')
        ->assertStatus(403);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $dosen->id])
        ->call('openModal')
        ->assertStatus(403);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $dosen->id])
        ->call('confirmDelete', $dosenWali->id)
        ->assertStatus(403);

    expect(DosenWali::find($dosenWali->id))->not->toBeNull();
});

it('lets an akademik admin manage kuota and bimbingan once granted the specific permissions', function () {
    $admin = adminUser('admin_akademik');
    $admin->givePermissionTo(['update dosen wali', 'delete dosen wali']);

    $dosen = Dosen::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('openKuotaModal')
        ->set('kuotaInput', '5')
        ->call('applyKuota')
        ->assertHasNoErrors();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $dosen->id])
        ->call('selectMahasiswa', $mahasiswa->id, $mahasiswa->nim.' - '.$mahasiswa->nama)
        ->call('save')
        ->assertHasNoErrors();

    $dosenWali = DosenWali::where('id_dosen', $dosen->id)->where('id_mahasiswa', $mahasiswa->id)->firstOrFail();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $dosen->id])
        ->call('confirmDelete', $dosenWali->id)
        ->call('delete');

    expect(DosenWali::find($dosenWali->id))->toBeNull();
});

it('still lets superadmin manage kuota and bimbingan regardless of granular mode', function () {
    $admin = adminUser();
    $dosen = Dosen::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $dosen->id])
        ->call('selectMahasiswa', $mahasiswa->id, $mahasiswa->nim.' - '.$mahasiswa->nama)
        ->call('save')
        ->assertHasNoErrors();

    expect(DosenWali::where('id_dosen', $dosen->id)->where('id_mahasiswa', $mahasiswa->id)->exists())->toBeTrue();
});

it('still blocks keuangan-only admins from dosen wali entirely in granular mode', function () {
    $admin = adminUser('admin_keuangan');

    $this->actingAs($admin)->get(route('admin.administrasi.dosen-wali'))->assertStatus(403);
});
