<?php

use App\Livewire\Admin\Tagihan\Generate;
use App\Models\KategoriBiaya;
use App\Models\KategoriBiayaMahasiswa;
use App\Models\KomponenBiaya;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\StrukturBiaya;
use App\Models\Tagihan;
use Livewire\Livewire;

it('renders the generate tagihan page as a full page', function () {
    $admin = adminUser();
    $periode = Semester::factory()->create(['nama' => 'Periode Uji', 'tanggal_mulai' => '2024-09-01']);
    $angkatan = Semester::factory()->create();
    StrukturBiaya::factory()->create(['id_periode' => $periode->id, 'id_angkatan' => $angkatan->id]);

    $this->actingAs($admin)
        ->get(route('admin.keuangan.tagihan.generate'))
        ->assertOk()
        ->assertSee('Periode Uji');
});

it('groups struktur biaya rows by periode/angkatan/prodi/komponen, collecting all tahap', function () {
    $admin = adminUser();
    $prodi = Prodi::factory()->create();
    $angkatan = Semester::factory()->create();
    $periode = Semester::factory()->create(['tanggal_mulai' => '2024-09-01']);
    $komponen = KomponenBiaya::factory()->create();

    StrukturBiaya::factory()->create([
        'id_prodi' => $prodi->id, 'id_angkatan' => $angkatan->id, 'id_periode' => $periode->id,
        'id_komponen_biaya' => $komponen->id, 'id_kategori_biaya' => null, 'tahap' => 1,
    ]);
    StrukturBiaya::factory()->create([
        'id_prodi' => $prodi->id, 'id_angkatan' => $angkatan->id, 'id_periode' => $periode->id,
        'id_komponen_biaya' => $komponen->id, 'id_kategori_biaya' => null, 'tahap' => 2,
    ]);

    $groups = Livewire::actingAs($admin)->test(Generate::class)->instance()->groupedStrukturBiaya;

    expect($groups)->toHaveCount(1);
    expect($groups->first()['available_tahap'])->toBe([1, 2]);
    expect($groups->first()['total_baris_struktur'])->toBe(2);
});

it('generates tagihan for every matching mahasiswa across all tahap', function () {
    $admin = adminUser();
    $angkatan = Semester::factory()->create();
    $periode = Semester::factory()->create(['tanggal_mulai' => '2024-09-01']);
    $komponen = KomponenBiaya::factory()->create();

    StrukturBiaya::factory()->create([
        'id_prodi' => null, 'id_angkatan' => $angkatan->id, 'id_periode' => $periode->id,
        'id_komponen_biaya' => $komponen->id, 'id_kategori_biaya' => null, 'tahap' => 1, 'nominal' => 1000000,
    ]);
    StrukturBiaya::factory()->create([
        'id_prodi' => null, 'id_angkatan' => $angkatan->id, 'id_periode' => $periode->id,
        'id_komponen_biaya' => $komponen->id, 'id_kategori_biaya' => null, 'tahap' => 2, 'nominal' => 500000,
    ]);

    $mahasiswaA = Mahasiswa::factory()->create(['id_semester_masuk' => $angkatan->id]);
    $mahasiswaB = Mahasiswa::factory()->create(['id_semester_masuk' => $angkatan->id]);
    // Mahasiswa angkatan lain — tidak boleh ikut ter-generate.
    Mahasiswa::factory()->create(['id_semester_masuk' => Semester::factory()->create()->id]);

    $key = implode('|', [$periode->id, $angkatan->id, 'null', $komponen->id]);

    Livewire::actingAs($admin)
        ->test(Generate::class)
        ->call('openGenerateModal', $key)
        ->assertSet('opsiTahap', 'all')
        ->call('generate')
        ->assertHasNoErrors();

    expect(Tagihan::where('id_mahasiswa', $mahasiswaA->id)->count())->toBe(2);
    expect(Tagihan::where('id_mahasiswa', $mahasiswaB->id)->count())->toBe(2);
    expect(Tagihan::count())->toBe(4);

    // Tahap dibaca dari kolomnya, bukan dari penanda teks di keterangan.
    $tahap1 = Tagihan::where('id_mahasiswa', $mahasiswaA->id)->where('tahap', 1)->first();
    expect((float) $tahap1->total)->toBe(1000000.0);
    $tahap2 = Tagihan::where('id_mahasiswa', $mahasiswaA->id)->where('tahap', 2)->first();
    expect((float) $tahap2->total)->toBe(500000.0);
});

it('only generates the selected tahap when opsi tahap is specific', function () {
    $admin = adminUser();
    $angkatan = Semester::factory()->create();
    $periode = Semester::factory()->create(['tanggal_mulai' => '2024-09-01']);
    $komponen = KomponenBiaya::factory()->create();

    StrukturBiaya::factory()->create([
        'id_prodi' => null, 'id_angkatan' => $angkatan->id, 'id_periode' => $periode->id,
        'id_komponen_biaya' => $komponen->id, 'id_kategori_biaya' => null, 'tahap' => 1,
    ]);
    StrukturBiaya::factory()->create([
        'id_prodi' => null, 'id_angkatan' => $angkatan->id, 'id_periode' => $periode->id,
        'id_komponen_biaya' => $komponen->id, 'id_kategori_biaya' => null, 'tahap' => 2,
    ]);

    $mahasiswa = Mahasiswa::factory()->create(['id_semester_masuk' => $angkatan->id]);

    $key = implode('|', [$periode->id, $angkatan->id, 'null', $komponen->id]);

    Livewire::actingAs($admin)
        ->test(Generate::class)
        ->call('openGenerateModal', $key)
        ->set('opsiTahap', 'specific')
        ->set('selectedTahap', '2')
        ->call('generate')
        ->assertHasNoErrors();

    expect(Tagihan::where('id_mahasiswa', $mahasiswa->id)->count())->toBe(1);
    expect(Tagihan::where('id_mahasiswa', $mahasiswa->id)->first()->tahap)->toBe(2);
});

