<?php

namespace App\Services;

/**
 * Deret nomor dokumen untuk satu proses massal (generate tagihan) di dalam SATU transaksi.
 *
 * PenomoranDokumen membaca MAX dengan lockForUpdate setiap kali dipanggil. Itu benar dan aman,
 * tapi untuk generate massal artinya satu query penguncian per tagihan — 5.400 query hanya untuk
 * mengambil nomor, dan semuanya mengunci rentang yang sama persis.
 *
 * Karena transaksi yang menjalankan generate sudah memegang gap lock atas rentang nomor hari itu
 * sejak pembacaan pertama, tidak ada proses lain yang bisa menyisipkan nomor di tengah jalan.
 * Jadi nomor berikutnya cukup dihitung di memori. Query penguncian tetap terjadi sekali, di awal.
 *
 * SATU INSTANS HANYA UNTUK SATU TRANSAKSI. Memakainya ulang lintas transaksi membuat hitungan di
 * memori bisa basi karena gap lock-nya sudah dilepas.
 */
final class SeriNomorDokumen
{
    private ?int $terakhir = null;

    private function __construct(
        private readonly string $tabel,
        private readonly string $kolom,
        private readonly string $awalan,
    ) {}

    public static function tagihan(): self
    {
        return new self('tagihan', 'no_tagihan', 'INV');
    }

    public static function pembayaran(): self
    {
        return new self('pembayaran', 'no_pembayaran', 'PAY');
    }

    public function berikutnya(): string
    {
        if ($this->terakhir === null) {
            $this->terakhir = PenomoranDokumen::urutTerakhirHariIni($this->tabel, $this->kolom, $this->awalan);
        }

        $this->terakhir++;

        return PenomoranDokumen::rakit($this->awalan, $this->terakhir);
    }
}
