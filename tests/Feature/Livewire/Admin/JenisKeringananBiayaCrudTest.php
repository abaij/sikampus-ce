<?php

use App\Livewire\Admin\JenisKeringananBiaya\Form;
use App\Livewire\Admin\JenisKeringananBiaya\Index;
use App\Models\JenisKeringananBiaya;
use Livewire\Livewire;

it('renders index and create form as full pages', function () {
    $admin = adminUser();
    JenisKeringananBiaya::factory()->create(['nama' => 'Keringanan Yatim Piatu']);

    $this->actingAs($admin)->get(route('admin.keuangan.jenis-keringanan-biaya'))->assertOk()->assertSee('Keringanan Yatim Piatu');
    $this->actingAs($admin)->get(route('admin.keuangan.jenis-keringanan-biaya.create'))->assertOk()->assertSee('Tambah Jenis Keringanan Biaya');
});

it('creates a jenis keringanan biaya with a fixed nominal', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Keringanan Prestasi')
        ->set('is_persentase', false)
        ->set('nominal', '500000')
        ->set('is_active', true)
        ->call('save')
        ->assertRedirect(route('admin.keuangan.jenis-keringanan-biaya'));

    $row = JenisKeringananBiaya::where('nama', 'Keringanan Prestasi')->firstOrFail();
    expect($row->is_persentase)->toBeFalse();
    expect((float) $row->nominal)->toBe(500000.0);
    expect($row->is_active)->toBeTrue();
});

it('rejects a percentage nominal above 100', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Keringanan Persentase')
        ->set('is_persentase', true)
        ->set('nominal', '150')
        ->call('save')
        ->assertHasErrors('nominal');
});

it('updates a jenis keringanan biaya', function () {
    $admin = adminUser();
    $row = JenisKeringananBiaya::factory()->create(['nama' => 'Lama', 'is_active' => true]);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $row->id])
        ->set('nama', 'Baru')
        ->set('is_active', false)
        ->call('save')
        ->assertRedirect(route('admin.keuangan.jenis-keringanan-biaya'));

    expect($row->fresh()->nama)->toBe('Baru');
    expect($row->fresh()->is_active)->toBeFalse();
});

it('deletes a jenis keringanan biaya', function () {
    $admin = adminUser();
    $row = JenisKeringananBiaya::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $row->id)
        ->call('delete');

    expect(JenisKeringananBiaya::find($row->id))->toBeNull();
});

it('searches by nama or keterangan', function () {
    $admin = adminUser();
    JenisKeringananBiaya::factory()->create(['nama' => 'Findable Target']);
    JenisKeringananBiaya::factory()->create(['nama' => 'Lain Sama Sekali']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('search', 'Findable')
        ->assertSee('Findable Target')
        ->assertDontSee('Lain Sama Sekali');
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.keuangan.jenis-keringanan-biaya'))->assertRedirect(route('login'));
});
