<?php

use App\Livewire\Admin\KategoriBiaya\Form;
use App\Livewire\Admin\KategoriBiaya\Index;
use App\Livewire\Admin\KategoriBiaya\Show;
use App\Models\KategoriBiaya;
use App\Models\Mahasiswa;
use App\Models\Semester;
use Livewire\Livewire;

beforeEach(function () {
    config(['access.granular_permissions' => true]);
});

it('lets a fresh keuangan admin view kategori biaya but not reach create/edit routes', function () {
    $admin = adminUser('admin_keuangan');
    $kategoriBiaya = KategoriBiaya::factory()->create();

    $this->actingAs($admin)->get(route('admin.keuangan.kategori-biaya'))->assertOk();
    $this->actingAs($admin)->get(route('admin.keuangan.kategori-biaya.show', $kategoriBiaya->id))->assertOk();

    $this->actingAs($admin)->get(route('admin.keuangan.kategori-biaya.create'))->assertStatus(403);
    $this->actingAs($admin)->get(route('admin.keuangan.kategori-biaya.edit', $kategoriBiaya->id))->assertStatus(403);
});

it('hides the tambah/ubah/hapus/tambah mahasiswa buttons from a view-only keuangan admin', function () {
    $admin = adminUser('admin_keuangan');
    $kategoriBiaya = KategoriBiaya::factory()->create();

    $this->actingAs($admin)->get(route('admin.keuangan.kategori-biaya'))
        ->assertOk()
        ->assertDontSee('Tambah Kategori Biaya')
        ->assertDontSee(route('admin.keuangan.kategori-biaya.edit', $kategoriBiaya->id));

    $this->actingAs($admin)->get(route('admin.keuangan.kategori-biaya.show', $kategoriBiaya->id))
        ->assertOk()
        ->assertDontSee('Tambah Mahasiswa');
});

it('blocks a view-only keuangan admin from deleting or assigning mahasiswa via the livewire methods directly', function () {
    $admin = adminUser('admin_keuangan');
    $kategoriBiaya = KategoriBiaya::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create();
    $semester = Semester::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $kategoriBiaya->id)
        ->assertStatus(403);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $kategoriBiaya->id])
        ->call('openModal')
        ->assertStatus(403);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $kategoriBiaya->id])
        ->call('selectMahasiswa', $mahasiswa->id, 'label')
        ->set('selectedSemesterId', (string) $semester->id)
        ->call('save')
        ->assertStatus(403);

    expect(KategoriBiaya::find($kategoriBiaya->id))->not->toBeNull();
});

it('lets a keuangan admin create, edit, assign mahasiswa, and delete kategori biaya once granted the specific permissions', function () {
    $admin = adminUser('admin_keuangan');
    $admin->givePermissionTo(['create kategori biaya', 'update kategori biaya', 'delete kategori biaya']);

    $this->actingAs($admin)->get(route('admin.keuangan.kategori-biaya.create'))
        ->assertOk()
        ->assertSee('Tambah Kategori Biaya');

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Reguler')
        ->set('kode', 'REG')
        ->call('save')
        ->assertRedirect(route('admin.keuangan.kategori-biaya'));

    $kategoriBiaya = KategoriBiaya::where('nama', 'Reguler')->firstOrFail();

    $this->actingAs($admin)->get(route('admin.keuangan.kategori-biaya.edit', $kategoriBiaya->id))->assertOk();

    $mahasiswa = Mahasiswa::factory()->create();
    $semester = Semester::factory()->create(['is_active' => true]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $kategoriBiaya->id])
        ->call('selectMahasiswa', $mahasiswa->id, 'label')
        ->set('selectedSemesterId', (string) $semester->id)
        ->call('save')
        ->assertHasNoErrors();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $kategoriBiaya->id)
        ->call('delete');

    expect(KategoriBiaya::find($kategoriBiaya->id))->toBeNull();
});

it('still lets superadmin do everything on kategori biaya regardless of granular mode', function () {
    $admin = adminUser();
    $kategoriBiaya = KategoriBiaya::factory()->create();

    $this->actingAs($admin)->get(route('admin.keuangan.kategori-biaya.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.keuangan.kategori-biaya.edit', $kategoriBiaya->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $kategoriBiaya->id)
        ->call('delete');

    expect(KategoriBiaya::find($kategoriBiaya->id))->toBeNull();
});

it('still blocks akademik-only admins from kategori biaya entirely in granular mode', function () {
    $admin = adminUser('admin_akademik');

    $this->actingAs($admin)->get(route('admin.keuangan.kategori-biaya'))->assertStatus(403);
});
