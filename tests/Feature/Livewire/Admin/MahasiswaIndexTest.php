<?php

use App\Livewire\Admin\Mahasiswa\Index;
use App\Models\KelompokKelas;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use Livewire\Livewire;

it('renders as a full page with searchable-select filters', function () {
    $admin = adminUser();
    Mahasiswa::factory()->create(['nama' => 'Budi Santoso']);

    $this->actingAs($admin)
        ->get(route('admin.administrasi.mahasiswa'))
        ->assertOk()
        ->assertSee('Budi Santoso')
        ->assertSee('x-data', false);
});

it('scopes the kelas mahasiswa filter options to the selected prodi', function () {
    $admin = adminUser();
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $kelasA = KelompokKelas::factory()->create(['nama' => 'Kelas A', 'id_prodi' => $prodiA->id]);
    $kelasB = KelompokKelas::factory()->create(['nama' => 'Kelas B', 'id_prodi' => $prodiB->id]);

    $component = Livewire::actingAs($admin)->test(Index::class);

    expect($component->instance()->kelompokKelasOptions->pluck('id'))
        ->toContain($kelasA->id, $kelasB->id);

    $component->set('filterProdi', (string) $prodiA->id);

    expect($component->instance()->kelompokKelasOptions->pluck('id'))
        ->toContain($kelasA->id)
        ->not->toContain($kelasB->id);
});

it('resets the kelas mahasiswa filter when the prodi filter changes', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create();
    $kelas = KelompokKelas::factory()->create(['id_prodi' => $prodi->id]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('filterKelompokKelas', (string) $kelas->id)
        ->set('filterProdi', (string) $prodi->id)
        ->assertSet('filterKelompokKelas', '');
});

it('filters the mahasiswa list by prodi and kelas mahasiswa', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create();
    $kelas = KelompokKelas::factory()->create(['id_prodi' => $prodi->id]);
    Mahasiswa::factory()->create(['nama' => 'Dalam Filter', 'id_prodi' => $prodi->id, 'id_kelompok_kelas' => $kelas->id]);
    Mahasiswa::factory()->create(['nama' => 'Luar Filter']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('filterStatusAkademik', '')
        ->set('filterProdi', (string) $prodi->id)
        ->set('filterKelompokKelas', (string) $kelas->id)
        ->assertSee('Dalam Filter')
        ->assertDontSee('Luar Filter');
});

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('admin.administrasi.mahasiswa'))
        ->assertRedirect(route('login'));
});
