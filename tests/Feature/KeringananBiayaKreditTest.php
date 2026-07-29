<?php

use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Pembayaran\Form;
use App\Livewire\Admin\Pembayaran\LaporanPelunasan;
use App\Livewire\Admin\Tagihan\Index;
use App\Models\JenisKeringananBiaya;
use App\Models\KeringananBiaya;
use App\Models\Mahasiswa;
use App\Models\Pembayaran;
use App\Models\Semester;
use App\Models\Tagihan;
use App\Services\KeringananBiayaKreditService;
use App\Services\KeuanganAksesMahasiswaService;
use Livewire\Livewire;

function buatKeringanan(Mahasiswa $mahasiswa, Semester $semester, float $nominal, string $status = 'approved'): KeringananBiaya
{
    return KeringananBiaya::factory()->create([
        'id_jenis_keringanan_biaya' => JenisKeringananBiaya::factory(),
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'nominal' => $nominal,
        'status' => $status,
    ]);
}

it('gives no credit when the mahasiswa has no keringanan at all', function () {
    $tagihan = Tagihan::factory()->create(['total' => 1000000]);

    expect(KeringananBiayaKreditService::kreditUntukTagihan($tagihan))->toBe(0.0);
});

it('ignores keringanan that has not been approved', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $semester = Semester::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'total' => 1000000,
    ]);

    buatKeringanan($mahasiswa, $semester, 400000, 'pending');
    buatKeringanan($mahasiswa, $semester, 300000, 'rejected');

    expect(KeringananBiayaKreditService::kreditUntukTagihan($tagihan))->toBe(0.0);
});

it('applies an approved keringanan against a single tagihan', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $semester = Semester::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'total' => 1000000,
    ]);

    buatKeringanan($mahasiswa, $semester, 400000);

    expect(KeringananBiayaKreditService::kreditUntukTagihan($tagihan))->toBe(400000.0);
});

it('spreads the credit proportionally across every tagihan of the same semester', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $semester = Semester::factory()->create();

    $tahap1 = Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'total' => 600000,
        'tahap' => 1,
    ]);
    $tahap2 = Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'total' => 400000,
        'tahap' => 2,
    ]);

    buatKeringanan($mahasiswa, $semester, 500000);

    $kredit = KeringananBiayaKreditService::kreditUntukTagihanIds([$tahap1->id, $tahap2->id]);

    // rasio = 500.000 / 1.000.000 = 0,5
    expect($kredit[$tahap1->id])->toBe(300000.0);
    expect($kredit[$tahap2->id])->toBe(200000.0);
});

it('never allocates more credit than the outstanding amount', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $semester = Semester::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'total' => 1000000,
    ]);
    Pembayaran::factory()->create([
        'id_tagihan' => $tagihan->id,
        'nominal' => 700000,
        'approved_at' => now(),
    ]);

    // Keringanan jauh lebih besar dari sisa; kelebihannya hangus, tidak jadi kelebihan bayar.
    buatKeringanan($mahasiswa, $semester, 5000000);

    expect(KeringananBiayaKreditService::kreditUntukTagihan($tagihan))->toBe(300000.0);
});

it('does not leak credit across semesters', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $semesterA = Semester::factory()->create();
    $semesterB = Semester::factory()->create();

    $tagihanA = Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semesterA->id,
        'total' => 1000000,
    ]);
    $tagihanB = Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semesterB->id,
        'total' => 1000000,
    ]);

    buatKeringanan($mahasiswa, $semesterA, 400000);

    $kredit = KeringananBiayaKreditService::kreditUntukTagihanIds([$tagihanA->id, $tagihanB->id]);

    expect($kredit[$tagihanA->id])->toBe(400000.0);
    expect($kredit[$tagihanB->id])->toBe(0.0);
});

