<?php

use App\Models\AturanAksesKeuangan;
use App\Models\KeringananBiaya;
use App\Models\Mahasiswa;
use App\Models\Pembayaran;
use App\Models\Semester;
use App\Models\Tagihan;
use App\Services\KeuanganAksesMahasiswaService;

it('mengizinkan akses secara default kalau tidak ada aturan akses keuangan untuk kode tersebut', function () {
    $mahasiswa = Mahasiswa::factory()->create();

    $result = KeuanganAksesMahasiswaService::canAccessByKode($mahasiswa->id, 'krs');

    expect($result['allowed'])->toBeTrue();
    expect($result['persentase_minimum_required'])->toBeNull();
    expect($result['aturan'])->toBeNull();
});

it('menolak akses kalau persentase pembayaran di bawah syarat minimum aturan', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    AturanAksesKeuangan::factory()->create(['kode_akses' => 'krs', 'persentase_minimum' => 75, 'status' => 'active']);

    $tagihan = Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id, 'total' => 1000000, 'tanggal_tagihan' => now()->subDay(),
    ]);
    Pembayaran::factory()->create([
        'id_tagihan' => $tagihan->id, 'nominal' => 300000, 'approved_at' => now(),
    ]); // 30% -> di bawah 75%

    $result = KeuanganAksesMahasiswaService::canAccessByKode($mahasiswa->id, 'krs');

    expect($result['allowed'])->toBeFalse();
    expect($result['persentase_pembayaran'])->toBe(30.0);
    expect($result['persentase_minimum_required'])->toBe(75.0);
});

it('mengizinkan akses kalau persentase pembayaran memenuhi syarat minimum aturan', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    AturanAksesKeuangan::factory()->create(['kode_akses' => 'krs', 'persentase_minimum' => 75, 'status' => 'active']);

    $tagihan = Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id, 'total' => 1000000, 'tanggal_tagihan' => now()->subDay(),
    ]);
    Pembayaran::factory()->create([
        'id_tagihan' => $tagihan->id, 'nominal' => 800000, 'approved_at' => now(),
    ]); // 80% -> memenuhi syarat

    $result = KeuanganAksesMahasiswaService::canAccessByKode($mahasiswa->id, 'krs');

    expect($result['allowed'])->toBeTrue();
    expect($result['persentase_pembayaran'])->toBe(80.0);
});

it('pembayaran yang belum di-approve tidak dihitung dalam persentase pelunasan', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    AturanAksesKeuangan::factory()->create(['kode_akses' => 'krs', 'persentase_minimum' => 75, 'status' => 'active']);

    $tagihan = Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id, 'total' => 1000000, 'tanggal_tagihan' => now()->subDay(),
    ]);
    Pembayaran::factory()->create([
        'id_tagihan' => $tagihan->id, 'nominal' => 900000, 'approved_at' => null,
    ]); // besar tapi belum di-ACC admin

    $result = KeuanganAksesMahasiswaService::canAccessByKode($mahasiswa->id, 'krs');

    expect($result['persentase_pembayaran'])->toBe(0.0);
    expect($result['allowed'])->toBeFalse();
});

it('pembayaran lebih besar dari total satu tagihan tidak membuat persentase melebihi 100% dari tagihan itu', function () {
    $mahasiswa = Mahasiswa::factory()->create();

    $tagihan = Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id, 'total' => 500000, 'tanggal_tagihan' => now()->subDay(),
    ]);
    // Overpay -> harus di-cap ke 500000, bukan 700000.
    Pembayaran::factory()->create(['id_tagihan' => $tagihan->id, 'nominal' => 700000, 'approved_at' => now()]);

    $result = KeuanganAksesMahasiswaService::canAccessByKode($mahasiswa->id, 'krs');

    expect($result['total_terbayar_disetujui'])->toBe(500000.0);
    expect($result['persentase_pembayaran'])->toBe(100.0);
});

it('tagihan yang tanggal_tagihan-nya belum berlaku (masa depan) tidak ikut dihitung', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    AturanAksesKeuangan::factory()->create(['kode_akses' => 'krs', 'persentase_minimum' => 75, 'status' => 'active']);

    Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id, 'total' => 1000000, 'tanggal_tagihan' => now()->addMonth(),
    ]);

    $result = KeuanganAksesMahasiswaService::canAccessByKode($mahasiswa->id, 'krs');

    // Tidak ada tagihan berlaku sama sekali -> dianggap 100% (tidak ada kewajiban jatuh tempo).
    expect($result['jumlah_tagihan_berlaku'])->toBe(0);
    expect($result['persentase_pembayaran'])->toBe(100.0);
    expect($result['allowed'])->toBeTrue();
});

it('mengizinkan akses lewat keringanan biaya yang disetujui meski persentase belum memenuhi syarat', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $semesterAktif = Semester::factory()->create(['is_active' => true]);
    $aturan = AturanAksesKeuangan::factory()->create(['kode_akses' => 'krs', 'persentase_minimum' => 75, 'status' => 'active']);

    $tagihan = Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id, 'total' => 1000000, 'tanggal_tagihan' => now()->subDay(),
    ]);
    Pembayaran::factory()->create(['id_tagihan' => $tagihan->id, 'nominal' => 100000, 'approved_at' => now()]); // 10%, jauh di bawah syarat

    KeringananBiaya::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semesterAktif->id,
        'id_aturan_akses_keuangan' => $aturan->id,
        'status' => 'approved',
    ]);

    $result = KeuanganAksesMahasiswaService::canAccessByKode($mahasiswa->id, 'krs', $semesterAktif->id);

    expect($result['allowed'])->toBeTrue();
    expect($result['allowed_via_keringanan_biaya'])->toBeTrue();
});
