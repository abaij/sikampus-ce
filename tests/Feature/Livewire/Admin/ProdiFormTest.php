<?php

use App\Livewire\Admin\Prodi\Form;
use App\Models\Fakultas;
use App\Models\Prodi;
use Livewire\Livewire;

it('renders the create form as a full page', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.prodi.create'))
        ->assertOk()
        ->assertSee('Tambah Prodi');
});

it('creates a prodi', function () {
    $admin = adminUser();
    $fakultas = Fakultas::factory()->create();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Sistem Informasi')
        ->set('kode', 'SI')
        ->set('id_fakultas', $fakultas->id)
        ->call('save')
        ->assertRedirect(route('admin.prodi.index'));

    expect(Prodi::where('nama', 'Sistem Informasi')->where('id_fakultas', $fakultas->id)->exists())->toBeTrue();
});

it('requires nama', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', '')
        ->call('save')
        ->assertHasErrors(['nama' => 'required']);
});

it('loads and updates an existing prodi', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create(['nama' => 'Prodi Lama']);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $prodi->id])
        ->assertSet('nama', 'Prodi Lama')
        ->set('nama', 'Prodi Baru')
        ->call('save')
        ->assertRedirect(route('admin.prodi.index'));

    expect($prodi->fresh()->nama)->toBe('Prodi Baru');
});

it('admin dengan scope prodi tidak boleh membuat prodi baru', function () {
    $prodi = Prodi::factory()->create();
    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodi->id);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Prodi Baru')
        ->call('save')
        ->assertStatus(403);
});

it('admin dengan scope fakultas tidak bisa memindah prodi ke fakultas di luar scope-nya', function () {
    $fakultasA = Fakultas::factory()->create();
    $fakultasB = Fakultas::factory()->create();
    $prodi = Prodi::factory()->create(['id_fakultas' => $fakultasA->id]);

    $admin = adminUser('admin_akademik');
    scopeAdminToFakultas($admin, $fakultasA->id);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $prodi->id])
        ->set('id_fakultas', $fakultasB->id)
        ->call('save')
        ->assertStatus(403);
});
