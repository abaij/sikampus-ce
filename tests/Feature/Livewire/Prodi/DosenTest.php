<?php

use App\Livewire\Prodi\Dosen\Index;
use App\Livewire\Prodi\Dosen\Show;
use App\Models\Dosen;
use App\Models\Prodi;
use Livewire\Livewire;

it('lists dosen without prodi scope filtering, mirroring DosenController::index', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    Dosen::factory()->create(['nama' => 'Dosen Prodi A Punya']);
    Dosen::factory()->create(['nama' => 'Dosen Prodi B Punya']);

    $kaprodi = kaprodiUser($prodiA);

    // Sengaja tidak ada isolasi scope di sini — DosenController::index tidak memfilter
    // berdasarkan prodi sama sekali, jadi kaprodi/sekprodi bisa melihat semua dosen institusi.
    Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->assertSee('Dosen Prodi A Punya')
        ->assertSee('Dosen Prodi B Punya');
});

it('filters by search term across nama, email, kode_dosen, nip, and nidn', function () {
    $prodi = Prodi::factory()->create();
    Dosen::factory()->create(['nama' => 'Budi Santoso', 'kode_dosen' => 'DS001']);
    Dosen::factory()->create(['nama' => 'Citra Dewi', 'kode_dosen' => 'DS002']);
    $kaprodi = kaprodiUser($prodi);

    Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->set('search', 'DS001')
        ->assertSee('Budi Santoso')
        ->assertDontSee('Citra Dewi');
});

it('has no create, edit, or delete actions available (read-only portal)', function () {
    $prodi = Prodi::factory()->create();
    Dosen::factory()->create();
    $kaprodi = kaprodiUser($prodi);

    $html = $this->actingAs($kaprodi)->get(route('prodi.dosen'))->getContent();

    expect($html)->not->toContain('wire:click="confirmDelete');
    expect($html)->not->toContain('Tambah Dosen');
});

it('shows dosen detail for any dosen regardless of prodi, mirroring DosenController::show', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $dosen = Dosen::factory()->create(['nama' => 'Dr. Wibowo', 'nidn' => '0099887766']);
    $kaprodi = kaprodiUser($prodiA);

    $this->actingAs($kaprodi)
        ->get(route('prodi.dosen.show', $dosen->id))
        ->assertOk()
        ->assertSee('Dr. Wibowo')
        ->assertSee('0099887766');
});

it('returns a 404 for a dosen id that does not exist', function () {
    $prodi = Prodi::factory()->create();
    $kaprodi = kaprodiUser($prodi);

    $this->actingAs($kaprodi)
        ->get(route('prodi.dosen.show', 999999))
        ->assertStatus(404);
});

it('has no edit or delete actions on the detail page', function () {
    $prodi = Prodi::factory()->create();
    $dosen = Dosen::factory()->create();
    $kaprodi = kaprodiUser($prodi);

    $html = $this->actingAs($kaprodi)->get(route('prodi.dosen.show', $dosen->id))->getContent();

    expect($html)->not->toContain('wire:click="deleteDosen"');
    expect($html)->not->toContain('wire:click="confirmDeleteDosen"');

    $component = Livewire::actingAs($kaprodi)->test(Show::class, ['id' => $dosen->id]);
    expect(method_exists($component->instance(), 'deleteDosen'))->toBeFalse();
});

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('prodi.dosen'))->assertRedirect(route('login'));
});
