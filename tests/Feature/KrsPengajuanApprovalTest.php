<?php

use App\Models\AturanAksesKeuangan;
use App\Models\Dosen;
use App\Models\DosenWali;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Matkul;
use App\Models\MatkulPrasyarat;
use App\Models\Nilai;
use App\Models\Notifikasi;
use App\Models\Semester;
use App\Models\Tagihan;
use App\Models\User;

it('menolak pengajuan KRS jika mata kuliah prasyarat belum lulus minimal C', function () {
    $mahasiswaUser = User::factory()->create(['role' => 'mahasiswa']);
    $mahasiswa = Mahasiswa::factory()->create(['id_user' => $mahasiswaUser->id]);

    $matkulPrasyarat = Matkul::factory()->create();
    $matkulTarget = Matkul::factory()->create();
    MatkulPrasyarat::factory()->create(['id_matkul' => $matkulTarget->id, 'id_matkul_prasyarat' => $matkulPrasyarat->id]);

    $kelasTarget = Kelas::factory()->create();
    $kelasTarget->kurikulumMatkul->update(['id_matkul' => $matkulTarget->id]);

    // Mahasiswa belum pernah ambil/lulus matkul prasyarat sama sekali.
    $response = $this->actingAs($mahasiswaUser)->postJson('/api/krs/pengajuan', [
        'krs' => [['id_kelas' => $kelasTarget->id]],
    ]);

    $response->assertStatus(422)->assertJsonStructure(['message', 'prasyarat_tidak_terpenuhi']);
    expect(Krs::where('id_mahasiswa', $mahasiswa->id)->where('id_kelas', $kelasTarget->id)->exists())->toBeFalse();
});

it('mengizinkan pengajuan KRS setelah mata kuliah prasyarat lulus minimal C', function () {
    $mahasiswaUser = User::factory()->create(['role' => 'mahasiswa']);
    $mahasiswa = Mahasiswa::factory()->create(['id_user' => $mahasiswaUser->id]);

    $matkulPrasyarat = Matkul::factory()->create();
    $matkulTarget = Matkul::factory()->create();
    MatkulPrasyarat::factory()->create(['id_matkul' => $matkulTarget->id, 'id_matkul_prasyarat' => $matkulPrasyarat->id]);

    // Mahasiswa sudah lulus matkul prasyarat (nilai C, final) di kelas lain.
    $kelasPrasyarat = Kelas::factory()->create();
    $kelasPrasyarat->kurikulumMatkul->update(['id_matkul' => $matkulPrasyarat->id]);
    $krsLulus = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelasPrasyarat->id]);
    Nilai::factory()->create(['id_krs' => $krsLulus->id, 'huruf_mutu' => 'C', 'is_final' => true]);

    $kelasTarget = Kelas::factory()->create();
    $kelasTarget->kurikulumMatkul->update(['id_matkul' => $matkulTarget->id]);

    $response = $this->actingAs($mahasiswaUser)->postJson('/api/krs/pengajuan', [
        'krs' => [['id_kelas' => $kelasTarget->id]],
    ]);

    $response->assertCreated();
    expect(Krs::where('id_mahasiswa', $mahasiswa->id)->where('id_kelas', $kelasTarget->id)->exists())->toBeTrue();
});

it('menolak pengajuan KRS kalau persentase pelunasan tagihan di bawah syarat minimum', function () {
    $mahasiswaUser = User::factory()->create(['role' => 'mahasiswa']);
    $mahasiswa = Mahasiswa::factory()->create(['id_user' => $mahasiswaUser->id]);

    AturanAksesKeuangan::factory()->create(['kode_akses' => 'krs', 'persentase_minimum' => 75, 'status' => 'active']);
    Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'total' => 1000000,
        'tanggal_tagihan' => now()->subDay(),
    ]); // belum ada pembayaran sama sekali -> 0%

    $kelasTarget = Kelas::factory()->create();

    $response = $this->actingAs($mahasiswaUser)->postJson('/api/krs/pengajuan', [
        'krs' => [['id_kelas' => $kelasTarget->id]],
    ]);

    $response->assertStatus(422)->assertJsonStructure(['message', 'akses_keuangan']);
    expect(Krs::where('id_mahasiswa', $mahasiswa->id)->exists())->toBeFalse();
});

it('dosen wali hanya bisa approve KRS mahasiswa bimbingannya sendiri', function () {
    $dosenUser = User::factory()->create(['role' => 'dosen']);
    $dosen = Dosen::factory()->create(['id_user' => $dosenUser->id]);

    $mahasiswaLain = Mahasiswa::factory()->create(); // bukan bimbingan dosen ini
    $krsMahasiswaLain = Krs::factory()->create(['id_mahasiswa' => $mahasiswaLain->id]);

    $response = $this->actingAs($dosenUser)->postJson('/api/krs/approve', [
        'krs_ids' => [$krsMahasiswaLain->id],
    ]);

    $response->assertForbidden();
    expect(Krs::find($krsMahasiswaLain->id)->approved_at)->toBeNull();
});

it('approve KRS oleh dosen wali menyetujui KRS dan mengirim notifikasi ke mahasiswa', function () {
    $dosenUser = User::factory()->create(['role' => 'dosen']);
    $dosen = Dosen::factory()->create(['id_user' => $dosenUser->id]);

    $mahasiswaUser = User::factory()->create(['role' => 'mahasiswa']);
    $mahasiswa = Mahasiswa::factory()->create(['id_user' => $mahasiswaUser->id]);
    DosenWali::factory()->create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $mahasiswa->id, 'status' => 'active']);

    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id]);

    $response = $this->actingAs($dosenUser)->postJson('/api/krs/approve', [
        'krs_ids' => [$krs->id],
    ]);

    $response->assertOk()->assertJson(['approved_count' => 1]);
    expect(Krs::find($krs->id)->approved_at)->not->toBeNull();

    $notif = Notifikasi::where('id_user', $mahasiswaUser->id)->where('tipe', 'krs_disetujui')->first();
    expect($notif)->not->toBeNull();
});
