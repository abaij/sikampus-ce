<?php

use App\Livewire\Admin\Dosen\Form;
use App\Livewire\Admin\Dosen\Index;
use App\Livewire\Admin\Dosen\Show;
use App\Models\Dosen;
use App\Models\DosenWali;
use App\Models\Mahasiswa;
use Livewire\Livewire;

beforeEach(function () {
    config(['access.granular_permissions' => true]);
});

it('lets a fresh akademik admin view dosen but not reach create/edit routes', function () {
    $admin = adminUser('admin_akademik');
    $dosen = Dosen::factory()->create();

    $this->actingAs($admin)->get(route('admin.administrasi.dosen'))->assertOk();
    $this->actingAs($admin)->get(route('admin.administrasi.dosen.show', $dosen->id))->assertOk();

    $this->actingAs($admin)->get(route('admin.administrasi.dosen.create'))->assertStatus(403);
    $this->actingAs($admin)->get(route('admin.administrasi.dosen.edit', $dosen->id))->assertStatus(403);
});

it('hides the tambah/ubah/hapus dosen and bimbingan buttons from a view-only akademik admin', function () {
    $admin = adminUser('admin_akademik');
    $dosen = Dosen::factory()->create();

    $this->actingAs($admin)->get(route('admin.administrasi.dosen'))
        ->assertOk()
        ->assertDontSee('Tambah Dosen')
        ->assertDontSee(route('admin.administrasi.dosen.edit', $dosen->id));

    $this->actingAs($admin)->get(route('admin.administrasi.dosen.show', $dosen->id))
        ->assertOk()
        ->assertDontSee('Hapus Dosen')
        ->assertDontSee('Tambah Mahasiswa');
});

it('blocks a view-only akademik admin from deleting dosen or managing bimbingan via the livewire methods directly', function () {
    $admin = adminUser('admin_akademik');
    $dosen = Dosen::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $dosen->id)
        ->assertStatus(403);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $dosen->id])
        ->call('confirmDeleteDosen')
        ->assertStatus(403);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $dosen->id])
        ->call('openModal')
        ->assertStatus(403);

    expect(Dosen::find($dosen->id))->not->toBeNull();
});

it('lets an akademik admin create/edit/delete dosen and manage bimbingan once granted the specific permissions', function () {
    $admin = adminUser('admin_akademik');
    $admin->givePermissionTo(['create dosen', 'update dosen', 'delete dosen', 'update dosen wali', 'delete dosen wali']);

    $this->actingAs($admin)->get(route('admin.administrasi.dosen.create'))
        ->assertOk()
        ->assertSee('Tambah Dosen');

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Dosen Baru')
        ->set('email', 'dosen.baru@example.test')
        ->call('save')
        ->assertRedirect(route('admin.administrasi.dosen'));

    $dosen = Dosen::where('email', 'dosen.baru@example.test')->firstOrFail();

    $this->actingAs($admin)->get(route('admin.administrasi.dosen.edit', $dosen->id))->assertOk();

    $mahasiswa = Mahasiswa::factory()->create();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $dosen->id])
        ->call('selectMahasiswa', $mahasiswa->id, $mahasiswa->nim.' - '.$mahasiswa->nama)
        ->call('saveBimbingan')
        ->assertHasNoErrors();

    $dosenWali = DosenWali::where('id_dosen', $dosen->id)->where('id_mahasiswa', $mahasiswa->id)->firstOrFail();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $dosen->id])
        ->call('confirmDeleteBimbingan', $dosenWali->id)
        ->call('deleteBimbingan');

    expect(DosenWali::find($dosenWali->id))->toBeNull();

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $dosen->id])
        ->call('confirmDeleteDosen')
        ->call('deleteDosen');

    expect(Dosen::find($dosen->id))->toBeNull();
});

it('still lets superadmin do everything on dosen regardless of granular mode', function () {
    $admin = adminUser();
    $dosen = Dosen::factory()->create();

    $this->actingAs($admin)->get(route('admin.administrasi.dosen.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.administrasi.dosen.edit', $dosen->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $dosen->id)
        ->call('delete');

    expect(Dosen::find($dosen->id))->toBeNull();
});

it('still blocks keuangan-only admins from dosen entirely in granular mode', function () {
    $admin = adminUser('admin_keuangan');

    $this->actingAs($admin)->get(route('admin.administrasi.dosen'))->assertStatus(403);
});
