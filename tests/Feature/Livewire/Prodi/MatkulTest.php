<?php

use App\Livewire\Prodi\Matkul\Index;
use App\Livewire\Prodi\Matkul\Show;
use App\Models\JenisMatkul;
use App\Models\Matkul;
use App\Models\Prodi;
use Livewire\Livewire;

it('lists only mata kuliah within the kaprodi/sekprodi scope', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    Matkul::factory()->create(['nama' => 'Matkul Prodi A', 'id_prodi' => $prodiA->id]);
    Matkul::factory()->create(['nama' => 'Matkul Prodi B', 'id_prodi' => $prodiB->id]);

    $kaprodi = kaprodiUser($prodiA);

    Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->assertSee('Matkul Prodi A')
        ->assertDontSee('Matkul Prodi B');
});

it('filters by search, semester, and status', function () {
    $prodi = Prodi::factory()->create();
    Matkul::factory()->create(['nama' => 'Kalkulus Lanjut', 'id_prodi' => $prodi->id, 'semester' => 3, 'status' => 'active']);
    Matkul::factory()->create(['nama' => 'Fisika Dasar', 'id_prodi' => $prodi->id, 'semester' => 1, 'status' => 'inactive']);
    $kaprodi = kaprodiUser($prodi);

    Livewire::actingAs($kaprodi)
        ->test(Index::class)
        ->set('search', 'Kalkulus')
        ->assertSee('Kalkulus Lanjut')
        ->assertDontSee('Fisika Dasar')
        ->set('search', '')
        ->set('filterSemester', '1')
        ->assertSee('Fisika Dasar')
        ->assertDontSee('Kalkulus Lanjut')
        ->set('filterSemester', '')
        ->set('filterStatus', 'inactive')
        ->assertSee('Fisika Dasar')
        ->assertDontSee('Kalkulus Lanjut');
});

it('has no create, edit, or delete actions available (read-only portal)', function () {
    $prodi = Prodi::factory()->create();
    Matkul::factory()->create(['id_prodi' => $prodi->id]);
    $kaprodi = kaprodiUser($prodi);

    $html = $this->actingAs($kaprodi)->get(route('prodi.matkul'))->getContent();

    expect($html)->not->toContain('wire:click="confirmDelete');
    expect($html)->not->toContain('Tambah Mata Kuliah');
});

it('shows mata kuliah detail within scope', function () {
    $prodi = Prodi::factory()->create();
    $jenis = JenisMatkul::factory()->create(['nama' => 'Wajib Prodi']);
    $matkul = Matkul::factory()->create([
        'id_prodi' => $prodi->id,
        'id_jenis_matkul' => $jenis->id,
        'nama' => 'Struktur Data',
        'kode' => 'IF201',
    ]);
    $kaprodi = kaprodiUser($prodi);

    $this->actingAs($kaprodi)
        ->get(route('prodi.matkul.show', $matkul->id))
        ->assertOk()
        ->assertSee('Struktur Data')
        ->assertSee('IF201')
        ->assertSee('Wajib Prodi');
});

it('forbids viewing a mata kuliah outside the kaprodi/sekprodi scope', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $matkulB = Matkul::factory()->create(['id_prodi' => $prodiB->id]);
    $kaprodi = kaprodiUser($prodiA);

    $this->actingAs($kaprodi)
        ->get(route('prodi.matkul.show', $matkulB->id))
        ->assertStatus(403);
});

it('has no edit or delete actions on the detail page and no prasyarat management', function () {
    $prodi = Prodi::factory()->create();
    $matkul = Matkul::factory()->create(['id_prodi' => $prodi->id]);
    $kaprodi = kaprodiUser($prodi);

    $html = $this->actingAs($kaprodi)->get(route('prodi.matkul.show', $matkul->id))->getContent();

    expect($html)->not->toContain('wire:click="confirmDeleteMatkul"');
    expect($html)->not->toContain('Prasyarat');

    $component = Livewire::actingAs($kaprodi)->test(Show::class, ['id' => $matkul->id]);
    expect(method_exists($component->instance(), 'deleteMatkul'))->toBeFalse();
    expect(method_exists($component->instance(), 'savePrasyarat'))->toBeFalse();
});

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('prodi.matkul'))->assertRedirect(route('login'));
});
