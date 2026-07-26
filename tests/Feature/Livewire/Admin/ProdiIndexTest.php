<?php

use App\Livewire\Admin\Prodi\Index;
use App\Models\Fakultas;
use App\Models\Prodi;
use Livewire\Livewire;

it('renders as a full page inside the shared web layout', function () {
    $admin = adminUser();
    Prodi::factory()->create(['nama' => 'Teknik Informatika']);

    $this->actingAs($admin)
        ->get(route('admin.prodi.index'))
        ->assertOk()
        ->assertSee('Teknik Informatika')
        ->assertSee('Tambah Prodi');
});

it('admin dengan scope prodi langsung hanya melihat prodi miliknya', function () {
    $prodiA = Prodi::factory()->create(['nama' => 'Prodi A']);
    $prodiB = Prodi::factory()->create(['nama' => 'Prodi B']);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Prodi A')
        ->assertDontSee('Prodi B');
});

it('admin dengan scope fakultas melihat semua prodi di fakultas itu', function () {
    $fakultasA = Fakultas::factory()->create();
    $fakultasB = Fakultas::factory()->create();
    $prodiA = Prodi::factory()->create(['nama' => 'Prodi Fakultas A', 'id_fakultas' => $fakultasA->id]);
    $prodiB = Prodi::factory()->create(['nama' => 'Prodi Fakultas B', 'id_fakultas' => $fakultasB->id]);

    $admin = adminUser('admin_akademik');
    scopeAdminToFakultas($admin, $fakultasA->id);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Prodi Fakultas A')
        ->assertDontSee('Prodi Fakultas B');
});

it('deletes a prodi after confirmation', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $prodi->id)
        ->call('delete');

    expect(Prodi::find($prodi->id))->toBeNull();
});

it('admin dengan scope prodi tidak bisa menghapus prodi di luar scope-nya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('confirmDelete', $prodiB->id)
        ->call('delete')
        ->assertStatus(403);

    expect(Prodi::find($prodiB->id))->not->toBeNull();
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.prodi.index'))
        ->assertRedirect(route('login'));
});
