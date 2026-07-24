<?php

use App\Livewire\Admin\JalurMasuk\Form;
use App\Livewire\Admin\JalurMasuk\Index;
use App\Models\JalurMasuk;
use Livewire\Livewire;

it('renders index and create form as full pages', function () {
    $admin = adminUser();
    JalurMasuk::factory()->create(['nama' => 'Jalur Prestasi']);

    $this->actingAs($admin)->get(route('admin.jalur-masuk.index'))->assertOk()->assertSee('Jalur Prestasi');
    $this->actingAs($admin)->get(route('admin.jalur-masuk.create'))->assertOk()->assertSee('Tambah Jalur Masuk');
});

it('creates, updates, and deletes a jalur masuk', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Jalur Reguler')
        ->set('has_selection', false)
        ->call('save')
        ->assertRedirect(route('admin.jalur-masuk.index'));

    $jalurMasuk = JalurMasuk::where('nama', 'Jalur Reguler')->firstOrFail();
    expect($jalurMasuk->has_selection)->toBeFalse();

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $jalurMasuk->id])
        ->assertSet('nama', 'Jalur Reguler')
        ->set('status', 'inactive')
        ->call('save');

    expect($jalurMasuk->fresh()->status)->toBe('inactive');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $jalurMasuk->id)
        ->call('delete');

    expect(JalurMasuk::find($jalurMasuk->id))->toBeNull();
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.jalur-masuk.index'))->assertRedirect(route('admin.login'));
});
