<?php

use App\Livewire\Admin\Fakultas\Index;
use App\Models\Fakultas;
use App\Models\User;
use Livewire\Livewire;

it('renders as a full page inside the shared web layout', function () {
    $admin = adminUser();
    Fakultas::factory()->create(['nama' => 'Fakultas Teknik']);

    $this->actingAs($admin)
        ->get(route('admin.fakultas.index'))
        ->assertOk()
        ->assertSee('Fakultas Teknik')
        ->assertSee('Fakultas') // header_title, via ->extends() merge ke layouts.web
        ->assertSee('Tambah Fakultas'); // header_actions
});

it('filters by search term', function () {
    $admin = adminUser();
    Fakultas::factory()->create(['nama' => 'Fakultas Teknik']);
    Fakultas::factory()->create(['nama' => 'Fakultas Ekonomi']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('search', 'Teknik')
        ->assertSee('Fakultas Teknik')
        ->assertDontSee('Fakultas Ekonomi');
});

it('admin dengan scope fakultas hanya melihat fakultas dalam scope-nya', function () {
    $fakultasA = Fakultas::factory()->create(['nama' => 'Fakultas A']);
    $fakultasB = Fakultas::factory()->create(['nama' => 'Fakultas B']);

    $admin = adminUser('admin_akademik');
    scopeAdminToFakultas($admin, $fakultasA->id);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Fakultas A')
        ->assertDontSee('Fakultas B');
});

it('deletes a fakultas after confirmation', function () {
    $admin = adminUser();
    $fakultas = Fakultas::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $fakultas->id)
        ->call('delete');

    expect(Fakultas::find($fakultas->id))->toBeNull();
});

it('admin dengan scope fakultas tidak bisa menghapus fakultas di luar scope-nya', function () {
    $fakultasA = Fakultas::factory()->create();
    $fakultasB = Fakultas::factory()->create();

    $admin = adminUser('admin_akademik');
    scopeAdminToFakultas($admin, $fakultasA->id);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $fakultasB->id)
        ->call('delete')
        ->assertStatus(403);

    expect(Fakultas::find($fakultasB->id))->not->toBeNull();
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.fakultas.index'))
        ->assertRedirect(route('admin.login'));
});

it('shows a 403 page for an authenticated user without an admin role', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa']);

    $this->actingAs($mahasiswa)
        ->get(route('admin.fakultas.index'))
        ->assertForbidden();
});
