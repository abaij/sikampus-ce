<?php

use App\Models\KomponenBiaya;
use App\Models\Mahasiswa;
use App\Models\Semester;
use App\Models\StrukturBiaya;
use App\Models\Tagihan;
use App\Services\SeriNomorDokumen;
use Illuminate\Support\Facades\DB;

/**
 * Generate massal sempat memakan 27.018 query dan 32,9 detik untuk 5.400 tagihan (900 mahasiswa
 * x 6 tahap) — lima query per tagihan, cukup untuk menyentuh batas waktu web server sekaligus
 * menahan transaksi selama itu.
 *
 * Tiga di antaranya bisa dihilangkan: pengambilan nomor dokumen (dikunci sekali lalu dilanjutkan
 * di memori), pencarian tagihan yang sudah ada (dipramuat satu query), dan pengecekan baris
 * rincian pada tagihan yang baru dibuat (mustahil sudah ada). Sisanya dua INSERT per tagihan,
 * yang memang tidak bisa dikurangi lagi tanpa mengorbankan jejak audit dari event model.
 *
 * Tes ini mengunci angkanya supaya query per tagihan tidak diam-diam naik lagi.
 */
function siapkanGenerate(int $jumlahMahasiswa, int $jumlahTahap): array
{
    $periode = Semester::factory()->create(['tanggal_mulai' => '2024-09-01']);
    $angkatan = Semester::factory()->create();
    $komponen = KomponenBiaya::factory()->create();

    Mahasiswa::factory()->count($jumlahMahasiswa)->create(['id_semester_masuk' => $angkatan->id]);

    for ($tahap = 1; $tahap <= $jumlahTahap; $tahap++) {
        StrukturBiaya::factory()->create([
            'id_periode' => $periode->id,
            'id_angkatan' => $angkatan->id,
            'id_prodi' => null,
            'id_kategori_biaya' => null,
            'id_komponen_biaya' => $komponen->id,
            'tahap' => $tahap,
            'nominal' => 1000000,
        ]);
    }

    return [$periode, $angkatan];
}

it('uses a flat number of queries per generated tagihan', function () {
    $admin = adminUser('admin_keuangan');
    [$periode, $angkatan] = siapkanGenerate(jumlahMahasiswa: 12, jumlahTahap: 5);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($admin)->postJson('/api/tagihan/generate', [
        'id_periode' => $periode->id,
        'id_angkatan' => $angkatan->id,
        'opsi_tahap' => 'all',
    ])->assertOk()->assertJson(['created_count' => 60]);

    $jumlahQuery = count(DB::getQueryLog());
    DB::disableQueryLog();

    // 60 tagihan x 2 INSERT = 120, ditambah sedikit query persiapan (struktur biaya, mahasiswa,
    // nomor dokumen, pramuat, cek role). Ambang 160 memberi ruang wajar tanpa menoleransi
    // kembalinya query per baris, yang akan melompat ke 300+.
    expect($jumlahQuery)->toBeLessThan(160);
});

it('locks the document number only once for the whole run', function () {
    $admin = adminUser('admin_keuangan');
    [$periode, $angkatan] = siapkanGenerate(jumlahMahasiswa: 10, jumlahTahap: 4);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($admin)->postJson('/api/tagihan/generate', [
        'id_periode' => $periode->id,
        'id_angkatan' => $angkatan->id,
        'opsi_tahap' => 'all',
    ])->assertOk();

    $penguncian = collect(DB::getQueryLog())
        ->filter(fn ($q) => str_contains($q['query'], 'for update') && str_contains($q['query'], 'no_tagihan'))
        ->count();
    DB::disableQueryLog();

    expect($penguncian)->toBe(1);
});

it('still numbers every generated tagihan uniquely and in sequence', function () {
    $admin = adminUser('admin_keuangan');
    [$periode, $angkatan] = siapkanGenerate(jumlahMahasiswa: 8, jumlahTahap: 3);

    $this->actingAs($admin)->postJson('/api/tagihan/generate', [
        'id_periode' => $periode->id,
        'id_angkatan' => $angkatan->id,
        'opsi_tahap' => 'all',
    ])->assertOk()->assertJson(['created_count' => 24]);

    $nomor = Tagihan::orderBy('id')->pluck('no_tagihan');

    expect($nomor)->toHaveCount(24);
    expect($nomor->unique())->toHaveCount(24);
    expect($nomor->first())->toBe('INV-'.now()->format('Ymd').'-0001');
    expect($nomor->last())->toBe('INV-'.now()->format('Ymd').'-0024');
});

it('continues the in-memory series from numbers already used today', function () {
    $prefix = 'INV-'.now()->format('Ymd').'-';
    Tagihan::factory()->create(['no_tagihan' => $prefix.'0005']);

    DB::transaction(function () use ($prefix) {
        $seri = SeriNomorDokumen::tagihan();

        expect($seri->berikutnya())->toBe($prefix.'0006');
        expect($seri->berikutnya())->toBe($prefix.'0007');
        expect($seri->berikutnya())->toBe($prefix.'0008');
    });
});
