<?php

use App\Livewire\Admin\KeringananBiaya\Form;
use App\Livewire\Admin\KeringananBiaya\Index;
use App\Models\JenisKeringananBiaya;
use App\Models\KeringananBiaya;
use App\Models\Mahasiswa;
use App\Models\Semester;
use Livewire\Livewire;

beforeEach(function () {
    config(['access.granular_permissions' => true]);
});

it('lets a fresh keuangan admin view keringanan biaya but not reach create/edit routes', function () {
    $admin = adminUser('admin_keuangan');
    $keringananBiaya = KeringananBiaya::factory()->create();

    $this->actingAs($admin)->get(route('admin.keuangan.keringanan-biaya'))->assertOk();

    $this->actingAs($admin)->get(route('admin.keuangan.keringanan-biaya.create'))->assertStatus(403);
    $this->actingAs($admin)->get(route('admin.keuangan.keringanan-biaya.edit', $keringananBiaya->id))->assertStatus(403);
});

it('hides the tambah/ubah/hapus buttons from a view-only keuangan admin', function () {
    $admin = adminUser('admin_keuangan');
    $keringananBiaya = KeringananBiaya::factory()->create();

    $this->actingAs($admin)->get(route('admin.keuangan.keringanan-biaya'))
        ->assertOk()
        ->assertDontSee('Tambah Pengajuan')
        ->assertDontSee(route('admin.keuangan.keringanan-biaya.edit', $keringananBiaya->id));
});

it('blocks a view-only keuangan admin from deleting via the livewire method directly', function () {
    $admin = adminUser('admin_keuangan');
    $keringananBiaya = KeringananBiaya::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $keringananBiaya->id)
        ->assertStatus(403);

    expect(KeringananBiaya::find($keringananBiaya->id))->not->toBeNull();
});

it('lets a keuangan admin create, edit, and delete keringanan biaya once granted the specific permissions', function () {
    $admin = adminUser('admin_keuangan');
    $admin->givePermissionTo(['create keringanan biaya', 'update keringanan biaya', 'delete keringanan biaya']);

    $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Dwi Kartika', 'nim' => '2023000700']);
    $jenis = JenisKeringananBiaya::factory()->create();
    $semester = Semester::factory()->create();

    $this->actingAs($admin)->get(route('admin.keuangan.keringanan-biaya.create'))
        ->assertOk()
        ->assertSee('Tambah Keringanan Biaya');

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('mahasiswaSearch', 'Dwi Kartika')
        ->call('selectMahasiswa', $mahasiswa->id, 'Dwi Kartika (2023000700)')
        ->set('id_jenis_keringanan_biaya', $jenis->id)
        ->set('id_semester', $semester->id)
        ->set('nominal', '250000')
        ->set('status', 'pending')
        ->call('save')
        ->assertRedirect(route('admin.keuangan.keringanan-biaya'));

    $keringananBiaya = KeringananBiaya::where('id_mahasiswa', $mahasiswa->id)->firstOrFail();

    $this->actingAs($admin)->get(route('admin.keuangan.keringanan-biaya.edit', $keringananBiaya->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $keringananBiaya->id)
        ->call('delete');

    expect(KeringananBiaya::find($keringananBiaya->id))->toBeNull();
});

it('still lets superadmin do everything on keringanan biaya regardless of granular mode', function () {
    $admin = adminUser();
    $keringananBiaya = KeringananBiaya::factory()->create();

    $this->actingAs($admin)->get(route('admin.keuangan.keringanan-biaya.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.keuangan.keringanan-biaya.edit', $keringananBiaya->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $keringananBiaya->id)
        ->call('delete');

    expect(KeringananBiaya::find($keringananBiaya->id))->toBeNull();
});

it('still blocks akademik-only admins from keringanan biaya entirely in granular mode', function () {
    $admin = adminUser('admin_akademik');

    $this->actingAs($admin)->get(route('admin.keuangan.keringanan-biaya'))->assertStatus(403);
});
