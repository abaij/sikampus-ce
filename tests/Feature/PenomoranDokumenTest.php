<?php

use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Services\PenomoranDokumen;
use Illuminate\Support\Facades\DB;

it('starts at 0001 for the first document of the day', function () {
    expect(PenomoranDokumen::tagihan())->toBe('INV-'.now()->format('Ymd').'-0001');
    expect(PenomoranDokumen::pembayaran())->toBe('PAY-'.now()->format('Ymd').'-0001');
});

it('continues from the highest number already used today', function () {
    $prefix = 'INV-'.now()->format('Ymd').'-';
    Tagihan::factory()->create(['no_tagihan' => $prefix.'0007']);

    expect(PenomoranDokumen::tagihan())->toBe($prefix.'0008');
});

it('ignores numbers belonging to another day', function () {
    Tagihan::factory()->create(['no_tagihan' => 'INV-'.now()->subDay()->format('Ymd').'-0042']);

    expect(PenomoranDokumen::tagihan())->toBe('INV-'.now()->format('Ymd').'-0001');
});

/**
 * Cacat 2 dari temuan 2.8: query lama memakai Eloquent, sehingga baris yang di-soft-delete tidak
 * terlihat dan nomornya dipakai ulang — padahal baris trashed masih memegang unique index.
 */
it('does not reuse a number held by a soft-deleted tagihan', function () {
    $prefix = 'INV-'.now()->format('Ymd').'-';
    $tagihan = Tagihan::factory()->create(['no_tagihan' => $prefix.'0001']);
    $tagihan->delete();

    expect(Tagihan::count())->toBe(0);
    expect(PenomoranDokumen::tagihan())->toBe($prefix.'0002');
});

it('does not reuse a number held by a soft-deleted pembayaran', function () {
    $prefix = 'PAY-'.now()->format('Ymd').'-';
    $pembayaran = Pembayaran::factory()->create(['no_pembayaran' => $prefix.'0001']);
    $pembayaran->delete();

    expect(PenomoranDokumen::pembayaran())->toBe($prefix.'0002');
});

/**
 * Cacat 3: nomor terakhir dicari dengan urutan teks lalu dipotong substr(-4). Begitu menyentuh
 * 10000, urutan teks menaruh "9999" di atas "10000" dan potongan "0000" mengembalikan hitungan
 * ke 1 — nomor kembar. Dengan generate multi-tahap batas 9999/hari memang terjangkau.
 */
it('keeps counting correctly past 9999 in a single day', function () {
    $prefix = 'INV-'.now()->format('Ymd').'-';
    Tagihan::factory()->create(['no_tagihan' => $prefix.'9999']);

    $berikutnya = PenomoranDokumen::tagihan();
    expect($berikutnya)->toBe($prefix.'10000');

    Tagihan::factory()->create(['no_tagihan' => $berikutnya]);
    expect(PenomoranDokumen::tagihan())->toBe($prefix.'10001');
});

it('picks the numeric maximum, not the lexicographic one', function () {
    $prefix = 'INV-'.now()->format('Ymd').'-';
    // Urutan teks menempatkan "9999" di atas "10000"; urutan numerik tidak.
    Tagihan::factory()->create(['no_tagihan' => $prefix.'9999']);
    Tagihan::factory()->create(['no_tagihan' => $prefix.'10000']);

    expect(PenomoranDokumen::tagihan())->toBe($prefix.'10001');
});

it('produces unique numbers for every document created in one run', function () {
    $nomor = [];
    DB::transaction(function () use (&$nomor) {
        for ($i = 0; $i < 25; $i++) {
            $no = PenomoranDokumen::tagihan();
            Tagihan::factory()->create(['no_tagihan' => $no]);
            $nomor[] = $no;
        }
    });

    expect($nomor)->toHaveCount(25);
    expect(array_unique($nomor))->toHaveCount(25);
    expect(Tagihan::whereIn('no_tagihan', $nomor)->count())->toBe(25);
});

it('keeps tagihan and pembayaran counters independent', function () {
    $hariIni = now()->format('Ymd');
    Tagihan::factory()->create(['no_tagihan' => 'INV-'.$hariIni.'-0050']);

    expect(PenomoranDokumen::pembayaran())->toBe('PAY-'.$hariIni.'-0001');
    expect(PenomoranDokumen::tagihan())->toBe('INV-'.$hariIni.'-0051');
});
