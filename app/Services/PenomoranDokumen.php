<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Penomoran dokumen keuangan: INV-YYYYMMDD-NNNN untuk tagihan, PAY-YYYYMMDD-NNNN untuk pembayaran.
 *
 * Aturan lamanya disalin ke enam tempat dan punya tiga cacat yang semuanya berujung pada nomor
 * kembar (ditangkap unique index jadi berupa error 500, bukan data rusak):
 *
 *  1. BALAPAN — membaca nomor terakhir lalu +1 tanpa penguncian, jadi dua permintaan bersamaan
 *     menghasilkan nomor yang sama. Sekarang MAX dibaca dengan lockForUpdate, sehingga permintaan
 *     lain untuk hari yang sama menunggu (InnoDB memasang gap lock pada rentang unique index-nya).
 *     Ini hanya berlaku kalau pemanggilnya berada di dalam transaksi — semua pemanggil sudah
 *     dipastikan begitu, termasuk unggah bukti oleh mahasiswa yang tadinya di luar transaksi.
 *
 *  2. PEMAKAIAN ULANG — query lama memakai Eloquent sehingga baris yang di-soft-delete tidak
 *     terlihat; nomor bekas baris terhapus dipakai lagi dan menabrak baris trashed yang masih
 *     memegang unique index. Di sini query langsung ke tabel, jadi baris terhapus ikut terhitung.
 *
 *  3. LUAP DI ATAS 9999 — nomor terakhir dicari dengan orderBy kolomnya (urutan teks), lalu
 *     dipotong substr(-4). Begitu menyentuh 10000, urutan teks menempatkan "9999" di atas
 *     "10000", dan potongan 4 digit terakhir "0000" mengembalikan hitungan ke 1. Sekarang MAX
 *     dihitung numerik atas seluruh sufiks, dan padding 4 digit hanya batas bawah — 10000 tetap
 *     ditulis utuh. Dengan generate multi-tahap (899 mahasiswa x beberapa tahap) batas ini
 *     memang terjangkau dalam satu hari.
 *
 * Catatan kinerja: satu query per nomor, sama seperti sebelumnya — hanya ditambah penguncian.
 * Untuk generate massal biayanya ikut jumlah tagihan; itu bagian dari temuan bottleneck 3.1,
 * bukan sesuatu yang diselesaikan di sini.
 */
final class PenomoranDokumen
{
    public static function tagihan(): string
    {
        return self::berikutnya('tagihan', 'no_tagihan', 'INV');
    }

    public static function pembayaran(): string
    {
        return self::berikutnya('pembayaran', 'no_pembayaran', 'PAY');
    }

    private static function berikutnya(string $tabel, string $kolom, string $awalan): string
    {
        return self::rakit($awalan, self::urutTerakhirHariIni($tabel, $kolom, $awalan) + 1);
    }

    /**
     * Nomor urut tertinggi yang sudah terpakai hari ini, dengan penguncian rentangnya.
     * Dipisah supaya SeriNomorDokumen bisa memakainya sekali lalu melanjutkan di memori.
     */
    public static function urutTerakhirHariIni(string $tabel, string $kolom, string $awalan): int
    {
        $prefix = self::prefix($awalan);
        $posisiSufiks = strlen($prefix) + 1;

        return (int) DB::table($tabel)
            ->where($kolom, 'like', $prefix.'%')
            ->lockForUpdate()
            ->selectRaw("COALESCE(MAX(CAST(SUBSTRING({$kolom}, {$posisiSufiks}) AS UNSIGNED)), 0) as maks")
            ->value('maks');
    }

    public static function rakit(string $awalan, int $urut): string
    {
        return self::prefix($awalan).str_pad((string) $urut, 4, '0', STR_PAD_LEFT);
    }

    private static function prefix(string $awalan): string
    {
        return $awalan.'-'.now()->format('Ymd').'-';
    }
}
