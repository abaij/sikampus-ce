<?php

use App\Livewire\Admin\Tagihan\Form;
use App\Livewire\Admin\Tagihan\Index;
use App\Models\KomponenBiaya;
use App\Models\Mahasiswa;
use App\Models\Semester;
use App\Models\Tagihan;
use Livewire\Livewire;

beforeEach(function () {
    config(['access.granular_permissions' => true]);
});

it('lets a fresh keuangan admin view tagihan but not reach create/edit routes', function () {
    $admin = adminUser('admin_keuangan');
    $tagihan = Tagihan::factory()->create();

    $this->actingAs($admin)->get(route('admin.keuangan.tagihan'))->assertOk();
    $this->actingAs($admin)->get(route('admin.keuangan.tagihan.show', $tagihan->id))->assertOk();

    $this->actingAs($admin)->get(route('admin.keuangan.tagihan.create'))->assertStatus(403);
    $this->actingAs($admin)->get(route('admin.keuangan.tagihan.edit', $tagihan->id))->assertStatus(403);
});

it('hides the tambah/ubah buttons from a view-only keuangan admin', function () {
    $admin = adminUser('admin_keuangan');
    $tagihan = Tagihan::factory()->create();

    $this->actingAs($admin)->get(route('admin.keuangan.tagihan'))
        ->assertOk()
        ->assertDontSee('Tambah Tagihan')
        ->assertDontSee(route('admin.keuangan.tagihan.edit', $tagihan->id));

    $this->actingAs($admin)->get(route('admin.keuangan.tagihan.show', $tagihan->id))
        ->assertOk()
        ->assertDontSee(route('admin.keuangan.tagihan.edit', $tagihan->id));
});

it('blocks a view-only keuangan admin from deleting via the livewire method directly', function () {
    $admin = adminUser('admin_keuangan');
    $tagihan = Tagihan::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $tagihan->id)
        ->assertStatus(403);

    expect(Tagihan::find($tagihan->id))->not->toBeNull();
});

it('lets a keuangan admin create, edit, and delete tagihan once granted the specific permissions', function () {
    $admin = adminUser('admin_keuangan');
    $admin->givePermissionTo(['create tagihan', 'update tagihan', 'delete tagihan']);

    $mahasiswa = Mahasiswa::factory()->create();
    $semester = Semester::factory()->create();
    $komponen = KomponenBiaya::factory()->create();

    $this->actingAs($admin)->get(route('admin.keuangan.tagihan.create'))
        ->assertOk()
        ->assertSee('Tambah Tagihan');

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->call('selectMahasiswa', $mahasiswa->id, 'label')
        ->set('id_semester', $semester->id)
        ->set('rincian.0.id_komponen_biaya', (string) $komponen->id)
        ->set('rincian.0.nominal', '1000000')
        ->call('save')
        ->assertRedirect(route('admin.keuangan.tagihan'));

    $tagihan = Tagihan::where('id_mahasiswa', $mahasiswa->id)->firstOrFail();

    $this->actingAs($admin)->get(route('admin.keuangan.tagihan.edit', $tagihan->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $tagihan->id)
        ->call('delete');

    expect(Tagihan::find($tagihan->id))->toBeNull();
});

it('still lets superadmin do everything on tagihan regardless of granular mode', function () {
    $admin = adminUser();
    $tagihan = Tagihan::factory()->create();

    $this->actingAs($admin)->get(route('admin.keuangan.tagihan.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.keuangan.tagihan.edit', $tagihan->id))->assertOk();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $tagihan->id)
        ->call('delete');

    expect(Tagihan::find($tagihan->id))->toBeNull();
});

it('still blocks akademik-only admins from tagihan entirely in granular mode', function () {
    $admin = adminUser('admin_akademik');

    $this->actingAs($admin)->get(route('admin.keuangan.tagihan'))->assertStatus(403);
});
