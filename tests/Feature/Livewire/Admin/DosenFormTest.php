<?php

use App\Livewire\Admin\Dosen\Form;
use App\Models\Dosen;
use Livewire\Livewire;

it('renders the create form as a full page', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.administrasi.dosen.create'))
        ->assertOk()
        ->assertSee('Tambah Dosen');
});

it('creates a dosen', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Ahmad Wijaya')
        ->set('kode_dosen', 'DSN-001')
        ->set('email', 'ahmad.wijaya@example.test')
        ->call('save')
        ->assertRedirect(route('admin.administrasi.dosen'));

    expect(Dosen::where('nama', 'Ahmad Wijaya')->where('kode_dosen', 'DSN-001')->exists())->toBeTrue();
});

it('requires nama', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', '')
        ->call('save')
        ->assertHasErrors(['nama' => 'required']);
});

it('rejects a duplicate email', function () {
    $admin = adminUser();
    Dosen::factory()->create(['email' => 'dup@example.test']);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Dosen Baru')
        ->set('email', 'dup@example.test')
        ->call('save')
        ->assertHasErrors(['email' => 'unique']);
});

it('loads and updates an existing dosen', function () {
    $admin = adminUser();
    $dosen = Dosen::factory()->create(['nama' => 'Nama Lama']);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $dosen->id])
        ->assertSet('nama', 'Nama Lama')
        ->set('nama', 'Nama Baru')
        ->call('save')
        ->assertRedirect(route('admin.administrasi.dosen'));

    expect($dosen->fresh()->nama)->toBe('Nama Baru');
});

it('saves kuota bimbingan as integers with default 0 when left blank', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Dosen Kuota')
        ->call('save');

    $dosen = Dosen::where('nama', 'Dosen Kuota')->firstOrFail();
    expect($dosen->kuota_bimbingan_akademik)->toBe(0);
    expect($dosen->kuota_bimbingan_ta)->toBe(0);
});
