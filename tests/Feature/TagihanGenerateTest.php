<?php

use App\Models\KategoriBiaya;
use App\Models\KategoriBiayaMahasiswa;
use App\Models\KomponenBiaya;
use App\Models\Mahasiswa;
use App\Models\Semester;
use App\Models\StrukturBiaya;
use App\Models\Tagihan;
use App\Models\TagihanRinci;
use App\Models\User;

it('generate tagihan dari struktur biaya menjumlahkan seluruh komponen dalam satu tahap', function () {
    $admin = adminUser('admin_keuangan');
    $periode = Semester::factory()->create();
    $angkatan = Semester::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create(['id_semester_masuk' => $angkatan->id]);

    $spp = KomponenBiaya::factory()->create(['kode' => 'SPP']);
    $bpp = KomponenBiaya::factory()->create(['kode' => 'BPP']);
    StrukturBiaya::factory()->create([
        'id_periode' => $periode->id, 'id_angkatan' => $angkatan->id,
        'id_kategori_biaya' => null, 'id_komponen_biaya' => $spp->id, 'tahap' => 1, 'nominal' => 500000,
    ]);
    StrukturBiaya::factory()->create([
        'id_periode' => $periode->id, 'id_angkatan' => $angkatan->id,
        'id_kategori_biaya' => null, 'id_komponen_biaya' => $bpp->id, 'tahap' => 1, 'nominal' => 300000,
    ]);

    $response = $this->actingAs($admin)->postJson('/api/tagihan/generate', [
        'id_periode' => $periode->id,
        'id_angkatan' => $angkatan->id,
        'opsi_tahap' => 'all',
    ]);

    $response->assertOk()->assertJson(['created_count' => 1, 'skipped_count' => 0]);

    $tagihan = Tagihan::where('id_mahasiswa', $mahasiswa->id)->first();
    expect($tagihan)->not->toBeNull();
    expect((float) $tagihan->total)->toBe(800000.0);
    expect($tagihan->status)->toBe('unpaid');
    expect(TagihanRinci::where('id_tagihan', $tagihan->id)->count())->toBe(2);
});

it('hanya menagih mahasiswa dengan kategori biaya yang cocok saat struktur biaya dibatasi per kategori', function () {
    $admin = adminUser('admin_keuangan');
    $periode = Semester::factory()->create();
    $angkatan = Semester::factory()->create();

    $kategoriReguler = KategoriBiaya::factory()->create(['nama' => 'Reguler']);
    $mahasiswaReguler = Mahasiswa::factory()->create(['id_semester_masuk' => $angkatan->id]);
    KategoriBiayaMahasiswa::factory()->create([
        'id_mahasiswa' => $mahasiswaReguler->id,
        'id_kategori_biaya' => $kategoriReguler->id,
        'id_semester' => $angkatan->id,
        'status' => 'active',
    ]);
    $mahasiswaTanpaKategori = Mahasiswa::factory()->create(['id_semester_masuk' => $angkatan->id]);

    $spp = KomponenBiaya::factory()->create(['kode' => 'SPP']);
    StrukturBiaya::factory()->create([
        'id_periode' => $periode->id, 'id_angkatan' => $angkatan->id,
        'id_kategori_biaya' => $kategoriReguler->id, 'id_komponen_biaya' => $spp->id, 'tahap' => 1, 'nominal' => 500000,
    ]);

    $response = $this->actingAs($admin)->postJson('/api/tagihan/generate', [
        'id_periode' => $periode->id,
        'id_angkatan' => $angkatan->id,
        'opsi_tahap' => 'all',
    ]);

    $response->assertOk()->assertJson(['created_count' => 1, 'skipped_count' => 1]);

    expect(Tagihan::where('id_mahasiswa', $mahasiswaReguler->id)->exists())->toBeTrue();
    expect(Tagihan::where('id_mahasiswa', $mahasiswaTanpaKategori->id)->exists())->toBeFalse();
});

it('tidak membuat tagihan duplikat kalau tahap yang sama sudah pernah di-generate untuk periode itu', function () {
    $admin = adminUser('admin_keuangan');
    $periode = Semester::factory()->create();
    $angkatan = Semester::factory()->create();
    Mahasiswa::factory()->create(['id_semester_masuk' => $angkatan->id]);

    $spp = KomponenBiaya::factory()->create(['kode' => 'SPP']);
    StrukturBiaya::factory()->create([
        'id_periode' => $periode->id, 'id_angkatan' => $angkatan->id,
        'id_kategori_biaya' => null, 'id_komponen_biaya' => $spp->id, 'tahap' => 1, 'nominal' => 500000,
    ]);

    $payload = ['id_periode' => $periode->id, 'id_angkatan' => $angkatan->id, 'opsi_tahap' => 'all'];

    $this->actingAs($admin)->postJson('/api/tagihan/generate', $payload)
        ->assertOk()->assertJson(['created_count' => 1, 'skipped_count' => 0]);

    // Generate ulang dengan filter persis sama -> harus dilewati, bukan bikin tagihan kedua.
    $this->actingAs($admin)->postJson('/api/tagihan/generate', $payload)
        ->assertOk()->assertJson(['created_count' => 0, 'skipped_count' => 1]);

    expect(Tagihan::where('id_semester', $periode->id)->count())->toBe(1);
});

it('menolak generate tagihan oleh user yang bukan admin keuangan', function () {
    $mahasiswaUser = User::factory()->create(['role' => 'mahasiswa']);
    $periode = Semester::factory()->create();
    $angkatan = Semester::factory()->create();

    $this->actingAs($mahasiswaUser)->postJson('/api/tagihan/generate', [
        'id_periode' => $periode->id,
        'id_angkatan' => $angkatan->id,
        'opsi_tahap' => 'all',
    ])->assertForbidden();
});
