<?php

namespace App\Services;

use App\Models\KeringananBiaya;
use App\Models\Pembayaran;
use App\Models\Tagihan;
use Illuminate\Support\Collection;

/**
 * Keringanan biaya sebagai lapisan kredit atas tagihan.
 *
 * Sebelumnya `keringanan_biaya.nominal` hanya tersimpan dan dipakai sebagai bypass boolean di
 * KeuanganAksesMahasiswaService — tidak pernah mengurangi tagihan, piutang, maupun persentase
 * pelunasan. Service ini menjadikannya pengurang kewajiban di seluruh titik hitung, tanpa
 * memutasi baris tagihan/rincian: mencabut approval cukup membuat angkanya kembali sendiri.
 *
 * Aturan alokasi — PROPORSIONAL terhadap sisa tiap tagihan dalam satu (mahasiswa, semester):
 *
 *   sisa(T)   = maks(0, total(T) − pembayaran disetujui(T))
 *   kredit    = Σ nominal keringanan berstatus approved pada (mahasiswa, semester)
 *   rasio     = min(1, kredit / Σ sisa(T))
 *   kredit(T) = sisa(T) × rasio
 *
 * Dipilih proporsional, bukan berurutan-per-tahap, karena: (a) tidak bergantung pada urutan
 * sehingga tidak ada bias tahap mana yang "dilunasi duluan"; (b) cocok untuk keringanan
 * bertipe persentase; dan (c) bisa ditulis sebagai satu ekspresi SQL, sehingga filter status
 * di daftar tagihan memakai rumus yang sama persis dengan label yang tampil di barisnya.
 *
 * Konsekuensi yang disengaja: karena rasionya sama untuk semua tagihan dalam satu semester,
 * kredit membuat seluruh tagihan semester itu lunas sekaligus (rasio 1) atau tidak sama sekali.
 * Kelebihan kredit di atas total sisa tidak menghasilkan restitusi — sisa kredit hangus.
 *
 * Keringanan yang tidak berstatus `approved` tidak pernah dihitung, sejalan dengan kaidah
 * "hanya pembayaran disetujui yang mengurangi sisa" (Pembayaran::approvedQueryForTagihan).
 */
class KeringananBiayaKreditService
{
    /**
     * Ekspresi SQL kredit keringanan untuk satu baris tagihan, dipakai di query daftar/filter.
     *
     * EXISTS di depan bukan optimasi kosmetik: mayoritas mahasiswa tidak punya keringanan sama
     * sekali, dan cek itu memakai index (id_mahasiswa) sehingga subquery bersarang di cabang
     * ELSE tidak pernah dijalankan untuk mereka.
     */
    public static function sqlKreditTagihan(string $alias = 'tagihan'): string
    {
        $kredit = "(SELECT COALESCE(SUM(kb.nominal), 0) FROM keringanan_biaya kb
            WHERE kb.id_mahasiswa = {$alias}.id_mahasiswa
              AND kb.id_semester = {$alias}.id_semester
              AND kb.status = 'approved'
              AND kb.deleted_at IS NULL)";

        $sisaBaris = "GREATEST(0, {$alias}.total - ".Pembayaran::sqlSumDisetujui($alias).')';

