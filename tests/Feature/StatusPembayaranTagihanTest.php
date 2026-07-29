<?php

use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Tagihan\Index as TagihanIndex;
use App\Models\JenisKeringananBiaya;
use App\Models\KeringananBiaya;
use App\Models\Mahasiswa;
use App\Models\Pembayaran;
use App\Models\Semester;
use App\Models\Tagihan;
use App\Services\StatusPembayaranTagihan;
use Livewire\Livewire;

/**
 * Skenario pembanding: satu tagihan untuk tiap status turunan, plus satu tagihan Rp0 yang dulu
 * jadi bukti nyata perbedaan dashboard vs daftar tagihan.
 *
 * @return array<string, Tagihan>
 */
function skenarioStatusTagihan(Semester $semester): array
{
    $buat = function (float $total, string $status = 'unpaid') use ($semester) {
        return Tagihan::factory()->create([
            'id_mahasiswa' => Mahasiswa::factory(),
            'id_semester' => $semester->id,
            'total' => $total,
            'status' => $status,
        ]);
    };

    $lunas = $buat(1000000);
    Pembayaran::factory()->create(['id_tagihan' => $lunas->id, 'nominal' => 1000000, 'approved_at' => now()]);

    $sebagian = $buat(1000000);
    Pembayaran::factory()->create(['id_tagihan' => $sebagian->id, 'nominal' => 400000, 'approved_at' => now()]);

    $belum = $buat(1000000);
    // Pembayaran yang belum disetujui tidak mengubah status apa pun.
    Pembayaran::factory()->create(['id_tagihan' => $belum->id, 'nominal' => 900000, 'approved_at' => null]);

    $kedaluwarsa = $buat(1000000, 'expired');

    // Tagihan Rp0: kolom `status` selamanya 'unpaid' karena tidak pernah ada peristiwa
    // pembayaran, padahal tidak ada yang perlu dibayar.
    $nol = $buat(0);

    return compact('lunas', 'sebagian', 'belum', 'kedaluwarsa', 'nol');
}

it('derives each status from approved payments rather than the stored column', function () {
    $t = skenarioStatusTagihan(Semester::factory()->create());

    $status = fn (Tagihan $tagihan) => StatusPembayaranTagihan::hitung(
        $tagihan->fresh(),
        (float) Pembayaran::approvedQueryForTagihan($tagihan->id)->sum('nominal')
    );

    expect($status($t['lunas']))->toBe(StatusPembayaranTagihan::LUNAS);
    expect($status($t['sebagian']))->toBe(StatusPembayaranTagihan::DIBAYAR_SEBAGIAN);
    expect($status($t['belum']))->toBe(StatusPembayaranTagihan::BELUM_BAYAR);
    expect($status($t['kedaluwarsa']))->toBe(StatusPembayaranTagihan::KEDALUWARSA);
    expect($status($t['nol']))->toBe(StatusPembayaranTagihan::LUNAS);
});

it('gives the same answer in SQL as in PHP for every status', function () {
    $t = skenarioStatusTagihan(Semester::factory()->create());

    $sql = Tagihan::query()
        ->selectRaw('id, '.StatusPembayaranTagihan::sqlEkspresi().' as status_acc')
        ->pluck('status_acc', 'id');

    foreach ($t as $nama => $tagihan) {
        $php = StatusPembayaranTagihan::hitung(
            $tagihan->fresh(),
            (float) Pembayaran::approvedQueryForTagihan($tagihan->id)->sum('nominal')
        );

        expect($sql[$tagihan->id])->toBe($php, "status '{$nama}' berbeda antara SQL dan PHP");
    }
});

it('agrees between SQL and PHP once keringanan credit is involved', function () {
    $semester = Semester::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'total' => 1000000,
    ]);
    KeringananBiaya::factory()->create([
        'id_jenis_keringanan_biaya' => JenisKeringananBiaya::factory(),
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'nominal' => 1000000,
        'status' => 'approved',
    ]);

    $sql = Tagihan::query()
        ->where('id', $tagihan->id)
        ->selectRaw('id, '.StatusPembayaranTagihan::sqlEkspresi().' as status_acc')
        ->value('status_acc');

    expect($sql)->toBe(StatusPembayaranTagihan::LUNAS);
    expect($tagihan->fresh()->status)->toBe('unpaid'); // kolomnya sengaja tidak ikut berubah
});

/**
 * Inti temuan 1.1: dashboard dan daftar tagihan pernah menghitung "lunas" dengan cara berbeda.
 */
it('counts the dashboard summary with the same rule the tagihan list uses', function () {
    $admin = adminUser('admin_keuangan');
    $semester = Semester::factory()->create();
    $t = skenarioStatusTagihan($semester);

    $ringkasan = Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->instance()
        ->keuanganStats()['ringkasan'];

    expect($ringkasan['jumlah_tagihan_lunas'])->toBe(2); // yang lunas + yang Rp0
    expect($ringkasan['jumlah_tagihan_dibayar_sebagian'])->toBe(1);
    expect($ringkasan['jumlah_tagihan_belum_bayar'])->toBe(1);
    expect($ringkasan['jumlah_tagihan_kedaluwarsa'])->toBe(1);

    // Angka yang sama harus keluar dari filter halaman daftar tagihan.
    $jumlahDiDaftar = function (string $status) use ($admin) {
        return Livewire::actingAs($admin)
            ->test(TagihanIndex::class)
            ->set('filterStatusPembayaranAcc', $status)
            ->viewData('tagihanList')
            ->total();
    };

    expect($jumlahDiDaftar(StatusPembayaranTagihan::LUNAS))->toBe($ringkasan['jumlah_tagihan_lunas']);
    expect($jumlahDiDaftar(StatusPembayaranTagihan::DIBAYAR_SEBAGIAN))->toBe($ringkasan['jumlah_tagihan_dibayar_sebagian']);
    expect($jumlahDiDaftar(StatusPembayaranTagihan::BELUM_BAYAR))->toBe($ringkasan['jumlah_tagihan_belum_bayar']);
    expect($jumlahDiDaftar(StatusPembayaranTagihan::KEDALUWARSA))->toBe($ringkasan['jumlah_tagihan_kedaluwarsa']);
});

it('no longer reports a zero-value tagihan as outstanding on the dashboard', function () {
    $admin = adminUser('admin_keuangan');
    Tagihan::factory()->create(['total' => 0, 'status' => 'unpaid']);

    $ringkasan = Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->instance()
        ->keuanganStats()['ringkasan'];

    expect($ringkasan['jumlah_tagihan_lunas'])->toBe(1);
    expect($ringkasan['jumlah_tagihan_belum_bayar'])->toBe(0);
});

it('keeps the legacy API keys working with the derived numbers', function () {
    $admin = adminUser('admin_keuangan');
    $semester = Semester::factory()->create();
    skenarioStatusTagihan($semester);

    $ringkasan = $this->actingAs($admin)
        ->getJson('/api/keuangan/dashboard-stats?id_semester=')
        ->assertOk()
        ->json('ringkasan');

    expect($ringkasan['jumlah_tagihan_paid'])->toBe(2);
    // "unpaid" secara historis berarti belum lunas, jadi mencakup yang dibayar sebagian.
    expect($ringkasan['jumlah_tagihan_unpaid'])->toBe(2);
    expect($ringkasan['jumlah_tagihan_expired'])->toBe(1);
});
