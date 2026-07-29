<?php

namespace App\Services;

use App\Models\Pembayaran;
use App\Models\Tagihan;
use Illuminate\Database\Eloquent\Builder;

/**
 * Satu-satunya definisi "sudah lunas atau belum" untuk sebuah tagihan.
 *
 * Sebelumnya ada dua sumber kebenaran yang jalan bersamaan: kolom `tagihan.status` (dibaca
 * dashboard) dan status hasil hitung dari pembayaran yang disetujui (dipakai halaman tagihan).
 * Keduanya tidak pernah direkonsiliasi, sehingga baris yang sama bisa tampil "Lunas" di satu
 * halaman dan terhitung "Belum Lunas" di halaman lain — nyata terjadi pada 74 tagihan bernilai
 * Rp0 yang kolomnya tidak pernah diperbarui karena tak pernah ada peristiwa pembayaran.
 *
 * Sekarang status pelunasan selalu diturunkan dari fakta: pembayaran yang sudah disetujui
 * ditambah kredit keringanan yang sudah disetujui. Kolom `tagihan.status` tidak lagi dipakai
 * untuk menjawab "lunas atau belum" — perannya tinggal satu, yaitu menandai tagihan yang
 * dinyatakan kedaluwarsa secara administratif, yang memang bukan fakta pembayaran.
 *
 * Rumusnya ditulis dua kali (PHP dan SQL) karena daftar & agregat perlu menyaring di database,
 * sementara baris yang sudah dimuat dihitung di PHP. Keduanya wajib memberi jawaban identik —
 * dijaga oleh tes di tests/Feature/StatusPembayaranTagihanTest.php.
 */
class StatusPembayaranTagihan
{
    public const LUNAS = 'lunas';

    public const DIBAYAR_SEBAGIAN = 'dibayar_sebagian';

    public const BELUM_BAYAR = 'belum_bayar';

    public const KEDALUWARSA = 'kedaluwarsa';

    /**
     * Toleransi pembulatan setengah sen. Nominal disimpan decimal(_,2) sehingga selisih sekecil
     * ini tidak mungkin muncul dari data — gunanya menghindari perbandingan float telanjang.
     *
     * Publik supaya Tagihan::lunasMenurutPembayaranDisetujui() (aturan untuk kolom `status`,
     * yang sengaja tanpa keringanan) memakai ambang yang sama dan tidak bisa menyimpang.
     */
    public const TOLERANSI = 0.009;

    public static function hitung(Tagihan $tagihan, float $terbayarDisetujui, float $kreditKeringanan = 0.0): string
    {
        $tertutup = $terbayarDisetujui + $kreditKeringanan;

        if ($tertutup + self::TOLERANSI >= (float) $tagihan->total) {
            return self::LUNAS;
        }
        if ($tertutup > 0) {
            return self::DIBAYAR_SEBAGIAN;
        }
        if ($tagihan->status === 'expired') {
            return self::KEDALUWARSA;
        }

        return self::BELUM_BAYAR;
    }

    /**
     * Ekspresi SQL yang mengembalikan salah satu konstanta di atas untuk satu baris tagihan.
     * Urutan cabangnya harus sama persis dengan hitung().
     */
    public static function sqlEkspresi(string $alias = 'tagihan'): string
    {
        $tertutup = '('.Pembayaran::sqlSumDisetujui($alias).' + '.KeringananBiayaKreditService::sqlKreditTagihan($alias).')';
        $toleransi = self::TOLERANSI;

        return "(CASE
            WHEN {$tertutup} + {$toleransi} >= {$alias}.total THEN '".self::LUNAS."'
            WHEN {$tertutup} > 0 THEN '".self::DIBAYAR_SEBAGIAN."'
            WHEN {$alias}.status = 'expired' THEN '".self::KEDALUWARSA."'
            ELSE '".self::BELUM_BAYAR."'
        END)";
    }

    public static function label(string $status): string
    {
        return self::opsi()[$status] ?? $status;
    }

    /**
     * Label ringkas untuk chip/ringkasan (dashboard), tanpa embel-embel penjelas.
     *
     * @return array<string, string>
     */
    public static function opsiRingkas(): array
    {
        return [
            self::LUNAS => 'Lunas',
            self::DIBAYAR_SEBAGIAN => 'Dibayar sebagian',
            self::BELUM_BAYAR => 'Belum bayar',
            self::KEDALUWARSA => 'Kedaluwarsa',
        ];
    }

    /**
     * Label lengkap untuk dropdown filter, yang perlu menyebut dasar penilaiannya.
     *
     * @return array<string, string>
     */
    public static function opsi(): array
    {
        return [
            self::LUNAS => 'Lunas (disetujui penuh)',
            self::DIBAYAR_SEBAGIAN => 'Dibayar sebagian (ACC)',
            self::BELUM_BAYAR => 'Belum ada pembayaran disetujui',
            self::KEDALUWARSA => 'Kedaluwarsa (belum lunas)',
        ];
    }

    /**
     * Jumlah tagihan per status turunan, dalam satu query. Dipakai ringkasan dashboard supaya
     * angkanya berasal dari rumus yang sama dengan daftar tagihan.
     *
     * @return array<string, int>
     */
    public static function hitungPerStatus(Builder $query): array
    {
        $ekspresi = self::sqlEkspresi();

        $hasil = $query
            ->selectRaw("{$ekspresi} as status_acc, COUNT(*) as jumlah")
            ->groupBy('status_acc')
            ->pluck('jumlah', 'status_acc');

        return collect(array_keys(self::opsiRingkas()))
            ->mapWithKeys(fn (string $status) => [$status => (int) ($hasil[$status] ?? 0)])
            ->all();
    }
}
