<?php

use App\Models\BobotPenilaian;
use App\Models\Dosen;
use App\Models\JenisPenilaian;
use App\Models\Jenjang;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Nilai;
use App\Models\Notifikasi;
use App\Models\Prodi;
use App\Models\RentangNilai;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Standar rentang nilai jenjang dipakai berulang di beberapa test: A 85-100 (4),
 * B 70-84.99 (3), C 55-69.99 (2), D 40-54.99 (1), E 0-39.99 (0).
 */
function buatRentangNilaiStandar(Jenjang $jenjang): void
{
    RentangNilai::factory()->create(['id_jenjang' => $jenjang->id, 'nilai_huruf' => 'A', 'nilai_angka' => 4, 'nilai_rendah' => 85, 'nilai_tinggi' => 100]);
    RentangNilai::factory()->create(['id_jenjang' => $jenjang->id, 'nilai_huruf' => 'B', 'nilai_angka' => 3, 'nilai_rendah' => 70, 'nilai_tinggi' => 84.99]);
    RentangNilai::factory()->create(['id_jenjang' => $jenjang->id, 'nilai_huruf' => 'C', 'nilai_angka' => 2, 'nilai_rendah' => 55, 'nilai_tinggi' => 69.99]);
    RentangNilai::factory()->create(['id_jenjang' => $jenjang->id, 'nilai_huruf' => 'D', 'nilai_angka' => 1, 'nilai_rendah' => 40, 'nilai_tinggi' => 54.99]);
    RentangNilai::factory()->create(['id_jenjang' => $jenjang->id, 'nilai_huruf' => 'E', 'nilai_angka' => 0, 'nilai_rendah' => 0, 'nilai_tinggi' => 39.99]);
}

/**
 * Catatan: kolom `id_dosen` pada nilai_komponen ternyata NOT NULL di database sungguhan
 * meski migration-nya menulis ->nullable() (urutan chain constrained()->nullable() membuat
 * nullable() menempel ke definisi foreign key, bukan ke kolomnya — jadi diabaikan silang oleh
 * grammar). Makanya di sini id_dosen wajib diisi, mengikuti perilaku schema yang sebenarnya.
 */
function isiNilaiKomponen(Krs $krs, JenisPenilaian $jenis, float $nilai, Dosen $dosen): void
{
    DB::table('nilai_komponen')->insert([
        'id_krs' => $krs->id,
        'id_jenis_penilaian' => $jenis->id,
        'nilai' => $nilai,
        'id_dosen' => $dosen->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** Siapkan satu kelas lengkap (jenjang, prodi, kurikulum matkul, dosen PIC) siap dipakai kalkulasi/finalisasi. */
function siapkanKelasUntukNilai(): array
{
    $jenjang = Jenjang::factory()->create();
    $prodi = Prodi::factory()->create(['id_jenjang' => $jenjang->id]);
    $dosenUser = User::factory()->create(['role' => 'dosen']);
    $dosen = Dosen::factory()->create(['id_user' => $dosenUser->id]);
    $kelas = Kelas::factory()->create(['id_prodi' => $prodi->id, 'id_dosen_pic' => $dosen->id]);

    buatRentangNilaiStandar($jenjang);

    return compact('jenjang', 'prodi', 'dosenUser', 'dosen', 'kelas');
}

it('menghitung nilai akhir berbasis bobot jenis penilaian default', function () {
    ['dosenUser' => $dosenUser, 'dosen' => $dosen, 'kelas' => $kelas] = siapkanKelasUntukNilai();

    $uts = JenisPenilaian::factory()->create(['bobot' => 40]);
    $uas = JenisPenilaian::factory()->create(['bobot' => 60]);

    $mahasiswa = Mahasiswa::factory()->create();
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id]);
    isiNilaiKomponen($krs, $uts, 80, $dosen);
    isiNilaiKomponen($krs, $uas, 90, $dosen);

    // (80*40 + 90*60) / 100 = 86 -> rentang A (85-100)
    $response = $this->actingAs($dosenUser)
        ->postJson("/api/nilai/kelas/{$kelas->id}/kalkulasi-akhir");

    $response->assertOk()
        ->assertJson(['success_count' => 1, 'error_count' => 0]);

    $nilai = Nilai::where('id_krs', $krs->id)->first();
    expect($nilai)->not->toBeNull();
    expect((float) $nilai->angka_mutu)->toBe(4.0);
    expect($nilai->huruf_mutu)->toBe('A');
});

it('memprioritaskan bobot khusus mata kuliah (bobot_penilaian) dibanding bobot default jenis penilaian', function () {
    ['dosenUser' => $dosenUser, 'dosen' => $dosen, 'kelas' => $kelas] = siapkanKelasUntukNilai();

    // Bobot default sengaja dibuat salah (90/10) — mata kuliah ini override jadi 40/60.
    $uts = JenisPenilaian::factory()->create(['bobot' => 90]);
    $uas = JenisPenilaian::factory()->create(['bobot' => 10]);

    $idKurikulumMatkul = $kelas->id_kurikulum_matkul;
    BobotPenilaian::factory()->create(['id_kurikulum_matkul' => $idKurikulumMatkul, 'id_jenis_penilaian' => $uts->id, 'bobot' => 40]);
    BobotPenilaian::factory()->create(['id_kurikulum_matkul' => $idKurikulumMatkul, 'id_jenis_penilaian' => $uas->id, 'bobot' => 60]);

    $mahasiswa = Mahasiswa::factory()->create();
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id]);
    isiNilaiKomponen($krs, $uts, 80, $dosen);
    isiNilaiKomponen($krs, $uas, 90, $dosen);

    // Kalau bobot default (90/10) yang dipakai, hasilnya (80*90+90*10)/100=81 -> B.
    // Kalau bobot override (40/60) yang dipakai (yang benar), hasilnya 86 -> A.
    $this->actingAs($dosenUser)
        ->postJson("/api/nilai/kelas/{$kelas->id}/kalkulasi-akhir")
        ->assertOk()
        ->assertJson(['success_count' => 1, 'error_count' => 0]);

    $nilai = Nilai::where('id_krs', $krs->id)->first();
    expect($nilai->huruf_mutu)->toBe('A');
});

