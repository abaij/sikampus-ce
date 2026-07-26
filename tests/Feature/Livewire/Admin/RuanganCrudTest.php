<?php

use App\Livewire\Admin\Ruangan\Form;
use App\Livewire\Admin\Ruangan\Index;
use App\Models\Ruangan;
use Livewire\Livewire;

it('renders index and create form as full pages', function () {
    $admin = adminUser();
    Ruangan::factory()->create(['nama' => 'Lab Komputer']);

    $this->actingAs($admin)->get(route('admin.administrasi.ruangan'))->assertOk()->assertSee('Lab Komputer');
    $this->actingAs($admin)->get(route('admin.administrasi.ruangan.create'))->assertOk()->assertSee('Tambah Ruangan');
});

it('creates, updates, and deletes a ruangan', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Aula')
        ->set('kapasitas', 200)
        ->call('save')
        ->assertRedirect(route('admin.administrasi.ruangan'));

    $ruangan = Ruangan::where('nama', 'Aula')->firstOrFail();
    expect($ruangan->kapasitas)->toBe(200);

    Livewire::actingAs($admin)
        ->test(Form::class, ['id' => $ruangan->id])
        ->assertSet('nama', 'Aula')
        ->assertSet('kapasitas', 200)
        ->set('nama', 'Aula Utama')
        ->call('save');

    expect($ruangan->fresh()->nama)->toBe('Aula Utama');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $ruangan->id)
        ->call('delete');

    expect(Ruangan::find($ruangan->id))->toBeNull();
});

it('rejects a duplicate nama', function () {
    $admin = adminUser();
    Ruangan::factory()->create(['nama' => 'Ruang B201']);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nama', 'Ruang B201')
        ->call('save')
        ->assertHasErrors('nama');
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.administrasi.ruangan'))->assertRedirect(route('login'));
});
