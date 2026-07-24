<?php

use App\Models\Mahasiswa;
use App\Models\Matkul;
use App\Models\Prodi;

it('admin dengan scope prodi hanya melihat daftar prodi miliknya sendiri', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    $ids = collect($this->actingAs($admin)->getJson('/api/prodi')->assertOk()->json('data'))->pluck('id');

    expect($ids)->toContain($prodiA->id);
    expect($ids)->not->toContain($prodiB->id);

    $this->actingAs($admin)->getJson("/api/prodi/{$prodiA->id}")->assertOk();
    $this->actingAs($admin)->getJson("/api/prodi/{$prodiB->id}")->assertForbidden();
});

it('admin dengan scope prodi hanya melihat mahasiswa dari prodinya sendiri', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $mahasiswaA = Mahasiswa::factory()->create(['id_prodi' => $prodiA->id]);
    Mahasiswa::factory()->create(['id_prodi' => $prodiB->id]);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    $ids = collect($this->actingAs($admin)->getJson('/api/mahasiswa')->assertOk()->json('data'))->pluck('id');

    expect($ids)->toContain($mahasiswaA->id);
    expect($ids)->toHaveCount(1);
});

it('admin dengan scope prodi hanya melihat mata kuliah dari prodinya sendiri', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $matkulA = Matkul::factory()->create(['id_prodi' => $prodiA->id]);
    Matkul::factory()->create(['id_prodi' => $prodiB->id]);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    $ids = collect($this->actingAs($admin)->getJson('/api/matkul')->assertOk()->json('data'))->pluck('id');

    expect($ids)->toContain($matkulA->id);
    expect($ids)->toHaveCount(1);
});

it('admin ber-scope prodi saja tidak boleh membuat prodi baru', function () {
    $prodiA = Prodi::factory()->create();

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    $this->actingAs($admin)->postJson('/api/prodi', [
        'nama' => 'Prodi Baru',
        'id_fakultas' => $prodiA->id_fakultas,
    ])->assertForbidden();
});
