<?php

namespace App\Services;

use App\Models\Tagihan;
use App\Models\TagihanRinci;
use Closure;
use Illuminate\Support\Collection;

/**
 * Menerapkan satu set rincian hasil generate ke tagihan milik (mahasiswa, semester, tahap).
 *
 * Deteksi kembar pada generator dulu hanya bertanya "apakah tahap ini sudah punya tagihan?" —
 * tanpa melihat komponen biaya. Karena halaman generate mengelompokkan struktur biaya PER
 * KOMPONEN, konsekuensinya: setelah komponen pertama (mis. UKT) digenerate untuk tahap 1,
 * generate komponen kedua (mis. Praktikum) untuk tahap yang sama dilewati seluruhnya dengan
 * alasan "sudah ada" — mahasiswa tidak pernah ditagih komponen itu dan tidak ada peringatan.
 *
 * Aturan 1.5 menetapkan satu tagihan per (mahasiswa, semester, tahap), dan model domainnya
 * memang menampung banyak komponen lewat tagihan_rinci. Jadi komponen baru digabungkan sebagai
 * baris rincian ke tagihan tahap itu, bukan jadi tagihan kedua:
 *
 *   - belum ada tagihan tahap itu           → dibuat baru berisi seluruh komponen;
 *   - ada, dan komponennya belum tercatat   → komponen ditambahkan, total dihitung ulang;
 *   - ada, dan semua komponennya tercatat   → dilewati, dengan alasan yang menyebut nomornya.
 *
 * Tagihan yang sudah punya pembayaran disetujui tetap ditambahi. Mahasiswa memang berutang
 * komponen tersebut, dan status pelunasan sekarang turunan (lihat StatusPembayaranTagihan),
 * jadi tagihan yang tadinya Lunas otomatis kembali jadi Dibayar sebagian tanpa perlu disentuh.
 */
final class PenerapanTagihanTahap
{
    public const DIBUAT = 'dibuat';

    public const DITAMBAH = 'ditambah';

    public const DILEWATI = 'dilewati';

