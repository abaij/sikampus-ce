<?php

use App\Livewire\Admin\Tagihan\Generate;
use App\Models\KomponenBiaya;
use App\Models\Mahasiswa;
use App\Models\Pembayaran;
use App\Models\Semester;
use App\Models\StrukturBiaya;
use App\Models\Tagihan;
use App\Models\TagihanRinci;
use App\Services\StatusPembayaranTagihan;
use Livewire\Livewire;

function strukturKomponen(Semester $periode, Semester $angkatan, KomponenBiaya $komponen, int $tahap, float $nominal): StrukturBiaya
{
    return StrukturBiaya::factory()->create([
        'id_periode' => $periode->id,
        'id_angkatan' => $angkatan->id,
        'id_kategori_biaya' => null,
        'id_prodi' => null,
        'id_komponen_biaya' => $komponen->id,
        'tahap' => $tahap,
        'nominal' => $nominal,
    ]);
}

function generateKomponen(Semester $periode, Semester $angkatan, int $komponenId, $admin): array
{
    return test()->actingAs($admin)->postJson('/api/tagihan/generate', [
        'id_periode' => $periode->id,
        'id_angkatan' => $angkatan->id,
        'id_komponen_biaya' => $komponenId,
        'opsi_tahap' => 'all',
    ])->assertOk()->json();
}

/**
 * Inti sisa temuan 2.7: halaman generate mengelompokkan struktur biaya per komponen, tapi cek
 * kembar hanya melihat (mahasiswa, semester, tahap). Komponen kedua karena itu dilewati
 * seluruhnya dan mahasiswa tidak pernah ditagih untuk komponen tersebut.
 */
it('adds a second fee component to the existing tagihan for the same tahap', function () {
    $admin = adminUser('admin_keuangan');
    $periode = Semester::factory()->create(['tanggal_mulai' => '2024-09-01']);
    $angkatan = Semester::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create(['id_semester_masuk' => $angkatan->id]);

    $ukt = KomponenBiaya::factory()->create(['nama' => 'UKT']);
    $praktikum = KomponenBiaya::factory()->create(['nama' => 'Praktikum']);
    strukturKomponen($periode, $angkatan, $ukt, 1, 2000000);
    strukturKomponen($periode, $angkatan, $praktikum, 1, 500000);

    $pertama = generateKomponen($periode, $angkatan, $ukt->id, $admin);
    expect($pertama['created_count'])->toBe(1);

    $kedua = generateKomponen($periode, $angkatan, $praktikum->id, $admin);
    expect($kedua['created_count'])->toBe(0);
    expect($kedua['updated_count'])->toBe(1);
    expect($kedua['skipped_count'])->toBe(0);

    // Tetap satu tagihan per tahap (aturan 1.5), dengan dua baris rincian.
    $tagihan = Tagihan::where('id_mahasiswa', $mahasiswa->id)->get();
    expect($tagihan)->toHaveCount(1);
    expect((float) $tagihan->first()->total)->toBe(2500000.0);
    expect(TagihanRinci::where('id_tagihan', $tagihan->first()->id)->count())->toBe(2);
});

it('skips only when every component of that tahap is already recorded', function () {
    $admin = adminUser('admin_keuangan');
    $periode = Semester::factory()->create(['tanggal_mulai' => '2024-09-01']);
    $angkatan = Semester::factory()->create();
    Mahasiswa::factory()->create(['id_semester_masuk' => $angkatan->id]);

    $ukt = KomponenBiaya::factory()->create(['nama' => 'UKT']);
    strukturKomponen($periode, $angkatan, $ukt, 1, 2000000);

    generateKomponen($periode, $angkatan, $ukt->id, $admin);
    $ulang = generateKomponen($periode, $angkatan, $ukt->id, $admin);

    expect($ulang['created_count'])->toBe(0);
    expect($ulang['updated_count'])->toBe(0);
    expect($ulang['skipped_count'])->toBe(1);
    expect($ulang['skipped_detail'][0]['reason'])->toContain('sudah tercatat pada tagihan');

    expect(Tagihan::where('id_semester', $periode->id)->count())->toBe(1);
    expect((float) Tagihan::where('id_semester', $periode->id)->value('total'))->toBe(2000000.0);
});