it('does not leak credit to another mahasiswa in the same semester', function () {
    $semester = Semester::factory()->create();
    $punyaKeringanan = Mahasiswa::factory()->create();
    $lain = Mahasiswa::factory()->create();

    $tagihanPunya = Tagihan::factory()->create([
        'id_mahasiswa' => $punyaKeringanan->id,
        'id_semester' => $semester->id,
        'total' => 1000000,
    ]);
    $tagihanLain = Tagihan::factory()->create([
        'id_mahasiswa' => $lain->id,
        'id_semester' => $semester->id,
        'total' => 1000000,
    ]);

    buatKeringanan($punyaKeringanan, $semester, 250000);

    $kredit = KeringananBiayaKreditService::kreditUntukTagihanIds([$tagihanPunya->id, $tagihanLain->id]);

    expect($kredit[$tagihanPunya->id])->toBe(250000.0);
    expect($kredit[$tagihanLain->id])->toBe(0.0);
});

it('counts keringanan toward the KRS payment-percentage gate', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $semester = Semester::factory()->create();
    Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'total' => 1000000,
        'tanggal_tagihan' => now()->subDay(),
    ]);
    Pembayaran::factory()->create([
        'id_tagihan' => Tagihan::where('id_mahasiswa', $mahasiswa->id)->value('id'),
        'nominal' => 300000,
        'approved_at' => now(),
    ]);

    $sebelum = KeuanganAksesMahasiswaService::paymentPercentForBerlakuTagihan($mahasiswa->id);
    expect($sebelum['persentase_pembayaran'])->toBe(30.0);

    buatKeringanan($mahasiswa, $semester, 400000);

    $sesudah = KeuanganAksesMahasiswaService::paymentPercentForBerlakuTagihan($mahasiswa->id);
    expect($sesudah['persentase_pembayaran'])->toBe(70.0);
    expect($sesudah['total_keringanan_disetujui'])->toBe(400000.0);
    expect($sesudah['total_terbayar_disetujui'])->toBe(300000.0);
});

it('returns the credit back to zero when an approved keringanan is revoked', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $semester = Semester::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'total' => 1000000,
    ]);

    $keringanan = buatKeringanan($mahasiswa, $semester, 400000);
    expect(KeringananBiayaKreditService::kreditUntukTagihan($tagihan))->toBe(400000.0);

    // Tidak ada baris tagihan/pembayaran yang dimutasi, jadi mencabut approval cukup.
    $keringanan->update(['status' => 'rejected']);
    expect(KeringananBiayaKreditService::kreditUntukTagihan($tagihan->fresh()))->toBe(0.0);

    $keringanan->update(['status' => 'approved']);
    $keringanan->delete();
    expect(KeringananBiayaKreditService::kreditUntukTagihan($tagihan->fresh()))->toBe(0.0);
});

it('matches the SQL credit expression with the PHP allocation', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $semester = Semester::factory()->create();

    $tahap1 = Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'total' => 600000,
        'tahap' => 1,
    ]);
    $tahap2 = Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'total' => 400000,
        'tahap' => 2,
    ]);
    buatKeringanan($mahasiswa, $semester, 500000);

    $php = KeringananBiayaKreditService::kreditUntukTagihanIds([$tahap1->id, $tahap2->id]);

    $sql = Tagihan::query()
        ->whereIn('id', [$tahap1->id, $tahap2->id])
        ->selectRaw('id, '.KeringananBiayaKreditService::sqlKreditTagihan().' as kredit')
        ->pluck('kredit', 'id');

    expect(round((float) $sql[$tahap1->id], 2))->toBe($php[$tahap1->id]);
    expect(round((float) $sql[$tahap2->id], 2))->toBe($php[$tahap2->id]);
});

