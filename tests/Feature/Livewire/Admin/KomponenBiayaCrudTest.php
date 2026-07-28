<?php

use App\Livewire\Admin\KomponenBiaya\Form;
use App\Livewire\Admin\KomponenBiaya\Index;
use App\Models\KomponenBiaya;
use Livewire\Livewire;

it('renders index and create form as full pages', function () {
    $admin = adminUser();
    KomponenBiaya::factory()->create(['nama' => 'Uang Kuliah Tunggal']);

    $this->actingAs($admin)->get(route('admin.keuangan.komponen-biaya'))->assertOk()->assertSee('Uang Kuliah Tunggal');
    $this->actingAs($admin)->get(route('admin.keuangan.komponen-biaya.create'))->assertOk()->assertSee('Tambah Komponen Biaya');
});

it('creates, updates, and deletes a komponen biaya', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('kode', 'UKT')
        ->set('nama', 'Uang Kuliah Tunggal')
        ->set('is_per_semester', true)
        ->set('is_akademik', true)
        ->call('save')
        ->assertRedirect(route('admin.keuangan.komponen-biaya'));

    $komponenBiaya = KomponenBiaya::where('kode', 'UKT')->firstOrFail();
    expect($komponenBiaya->is_akademik)->toBeTrue();

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $komponenBiaya->id])
        ->assertSet('nama', 'Uang Kuliah Tunggal')
        ->set('nama', 'UKT Semester Genap')
        ->call('save');

    expect($komponenBiaya->fresh()->nama)->toBe('UKT Semester Genap');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $komponenBiaya->id)
        ->call('delete');

    expect(KomponenBiaya::find($komponenBiaya->id))->toBeNull();
});

it('rejects a duplicate kode when creating a komponen biaya', function () {
    $admin = adminUser();
    KomponenBiaya::factory()->create(['kode' => 'UKT']);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('kode', 'UKT')
        ->set('nama', 'Uang Kuliah Tunggal Baru')
        ->call('save')
        ->assertHasErrors('kode');
});

it('requires kode and nama', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('kode', '')
        ->set('nama', '')
        ->call('save')
        ->assertHasErrors(['kode' => 'required', 'nama' => 'required']);
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.keuangan.komponen-biaya'))->assertRedirect(route('login'));
});