it('tidak menghitung nilai KRS yang belum lengkap semua jenis penilaian', function () {
    ['dosenUser' => $dosenUser, 'dosen' => $dosen, 'kelas' => $kelas] = siapkanKelasUntukNilai();

    $uts = JenisPenilaian::factory()->create(['bobot' => 40]);
    JenisPenilaian::factory()->create(['bobot' => 60]); // UAS — sengaja tidak diisi

    $mahasiswa = Mahasiswa::factory()->create();
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id]);
    isiNilaiKomponen($krs, $uts, 80, $dosen);

    $response = $this->actingAs($dosenUser)
        ->postJson("/api/nilai/kelas/{$kelas->id}/kalkulasi-akhir");

    $response->assertOk()
        ->assertJson(['success_count' => 0, 'error_count' => 1]);

    expect(Nilai::where('id_krs', $krs->id)->exists())->toBeFalse();
});

it('menolak kalkulasi nilai oleh dosen yang bukan PIC dan tidak punya jadwal di kelas tersebut', function () {
    ['kelas' => $kelas] = siapkanKelasUntukNilai();

    $dosenLainUser = User::factory()->create(['role' => 'dosen']);
    Dosen::factory()->create(['id_user' => $dosenLainUser->id]);

    $this->actingAs($dosenLainUser)
        ->postJson("/api/nilai/kelas/{$kelas->id}/kalkulasi-akhir")
        ->assertForbidden();
});

it('finalisasi nilai menandai is_final dan mengirim notifikasi ke mahasiswa', function () {
    ['dosenUser' => $dosenUser, 'dosen' => $dosen, 'kelas' => $kelas] = siapkanKelasUntukNilai();

    $mahasiswaUser = User::factory()->create(['role' => 'mahasiswa']);
    $mahasiswa = Mahasiswa::factory()->create(['id_user' => $mahasiswaUser->id]);
    $krs = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id]);
    Nilai::factory()->create(['id_krs' => $krs->id, 'is_final' => null, 'huruf_mutu' => 'A', 'angka_mutu' => 4]);

    expect(Notifikasi::where('id_user', $mahasiswaUser->id)->count())->toBe(0);

    $response = $this->actingAs($dosenUser)
        ->postJson("/api/nilai/kelas/{$kelas->id}/finalize");

    $response->assertOk()->assertJson(['updated_count' => 1]);

    $nilai = Nilai::where('id_krs', $krs->id)->first();
    expect((bool) $nilai->is_final)->toBeTrue();

    $notif = Notifikasi::where('id_user', $mahasiswaUser->id)->where('tipe', 'nilai_final')->first();
    expect($notif)->not->toBeNull();
    expect($notif->dibaca_pada)->toBeNull();
});