    /**
     * @param  Collection<int, array{id_komponen_biaya: mixed, nominal: float}>  $rincian
     * @param  array{tanggal_tagihan: string, tanggal_jatuh_tempo: string}  $jadwal
     * @param  Closure(): string  $noTagihan  dipanggil hanya kalau memang membuat tagihan baru
     * @param  Tagihan|null  $tagihanTerpasang  hasil pramuat dari pemanggil massal
     * @param  bool  $sudahDipramuat  true = $tagihanTerpasang sudah final, termasuk kalau null,
     *                                jadi service tidak perlu query mencarinya lagi. Tanpa
     *                                penanda ini, null tidak bisa dibedakan dari "belum dicek"
     *                                dan query per baris tetap jalan — persis yang bikin generate
     *                                massal mahal.
     * @return array{hasil: string, tagihan: Tagihan, komponen_baru: int, alasan: ?string}
     */
    public static function terapkan(
        int $mahasiswaId,
        int $semesterId,
        int $tahap,
        Collection $rincian,
        array $jadwal,
        ?string $keterangan,
        Closure $noTagihan,
        ?Tagihan $tagihanTerpasang = null,
        bool $sudahDipramuat = false,
    ): array {
        $tagihan = $tagihanTerpasang;

        if (! $tagihan && ! $sudahDipramuat) {
            $tagihan = Tagihan::where('id_mahasiswa', $mahasiswaId)
                ->where('id_semester', $semesterId)
                ->where('tahap', $tahap)
                ->first();
        }

        if (! $tagihan) {
            $tagihan = Tagihan::create([
                'id_mahasiswa' => $mahasiswaId,
                'id_semester' => $semesterId,
                'no_tagihan' => $noTagihan(),
                'total' => (float) $rincian->sum('nominal'),
                'tahap' => $tahap,
                'status' => 'unpaid',
                'tanggal_tagihan' => $jadwal['tanggal_tagihan'],
                'tanggal_jatuh_tempo' => $jadwal['tanggal_jatuh_tempo'],
                'keterangan' => $keterangan,
            ]);

            // Tagihan ini baru saja dibuat, jadi mustahil sudah punya baris rincian —
            // tidak perlu pengecekan withTrashed per komponen seperti pada jalur penggabungan.
            foreach ($rincian as $row) {
                TagihanRinci::create([
                    'id_tagihan' => $tagihan->id,
                    'id_komponen_biaya' => (int) $row['id_komponen_biaya'],
                    'nominal' => (float) $row['nominal'],
                ]);
            }

            return [
                'hasil' => self::DIBUAT,
                'tagihan' => $tagihan,
                'komponen_baru' => $rincian->count(),
                'alasan' => null,
            ];
        }

        $komponenTercatat = TagihanRinci::where('id_tagihan', $tagihan->id)
            ->pluck('id_komponen_biaya')
            ->map(fn ($id) => (int) $id)
            ->all();

        $komponenBaru = $rincian
            ->reject(fn ($row) => in_array((int) $row['id_komponen_biaya'], $komponenTercatat, true))
            ->values();

        if ($komponenBaru->isEmpty()) {
            return [
                'hasil' => self::DILEWATI,
                'tagihan' => $tagihan,
                'komponen_baru' => 0,
                'alasan' => "Semua komponen biaya tahap {$tahap} sudah tercatat pada tagihan {$tagihan->no_tagihan}.",
            ];
        }

        foreach ($komponenBaru as $row) {
            self::simpanRinci((int) $tagihan->id, (int) $row['id_komponen_biaya'], (float) $row['nominal']);
        }

        // Total selalu dihitung ulang dari seluruh rincian, bukan ditambahkan ke total lama,
        // supaya tidak ikut menggandakan kalau ada baris yang sempat tidak sinkron.
        $tagihan->update([
            'total' => (float) TagihanRinci::where('id_tagihan', $tagihan->id)->sum('nominal'),
        ]);

        return [
            'hasil' => self::DITAMBAH,
            'tagihan' => $tagihan,
            'komponen_baru' => $komponenBaru->count(),
            'alasan' => null,
        ];
    }

    /**
     * Pramuat tagihan yang sudah ada untuk sekumpulan mahasiswa pada satu semester, dikunci per
     * (id_mahasiswa, tahap). Satu query menggantikan satu query per baris yang akan diproses.
     *
     * @param  array<int>  $mahasiswaIds
     * @return Collection<string, Tagihan>
     */
    public static function pramuat(int $semesterId, array $mahasiswaIds): Collection
    {
        if ($mahasiswaIds === []) {
            return collect();
        }

        return Tagihan::where('id_semester', $semesterId)
            ->whereIn('id_mahasiswa', $mahasiswaIds)
            ->get()
            ->keyBy(fn (Tagihan $t) => self::kunciPramuat((int) $t->id_mahasiswa, $t->tahap === null ? null : (int) $t->tahap));
    }

    public static function kunciPramuat(int $mahasiswaId, ?int $tahap): string
    {
        return $mahasiswaId.':'.($tahap ?? '-');
    }

    /**
     * TagihanRinci memakai SoftDeletes, dan baris yang di-soft-delete tetap menempati unique
     * index (id_tagihan, id_komponen_biaya) — jadi create() polos bisa menabrak constraint untuk
     * komponen yang pernah dihapus. Baris seperti itu dipulihkan dan nominalnya diperbarui.
     */
    private static function simpanRinci(int $tagihanId, int $komponenBiayaId, float $nominal): void
    {
        $rinci = TagihanRinci::withTrashed()
            ->where('id_tagihan', $tagihanId)
            ->where('id_komponen_biaya', $komponenBiayaId)
            ->first();

        if (! $rinci) {
            TagihanRinci::create([
                'id_tagihan' => $tagihanId,
                'id_komponen_biaya' => $komponenBiayaId,
                'nominal' => $nominal,
            ]);

            return;
        }

        if ($rinci->trashed()) {
            $rinci->restore();
        }

        $rinci->update(['nominal' => $nominal]);
    }
}
