<?php

use App\Livewire\Admin\JenisDaftar\Form;
use App\Livewire\Admin\JenisDaftar\Index;
use App\Models\JenisDaftar;
use Livewire\Livewire;

it('renders index and create form as full pages', function () {
    $admin = adminUser();
    JenisDaftar::factory()->create(['nama' => 'Pendaftaran Online']);

    $this->actingAs($admin)->get(route('admin.jenis-daftar.index'))->assertOk()->assertSee('Pendaftaran Online');
    $this->actingAs($admin)->get(route('admin.jenis-daftar.create'))->assertOk()->assertSee('Tambah Jenis Daftar');
});

it('creates, updates, and deletes a jenis daftar', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Jalur Reguler')
        ->set('deskripsi', 'Pendaftaran reguler')
        ->call('save')
        ->assertRedirect(route('admin.jenis-daftar.index'));

    $jenisDaftar = JenisDaftar::where('nama', 'Jalur Reguler')->firstOrFail();
    expect($jenisDaftar->deskripsi)->toBe('Pendaftaran reguler');

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $jenisDaftar->id])
        ->assertSet('nama', 'Jalur Reguler')
        ->set('nama', 'Jalur Reguler Baru')
        ->call('save');

    expect($jenisDaftar->fresh()->nama)->toBe('Jalur Reguler Baru');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $jenisDaftar->id)
        ->call('delete');

    expect(JenisDaftar::find($jenisDaftar->id))->toBeNull();
});

it('rejects duplicate nama', function () {
    $admin = adminUser();
    JenisDaftar::factory()->create(['nama' => 'Jalur Reguler']);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Jalur Reguler')
        ->call('save')
        ->assertHasErrors(['nama']);
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.jenis-daftar.index'))->assertRedirect(route('login'));
});
