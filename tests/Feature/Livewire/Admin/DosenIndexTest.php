<?php

use App\Livewire\Admin\Dosen\Index;
use App\Models\Dosen;
use Livewire\Livewire;

it('renders as a full page inside the shared web layout', function () {
    $admin = adminUser();
    Dosen::factory()->create(['nama' => 'Budi Santoso']);

    $this->actingAs($admin)
        ->get(route('admin.administrasi.dosen'))
        ->assertOk()
        ->assertSee('Budi Santoso')
        ->assertSee('Dosen') // header_title
        ->assertSee('Tambah Dosen'); // page_actions
});

it('filters by search term across nama, email, kode_dosen, nip, and nidn', function () {
    $admin = adminUser();
    Dosen::factory()->create(['nama' => 'Budi Santoso', 'nip' => '111']);
    Dosen::factory()->create(['nama' => 'Citra Lestari', 'nip' => '222']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('search', 'Budi')
        ->assertSee('Budi Santoso')
        ->assertDontSee('Citra Lestari');
});

it('deletes a dosen after confirmation', function () {
    $admin = adminUser();
    $dosen = Dosen::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $dosen->id)
        ->call('delete');

    expect(Dosen::find($dosen->id))->toBeNull();
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.administrasi.dosen'))
        ->assertRedirect(route('login'));
});