it('requires a tahap to be selected when opsi tahap is specific', function () {
    $admin = adminUser();
    $angkatan = Semester::factory()->create();
    $periode = Semester::factory()->create(['tanggal_mulai' => '2024-09-01']);
    StrukturBiaya::factory()->create([
        'id_prodi' => null, 'id_angkatan' => $angkatan->id, 'id_periode' => $periode->id,
        'id_komponen_biaya' => null, 'tahap' => 1,
    ]);

    $key = implode('|', [$periode->id, $angkatan->id, 'null', 'null']);

    Livewire::actingAs($admin)
        ->test(Generate::class)
        ->call('openGenerateModal', $key)
        ->set('opsiTahap', 'specific')
        ->call('generate')
        ->assertHasErrors('selectedTahap');

    expect(Tagihan::count())->toBe(0);
});

it('skips mahasiswa whose active kategori biaya does not match a kategori-specific struktur biaya', function () {
    $admin = adminUser();
    $angkatan = Semester::factory()->create();
    $periode = Semester::factory()->create(['tanggal_mulai' => '2024-09-01']);
    $komponen = KomponenBiaya::factory()->create();
    $kategoriA = KategoriBiaya::factory()->create();
    $kategoriB = KategoriBiaya::factory()->create();

    StrukturBiaya::factory()->create([
        'id_prodi' => null, 'id_angkatan' => $angkatan->id, 'id_periode' => $periode->id,
        'id_komponen_biaya' => $komponen->id, 'id_kategori_biaya' => $kategoriA->id, 'tahap' => 1,
    ]);

    $mahasiswaCocok = Mahasiswa::factory()->create(['id_semester_masuk' => $angkatan->id]);
    KategoriBiayaMahasiswa::factory()->create(['id_mahasiswa' => $mahasiswaCocok->id, 'id_kategori_biaya' => $kategoriA->id, 'status' => 'active']);

    $mahasiswaTidakCocok = Mahasiswa::factory()->create(['id_semester_masuk' => $angkatan->id]);
    KategoriBiayaMahasiswa::factory()->create(['id_mahasiswa' => $mahasiswaTidakCocok->id, 'id_kategori_biaya' => $kategoriB->id, 'status' => 'active']);

    $key = implode('|', [$periode->id, $angkatan->id, 'null', $komponen->id]);

    Livewire::actingAs($admin)->test(Generate::class)->call('openGenerateModal', $key)->call('generate');

    expect(Tagihan::where('id_mahasiswa', $mahasiswaCocok->id)->exists())->toBeTrue();
    expect(Tagihan::where('id_mahasiswa', $mahasiswaTidakCocok->id)->exists())->toBeFalse();
});

it('skips a mahasiswa/tahap combination that already has a generated tagihan', function () {
    $admin = adminUser();
    $angkatan = Semester::factory()->create();
    $periode = Semester::factory()->create(['tanggal_mulai' => '2024-09-01']);
    $komponen = KomponenBiaya::factory()->create();

    StrukturBiaya::factory()->create([
        'id_prodi' => null, 'id_angkatan' => $angkatan->id, 'id_periode' => $periode->id,
        'id_komponen_biaya' => $komponen->id, 'id_kategori_biaya' => null, 'tahap' => 1,
    ]);

    $mahasiswa = Mahasiswa::factory()->create(['id_semester_masuk' => $angkatan->id]);
    $key = implode('|', [$periode->id, $angkatan->id, 'null', $komponen->id]);

    Livewire::actingAs($admin)->test(Generate::class)->call('openGenerateModal', $key)->call('generate');
    expect(Tagihan::where('id_mahasiswa', $mahasiswa->id)->count())->toBe(1);

    // Jalankan lagi — tidak boleh membuat tagihan duplikat untuk kombinasi mahasiswa+tahap yang sama.
    Livewire::actingAs($admin)->test(Generate::class)->call('openGenerateModal', $key)->call('generate');
    expect(Tagihan::where('id_mahasiswa', $mahasiswa->id)->count())->toBe(1);
});

it('admin dengan scope prodi hanya melihat grup struktur biaya milik prodinya', function () {
    $prodiA = Prodi::factory()->create(['nama' => 'Prodi Scope A']);
    $prodiB = Prodi::factory()->create(['nama' => 'Prodi Scope B']);

    $admin = adminUser('admin_keuangan');
    scopeAdminToProdi($admin, $prodiA->id);

    $angkatan = Semester::factory()->create();
    $periode = Semester::factory()->create(['tanggal_mulai' => '2024-09-01']);
    StrukturBiaya::factory()->create(['id_prodi' => $prodiA->id, 'id_angkatan' => $angkatan->id, 'id_periode' => $periode->id]);
    StrukturBiaya::factory()->create(['id_prodi' => $prodiB->id, 'id_angkatan' => $angkatan->id, 'id_periode' => $periode->id]);
    // Grup lintas-prodi (id_prodi null) juga tidak boleh terlihat oleh admin yang di-scope.
    StrukturBiaya::factory()->create(['id_prodi' => null, 'id_angkatan' => $angkatan->id, 'id_periode' => $periode->id]);

    $groups = Livewire::actingAs($admin)->test(Generate::class)->instance()->groupedStrukturBiaya;

    expect($groups)->toHaveCount(1);
    expect($groups->first()['id_prodi'])->toBe($prodiA->id);
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.keuangan.tagihan.generate'))->assertRedirect(route('login'));
});