it('reopens a settled tagihan when a new component is added to it', function () {
    $admin = adminUser('admin_keuangan');
    $periode = Semester::factory()->create(['tanggal_mulai' => '2024-09-01']);
    $angkatan = Semester::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create(['id_semester_masuk' => $angkatan->id]);

    $ukt = KomponenBiaya::factory()->create(['nama' => 'UKT']);
    $praktikum = KomponenBiaya::factory()->create(['nama' => 'Praktikum']);
    strukturKomponen($periode, $angkatan, $ukt, 1, 2000000);
    strukturKomponen($periode, $angkatan, $praktikum, 1, 500000);

    generateKomponen($periode, $angkatan, $ukt->id, $admin);
    $tagihan = Tagihan::where('id_mahasiswa', $mahasiswa->id)->firstOrFail();

    Pembayaran::factory()->create([
        'id_tagihan' => $tagihan->id,
        'nominal' => 2000000,
        'approved_at' => now(),
    ]);

    $statusSebelum = StatusPembayaranTagihan::hitung(
        $tagihan->fresh(),
        (float) Pembayaran::approvedQueryForTagihan($tagihan->id)->sum('nominal')
    );
    expect($statusSebelum)->toBe(StatusPembayaranTagihan::LUNAS);

    generateKomponen($periode, $angkatan, $praktikum->id, $admin);

    // Statusnya turunan, jadi ikut turun sendiri tanpa perlu menyentuh baris tagihan.
    expect((float) $tagihan->fresh()->total)->toBe(2500000.0);
    expect(StatusPembayaranTagihan::hitung(
        $tagihan->fresh(),
        (float) Pembayaran::approvedQueryForTagihan($tagihan->id)->sum('nominal')
    ))->toBe(StatusPembayaranTagihan::DIBAYAR_SEBAGIAN);
});

it('keeps each tahap separate when merging components', function () {
    $admin = adminUser('admin_keuangan');
    $periode = Semester::factory()->create(['tanggal_mulai' => '2024-09-01']);
    $angkatan = Semester::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create(['id_semester_masuk' => $angkatan->id]);

    $ukt = KomponenBiaya::factory()->create(['nama' => 'UKT']);
    $praktikum = KomponenBiaya::factory()->create(['nama' => 'Praktikum']);
    strukturKomponen($periode, $angkatan, $ukt, 1, 2000000);
    strukturKomponen($periode, $angkatan, $ukt, 2, 1000000);
    strukturKomponen($periode, $angkatan, $praktikum, 2, 300000);

    generateKomponen($periode, $angkatan, $ukt->id, $admin);
    generateKomponen($periode, $angkatan, $praktikum->id, $admin);

    $tagihan = Tagihan::where('id_mahasiswa', $mahasiswa->id)->orderBy('tahap')->get();

    expect($tagihan->pluck('tahap')->all())->toBe([1, 2]);
    expect((float) $tagihan[0]->total)->toBe(2000000.0); // tahap 1 tidak kena praktikum
    expect((float) $tagihan[1]->total)->toBe(1300000.0); // tahap 2 bertambah praktikum
});

it('restores a previously removed rincian line instead of hitting the unique index', function () {
    $admin = adminUser('admin_keuangan');
    $periode = Semester::factory()->create(['tanggal_mulai' => '2024-09-01']);
    $angkatan = Semester::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create(['id_semester_masuk' => $angkatan->id]);

    $ukt = KomponenBiaya::factory()->create(['nama' => 'UKT']);
    strukturKomponen($periode, $angkatan, $ukt, 1, 2000000);

    generateKomponen($periode, $angkatan, $ukt->id, $admin);
    $tagihan = Tagihan::where('id_mahasiswa', $mahasiswa->id)->firstOrFail();

    // Baris rincian di-soft-delete; barisnya tetap menempati unique index.
    TagihanRinci::where('id_tagihan', $tagihan->id)->delete();

    $ulang = generateKomponen($periode, $angkatan, $ukt->id, $admin);

    expect($ulang['updated_count'])->toBe(1);
    expect(TagihanRinci::where('id_tagihan', $tagihan->id)->count())->toBe(1);
    expect((float) $tagihan->fresh()->total)->toBe(2000000.0);
});

it('merges components from the admin panel too, not just the API', function () {
    $admin = adminUser('admin_keuangan');
    $periode = Semester::factory()->create(['tanggal_mulai' => '2024-09-01']);
    $angkatan = Semester::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create(['id_semester_masuk' => $angkatan->id]);

    $ukt = KomponenBiaya::factory()->create(['nama' => 'UKT']);
    $praktikum = KomponenBiaya::factory()->create(['nama' => 'Praktikum']);
    strukturKomponen($periode, $angkatan, $ukt, 1, 2000000);
    strukturKomponen($periode, $angkatan, $praktikum, 1, 500000);

    $jalankan = function (KomponenBiaya $komponen) use ($admin) {
        $component = Livewire::actingAs($admin)->test(Generate::class);
        $grup = $component->instance()->groupedStrukturBiaya()
            ->firstWhere('id_komponen_biaya', $komponen->id);

        $component->call('openGenerateModal', $grup['key'])->call('generate');
    };

    $jalankan($ukt);
    $jalankan($praktikum);

    $tagihan = Tagihan::where('id_mahasiswa', $mahasiswa->id)->get();
    expect($tagihan)->toHaveCount(1);
    expect((float) $tagihan->first()->total)->toBe(2500000.0);
    expect(TagihanRinci::where('id_tagihan', $tagihan->first()->id)->count())->toBe(2);
});