        $sisaSemester = '(SELECT COALESCE(SUM(GREATEST(0, t2.total - '.Pembayaran::sqlSumDisetujui('t2').")), 0)
            FROM tagihan t2
            WHERE t2.id_mahasiswa = {$alias}.id_mahasiswa
              AND t2.id_semester = {$alias}.id_semester
              AND t2.deleted_at IS NULL)";

        return "(CASE WHEN NOT EXISTS (SELECT 1 FROM keringanan_biaya kb2
                    WHERE kb2.id_mahasiswa = {$alias}.id_mahasiswa
                      AND kb2.id_semester = {$alias}.id_semester
                      AND kb2.status = 'approved'
                      AND kb2.deleted_at IS NULL)
                THEN 0
                ELSE {$sisaBaris} * LEAST(1, {$kredit} / NULLIF({$sisaSemester}, 0))
            END)";
    }

    /** Total keringanan disetujui pada satu (mahasiswa, semester). */
    public static function kreditSemester(int $mahasiswaId, int $semesterId): float
    {
        return (float) KeringananBiaya::query()
            ->where('id_mahasiswa', $mahasiswaId)
            ->where('id_semester', $semesterId)
            ->where('status', 'approved')
            ->sum('nominal');
    }

    /** Kredit yang teralokasi ke satu tagihan. */
    public static function kreditUntukTagihan(Tagihan $tagihan): float
    {
        return self::kreditUntukTagihanIds([(int) $tagihan->id])[(int) $tagihan->id] ?? 0.0;
    }

    /**
     * Versi massal untuk daftar & laporan: tiga query, berapa pun jumlah barisnya.
     *
     * Alokasi butuh seluruh tagihan pada (mahasiswa, semester) yang sama — bukan hanya id yang
     * diminta — karena rasionya dihitung dari total sisa satu semester.
     *
     * @param  array<int>  $tagihanIds
     * @return array<int, float> id_tagihan => kredit
     */
    public static function kreditUntukTagihanIds(array $tagihanIds): array
    {
        $tagihanIds = array_values(array_unique(array_map('intval', $tagihanIds)));
        if ($tagihanIds === []) {
            return [];
        }

        $diminta = Tagihan::query()
            ->whereIn('id', $tagihanIds)
            ->get(['id', 'id_mahasiswa', 'id_semester']);

        if ($diminta->isEmpty()) {
            return [];
        }

        $pasangan = $diminta
            ->map(fn ($t) => (int) $t->id_mahasiswa.':'.(int) $t->id_semester)
            ->unique()
            ->flip();

        // whereIn silang (bukan row constructor) supaya tetap satu query sederhana dan portabel;
        // kombinasi yang tidak diminta dibuang lagi di PHP lewat $pasangan.
        $semua = Tagihan::query()
            ->whereIn('id_mahasiswa', $diminta->pluck('id_mahasiswa')->unique()->all())
            ->whereIn('id_semester', $diminta->pluck('id_semester')->unique()->all())
            ->get(['id', 'id_mahasiswa', 'id_semester', 'total'])
            ->filter(fn ($t) => $pasangan->has((int) $t->id_mahasiswa.':'.(int) $t->id_semester))
            ->values();

        $terbayar = Pembayaran::query()
            ->whereIn('id_tagihan', $semua->pluck('id')->all())
            ->whereNotNull('approved_at')
            ->selectRaw('id_tagihan, SUM(nominal) as total')
            ->groupBy('id_tagihan')
            ->pluck('total', 'id_tagihan');

        $kreditPasangan = KeringananBiaya::query()
            ->whereIn('id_mahasiswa', $diminta->pluck('id_mahasiswa')->unique()->all())
            ->whereIn('id_semester', $diminta->pluck('id_semester')->unique()->all())
            ->where('status', 'approved')
            ->selectRaw('id_mahasiswa, id_semester, SUM(nominal) as total')
            ->groupBy('id_mahasiswa', 'id_semester')
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->id_mahasiswa.':'.(int) $r->id_semester => (float) $r->total]);

        $hasil = array_fill_keys($tagihanIds, 0.0);

        $semua->groupBy(fn ($t) => (int) $t->id_mahasiswa.':'.(int) $t->id_semester)
            ->each(function (Collection $grup, string $kunci) use ($kreditPasangan, $terbayar, &$hasil): void {
                $kredit = $kreditPasangan->get($kunci, 0.0);
                if ($kredit <= 0) {
                    return;
                }

                $sisaPer = $grup->mapWithKeys(fn ($t) => [
                    (int) $t->id => max(0.0, (float) $t->total - (float) ($terbayar[$t->id] ?? 0)),
                ]);

                $sisaTotal = (float) $sisaPer->sum();
                if ($sisaTotal <= 0) {
                    return;
                }

                $rasio = min(1.0, $kredit / $sisaTotal);

                foreach ($sisaPer as $id => $sisa) {
                    if (array_key_exists($id, $hasil)) {
                        $hasil[$id] = round($sisa * $rasio, 2);
                    }
                }
            });

        return $hasil;
    }

    /**
     * Kredit per mahasiswa untuk laporan agregat (Laporan Pelunasan). Di level ini tidak perlu
     * alokasi per tagihan — cukup dibatasi agar tidak melebihi kewajiban yang belum terbayar,
     * supaya persentase pelunasan tidak pernah melewati 100%.
     *
     * @param  array<int>  $mahasiswaIds
     * @return array<int, float> id_mahasiswa => kredit
     */
    public static function kreditPerMahasiswa(array $mahasiswaIds, ?int $semesterId = null): array
    {
        $mahasiswaIds = array_values(array_unique(array_map('intval', $mahasiswaIds)));
        if ($mahasiswaIds === []) {
            return [];
        }

        return KeringananBiaya::query()
            ->whereIn('id_mahasiswa', $mahasiswaIds)
            ->where('status', 'approved')
            ->when($semesterId, fn ($q) => $q->where('id_semester', $semesterId))
            ->selectRaw('id_mahasiswa, SUM(nominal) as total')
            ->groupBy('id_mahasiswa')
            ->pluck('total', 'id_mahasiswa')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /** Total keringanan disetujui untuk ringkasan dashboard. */
    public static function totalKreditDisetujui(?int $semesterId = null): float
    {
        return (float) KeringananBiaya::query()
            ->where('status', 'approved')
            ->when($semesterId, fn ($q) => $q->where('id_semester', $semesterId))
            ->sum('nominal');
    }
}
