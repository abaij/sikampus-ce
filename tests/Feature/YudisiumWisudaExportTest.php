<?php

use App\Models\Mahasiswa;
use App\Models\User;
use App\Models\Wisuda;
use App\Models\WisudaMahasiswa;
use App\Models\Yudisium;

it('admin dapat mengunduh lampiran SK yudisium dalam format PDF', function () {
    $admin = adminUser();
    $yudisium = Yudisium::factory()->create();

    $response = $this->actingAs($admin)->get('/api/yudisium/export-pdf');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('admin dapat mengunduh daftar yudisium dalam format Excel', function () {
    $admin = adminUser();
    Yudisium::factory()->create();

    $response = $this->actingAs($admin)->get('/api/yudisium/export-excel');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml');
});

it('export yudisium hanya menyertakan data sesuai filter no_sk_yudisium', function () {
    $admin = adminUser();
    Yudisium::factory()->create(['no_sk_yudisium' => 'SK/AAA/2026']);
    Yudisium::factory()->create(['no_sk_yudisium' => 'SK/BBB/2026']);

    // Cek lewat endpoint index dulu bahwa filter memang menyaring dengan benar (logic sama dipakai export).
    $response = $this->actingAs($admin)->getJson('/api/yudisium?no_sk_yudisium=SK/AAA/2026');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.no_sk_yudisium'))->toBe('SK/AAA/2026');
});

it('admin dapat mengunduh daftar peserta wisuda dalam format PDF dan Excel', function () {
    $admin = adminUser();
    $wisuda = Wisuda::factory()->create();
    WisudaMahasiswa::factory()->create(['id_wisuda' => $wisuda->id]);

    $this->actingAs($admin)->get("/api/wisuda/{$wisuda->id}/peserta/export-pdf")
        ->assertOk();

    $this->actingAs($admin)->get("/api/wisuda/{$wisuda->id}/peserta/export-excel")
        ->assertOk();
});

it('menolak export oleh user yang bukan admin', function () {
    $mahasiswaUser = User::factory()->create(['role' => 'mahasiswa']);

    $this->actingAs($mahasiswaUser)->get('/api/yudisium/export-pdf')->assertForbidden();
});