it('marks a tagihan lunas in the admin list once keringanan covers the remainder', function () {
    $admin = adminUser('admin_keuangan');
    $mahasiswa = Mahasiswa::factory()->create();
    $semester = Semester::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'total' => 1000000,
    ]);
    Pembayaran::factory()->create([
        'id_tagihan' => $tagihan->id,
        'nominal' => 600000,
        'approved_at' => now(),
    ]);

    $statusOf = function () use ($admin) {
        $view = Livewire::actingAs($admin)->test(Index::class);

        return $view->viewData('paymentSummaries')->first();
    };

    expect($statusOf()['status'])->toBe('dibayar_sebagian');

    buatKeringanan($mahasiswa, $semester, 400000);

    $ringkasan = $statusOf();
    expect($ringkasan['status'])->toBe('lunas');
    expect($ringkasan['kredit_keringanan'])->toBe(400000.0);
    expect($ringkasan['sisa'])->toBe(0.0);
});

it('keeps the lunas filter and the row label in agreement once keringanan applies', function () {
    $admin = adminUser('admin_keuangan');
    $mahasiswa = Mahasiswa::factory()->create();
    $semester = Semester::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'total' => 1000000,
    ]);
    buatKeringanan($mahasiswa, $semester, 1000000);

    $view = Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('filterStatusPembayaranAcc', 'lunas');

    expect($view->viewData('tagihanList')->pluck('id')->all())->toBe([$tagihan->id]);
    expect($view->viewData('paymentSummaries')[$tagihan->id]['status'])->toBe('lunas');

    // Baris yang sama tidak boleh ikut muncul di filter yang berlawanan.
    $belum = Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('filterStatusPembayaranAcc', 'belum_bayar');
    expect($belum->viewData('tagihanList')->pluck('id')->all())->toBe([]);
});

it('refuses a payment that exceeds what is left after keringanan', function () {
    $admin = adminUser('admin_keuangan');
    $mahasiswa = Mahasiswa::factory()->create();
    $semester = Semester::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'total' => 1000000,
    ]);
    buatKeringanan($mahasiswa, $semester, 700000);

    Livewire::actingAs($admin)
        ->test(Form::class)
        ->set('nim', $mahasiswa->nim)
        ->call('selectTagihan', $tagihan->id)
        ->set('nominal', '400000')
        ->call('save')
        ->assertHasErrors('nominal');

    expect(Pembayaran::where('id_tagihan', $tagihan->id)->count())->toBe(0);
});

it('counts keringanan toward the pelunasan report percentage', function () {
    $admin = adminUser('admin_keuangan');
    $mahasiswa = Mahasiswa::factory()->create();
    $semester = Semester::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'total' => 1000000,
    ]);
    Pembayaran::factory()->create([
        'id_tagihan' => $tagihan->id,
        'nominal' => 250000,
        'approved_at' => now(),
    ]);
    buatKeringanan($mahasiswa, $semester, 500000);

    $baris = Livewire::actingAs($admin)
        ->test(LaporanPelunasan::class)
        ->viewData('rows')
        ->first();

    expect($baris->total_pembayaran)->toBe(250000.0);
    expect($baris->total_keringanan)->toBe(500000.0);
    expect($baris->sisa)->toBe(250000.0);
    expect($baris->persentase)->toBe(75.0);
});

it('subtracts keringanan from the dashboard piutang figure', function () {
    $admin = adminUser('admin_keuangan');
    $mahasiswa = Mahasiswa::factory()->create();
    $semester = Semester::factory()->create();
    $tagihan = Tagihan::factory()->create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'total' => 1000000,
    ]);
    Pembayaran::factory()->create([
        'id_tagihan' => $tagihan->id,
        'nominal' => 200000,
        'approved_at' => now(),
    ]);
    buatKeringanan($mahasiswa, $semester, 300000);

    $ringkasan = Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->instance()
        ->keuanganStats()['ringkasan'];

    expect($ringkasan['total_terbayar'])->toBe(200000.0);
    expect($ringkasan['total_keringanan'])->toBe(300000.0);
    expect($ringkasan['total_piutang'])->toBe(500000.0);
});
