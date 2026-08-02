<?php

namespace App\Services;

use App\Models\Semester;
use Carbon\CarbonImmutable;

/**
 * Menentukan tanggal tagihan & jatuh tempo untuk tiap tahap saat generate dari struktur biaya.
 *
 * Sebelumnya tanggalnya dihitung dari Carbon::now()->startOfMonth() — artinya hasilnya bergantung
 * pada KAPAN tombol generate ditekan, bukan pada periode yang ditagih. Di database ini akibatnya
 * terlihat jelas: 899 tagihan dari delapan semester berbeda (2021 Genap s/d 2025 Genap) semuanya
 * bertanggal 2026-04-17. Lebih buruk lagi, dua kolom input `tanggal_tagihan` dan
 * `tanggal_jatuh_tempo` sudah divalidasi tapi tidak pernah dipakai — operator mengisi tanggal,
 * sistem diam-diam mengabaikannya.
 *
 * Sekarang basis tanggalnya diambil berurutan:
 *   1. tanggal yang diisi operator (paling eksplisit, jadi menang atas apa pun);
 *   2. `semester.tanggal_mulai` milik periode yang ditagih;
 *   3. tidak ada — generate ditolak, bukan menebak dari jam dinding.
 *
 * Tahap ke-n digeser n-1 bulan dari basis, memakai addMonthsNoOverflow supaya basis tanggal 31
 * tidak melompat ke bulan berikutnya.
 */
final class JadwalTagihanTahap
{
    /**
     * Selisih default tanggal tagihan → jatuh tempo kalau operator tidak mengisi jatuh tempo.
     * Angkanya mempertahankan maksud aturan lama (tanggal 1 menagih, tanggal 15 jatuh tempo).
     */
    public const TENGGAT_HARI_DEFAULT = 14;

    public const SUMBER_INPUT = 'input';

    public const SUMBER_PERIODE = 'periode';

    private function __construct(
        private readonly CarbonImmutable $baseTagihan,
        private readonly CarbonImmutable $baseJatuhTempo,
        public readonly string $sumber,
    ) {}

    /**
     * Pesan tunggal untuk kedua pemanggil (API & panel) saat basis tanggal tidak bisa ditentukan.
     */
    public static function pesanTanggalTidakDiketahui(): string
    {
        return 'Tanggal tagihan tidak dapat ditentukan: periode yang dipilih belum punya tanggal mulai. '
            .'Isi tanggal tagihan pada form, atau lengkapi tanggal mulai semester tersebut.';
    }

    public static function resolve(
        ?Semester $periode,
        ?string $tanggalTagihan = null,
        ?string $tanggalJatuhTempo = null,
    ): ?self {
        $sumber = self::SUMBER_INPUT;
        $base = self::parse($tanggalTagihan);

        if (! $base) {
            $base = $periode?->tanggal_mulai
                ? CarbonImmutable::parse($periode->tanggal_mulai)->startOfDay()
                : null;
            $sumber = self::SUMBER_PERIODE;
        }

        if (! $base) {
            return null;
        }

        $jatuhTempo = self::parse($tanggalJatuhTempo)
            ?? $base->addDays(self::TENGGAT_HARI_DEFAULT);

        // Jatuh tempo yang lebih awal dari tanggal tagihan tidak masuk akal; kalau operator
        // mengisi begitu, validasi request yang menolaknya lebih dulu — ini jaring pengaman
        // untuk basis yang berasal dari data semester.
        if ($jatuhTempo->lessThan($base)) {
            $jatuhTempo = $base->addDays(self::TENGGAT_HARI_DEFAULT);
        }

        return new self($base, $jatuhTempo, $sumber);
    }

    /**
     * @return array{tanggal_tagihan: string, tanggal_jatuh_tempo: string}
     */
    public function untukTahap(int $tahap): array
    {
        $geser = max(0, $tahap - 1);

        return [
            'tanggal_tagihan' => $this->baseTagihan->addMonthsNoOverflow($geser)->toDateString(),
            'tanggal_jatuh_tempo' => $this->baseJatuhTempo->addMonthsNoOverflow($geser)->toDateString(),
        ];
    }

    /** Kalimat ringkas untuk pratinjau jadwal di layar. */
    public function ringkasanTahap(int $tahap): string
    {
        $jadwal = $this->untukTahap($tahap);
        $tagihan = CarbonImmutable::parse($jadwal['tanggal_tagihan'])->translatedFormat('d F Y');
        $tempo = CarbonImmutable::parse($jadwal['tanggal_jatuh_tempo'])->translatedFormat('d F Y');

        return "Tahap {$tahap}: Tanggal tagihan {$tagihan} • Jatuh tempo {$tempo}.";
    }

    public function keteranganSumber(): string
    {
        return $this->sumber === self::SUMBER_INPUT
            ? 'Dihitung dari tanggal yang Anda isi.'
            : 'Dihitung dari tanggal mulai periode yang ditagih.';
    }

    private static function parse(?string $tanggal): ?CarbonImmutable
    {
        $tanggal = $tanggal !== null ? trim($tanggal) : '';

        return $tanggal !== '' ? CarbonImmutable::parse($tanggal)->startOfDay() : null;
    }
}
