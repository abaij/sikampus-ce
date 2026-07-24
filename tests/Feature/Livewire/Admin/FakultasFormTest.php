<?php

use App\Livewire\Admin\Fakultas\Form;
use App\Models\Fakultas;
use Livewire\Livewire;

it('renders the create form as a full page', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.fakultas.create'))
        ->assertOk()
        ->assertSee('Tambah Fakultas');
});

it('creates a fakultas', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Fakultas Ilmu Komputer')
        ->set('kode', 'FIK')
        ->call('save')
        ->assertRedirect(route('admin.fakultas.index'));

    expect(Fakultas::where('nama', 'Fakultas Ilmu Komputer')->where('kode', 'FIK')->exists())->toBeTrue();
});

it('requires nama', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', '')
        ->call('save')
        ->assertHasErrors(['nama' => 'required']);
});

it('rejects a duplicate nama', function () {
    $admin = adminUser();
    Fakultas::factory()->create(['nama' => 'Fakultas Teknik']);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Fakultas Teknik')
        ->call('save')
        ->assertHasErrors(['nama' => 'unique']);
});

it('loads and updates an existing fakultas', function () {
    $admin = adminUser();
    $fakultas = Fakultas::factory()->create(['nama' => 'Fakultas Lama']);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $fakultas->id])
        ->assertSet('nama', 'Fakultas Lama')
        ->set('nama', 'Fakultas Baru')
        ->call('save')
        ->assertRedirect(route('admin.fakultas.index'));

    expect($fakultas->fresh()->nama)->toBe('Fakultas Baru');
});

it('admin dengan scope fakultas tidak boleh membuat fakultas baru', function () {
    $fakultas = Fakultas::factory()->create();
    $admin = adminUser('admin_akademik');
    scopeAdminToFakultas($admin, $fakultas->id);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->assertStatus(403);
});

it('admin dengan scope fakultas tidak bisa mengubah fakultas di luar scope-nya', function () {
    $fakultasA = Fakultas::factory()->create();
    $fakultasB = Fakultas::factory()->create();

    $admin = adminUser('admin_akademik');
    scopeAdminToFakultas($admin, $fakultasA->id);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $fakultasB->id])
        ->assertStatus(403);
});
