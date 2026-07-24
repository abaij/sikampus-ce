<?php

namespace App\Services;

use App\Models\AturanAksesKeuangan;
use App\Models\KeringananBiaya;
use App\Models\Pembayaran;
use App\Models\Semester;
use App\Models\Tagihan;
use Illuminate\Support\Carbon;

class KeuanganAksesMahasiswaService
{
    /**
     * Tagihan yang sudah berlaku: tanggal_tagihan <= hari ini (sama seperti tagihan-saya).
     * Persentase = total pembayaran disetujui (dibatasi per tagihan tidak melebihi total tagihan) / total kewajiban.
     */
    public static function paymentPercentForBerlakuTagihan(int $mahasiswaId): array
    {
        $tagihanList = Tagihan::query()
            ->where('id_mahasiswa', $mahasiswaId)
            ->whereNull('deleted_at')
            ->whereNotNull('tanggal_tagihan')
            ->whereDate('tanggal_tagihan', '<=', Carbon::today())
            ->get(['id', 'total']);

        $totalKewajiban = (float) $tagihanList->sum(fn ($t) => (float) $t->total);
        $totalTerbayarAcc = 0.0;

        foreach ($tagihanList as $t) {
            $paid = (float) Pembayaran::approvedQueryForTagihan((int) $t->id)->sum('nominal');
            $cap = (float) $t->total;
            $totalTerbayarAcc += min($paid, $cap);
        }

        $persentase = $totalKewajiban > 0
            ? round(100 * $totalTerbayarAcc / $totalKewajiban, 2)
            : 100.0;

        return [
            'total_tagihan_berlaku' => $totalKewajiban,
            'total_terbayar_disetujui' => $totalTerbayarAcc,
            'persentase_pembayaran' => $persentase,
            'jumlah_tagihan_berlaku' => $tagihanList->count(),
        ];
    }

    /**
     * Keringanan biaya disetujui untuk semester & aturan akses yang sama → boleh melewati cek persentase pembayaran
     * (id_aturan kosong pada pengajuan mahasiswa tetap dianggap relevan jika semester cocok).
     *
     * @return array{allowed: bool, allowed_via_keringanan_biaya: bool, persentase_pembayaran: float, persentase_minimum_required: ?float, total_tagihan_berlaku: float, total_terbayar_disetujui: float, jumlah_tagihan_berlaku: int, aturan: ?array{nama: ?string, kode_akses: string}}
     */
    public static function canAccessByKode(int $mahasiswaId, string $kodeAkses, ?int $idSemester = null): array
    {
        $metrics = self::paymentPercentForBerlakuTagihan($mahasiswaId);

        $aturan = AturanAksesKeuangan::query()
            ->where('kode_akses', $kodeAkses)
            ->where('status', 'active')
            ->first();

        if (! $aturan || $aturan->persentase_minimum === null) {
            return array_merge($metrics, [
                'allowed' => true,
                'persentase_minimum_required' => null,
                'aturan' => null,
                'allowed_via_keringanan_biaya' => false,
            ]);
        }

        $min = (float) $aturan->persentase_minimum;
        $allowed = $metrics['persentase_pembayaran'] >= $min;
        $allowedViaKb = false;

        if (! $allowed) {
            $semId = $idSemester ?? Semester::query()->where('is_active', true)->value('id');
            if ($semId && self::mahasiswaHasApprovedKeringananForSemesterAndAturan(
                $mahasiswaId,
                (int) $semId,
                (int) $aturan->id
            )) {
                $allowed = true;
                $allowedViaKb = true;
            }
        }

        return array_merge($metrics, [
            'allowed' => $allowed,
            'persentase_minimum_required' => $min,
            'aturan' => [
                'nama' => $aturan->nama,
                'kode_akses' => $aturan->kode_akses,
            ],
            'allowed_via_keringanan_biaya' => $allowedViaKb,
        ]);
    }

    private static function mahasiswaHasApprovedKeringananForSemesterAndAturan(
        int $mahasiswaId,
        int $idSemester,
        int $idAturanKrs
    ): bool {
        return KeringananBiaya::query()
            ->where('id_mahasiswa', $mahasiswaId)
            ->where('id_semester', $idSemester)
            ->where('status', 'approved')
            ->where(function ($q) use ($idAturanKrs): void {
                $q->whereNull('id_aturan_akses_keuangan')
                    ->orWhere('id_aturan_akses_keuangan', $idAturanKrs);
            })
            ->exists();
    }
}
