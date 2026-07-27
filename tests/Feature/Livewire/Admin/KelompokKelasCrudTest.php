<?php

use App\Livewire\Admin\KelompokKelas\Form;
use App\Livewire\Admin\KelompokKelas\Index;
use App\Models\KelompokKelas;
use Livewire\Livewire;

it('renders index and create form as full pages', function () {
    $admin = adminUser();
    KelompokKelas::factory()->create(['nama' => 'KEB 2022']);

    $this->actingAs($admin)->get(route('admin.administrasi.kelas-mahasiswa'))->assertOk()->assertSee('KEB 2022');
    $this->actingAs($admin)->get(route('admin.administrasi.kelas-mahasiswa.create'))->assertOk()->assertSee('Tambah Kelas Mahasiswa');
});

it('renders the edit page for an existing kelompok kelas', function () {
    $admin = adminUser();
    $kelompokKelas = KelompokKelas::factory()->create(['nama' => 'KEB 2023']);

    $this->actingAs($admin)
        ->get(route('admin.administrasi.kelas-mahasiswa.edit', $kelompokKelas->id))
        ->assertOk()
        ->assertSee('KEB 2023');
});

it('creates and updates a kelompok kelas', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'KEB 2025')
        ->call('save')
        ->assertRedirect(route('admin.administrasi.kelas-mahasiswa'));

    $kelompokKelas = KelompokKelas::where('nama', 'KEB 2025')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $kelompokKelas->id])
        ->assertSet('nama', 'KEB 2025')
        ->set('nama', 'KEB 2025 Revisi')
        ->call('save');

    expect($kelompokKelas->fresh()->nama)->toBe('KEB 2025 Revisi');
});

it('deletes a kelompok kelas', function () {
    $admin = adminUser();
    $kelompokKelas = KelompokKelas::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $kelompokKelas->id)
        ->call('delete');

    expect(KelompokKelas::find($kelompokKelas->id))->toBeNull();
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.administrasi.kelas-mahasiswa'))->assertRedirect(route('login'));
});
