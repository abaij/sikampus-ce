<?php

use App\Livewire\Admin\Tagihan\Generate;
use App\Models\KomponenBiaya;
use App\Models\Mahasiswa;
use App\Models\Semester;
use App\Models\StrukturBiaya;
use App\Models\Tagihan;
use App\Services\JadwalTagihanTahap;
use Livewire\Livewire;

function strukturTahap(Semester $periode, Semester $angkatan, array $tahapList): void
{
    $komponen = KomponenBiaya::factory()->create();
    foreach ($tahapList as $tahap) {
        StrukturBiaya::factory()->create([
            'id_periode' => $periode->id,
            'id_angkatan' => $angkatan->id,
            'id_kategori_biaya' => null,
            'id_komponen_biaya' => $komponen->id,
            'tahap' => $tahap,
            'nominal' => 500000,
        ]);
    }
}

it('takes the base date from the period being billed, not from today', function () {
    $periode = Semester::factory()->create(['tanggal_mulai' => '2024-09-01']);

    $jadwal = JadwalTagihanTahap::resolve($periode);

    expect($jadwal->untukTahap(1)['tanggal_tagihan'])->toBe('2024-09-01');
    expect($jadwal->untukTahap(3)['tanggal_tagihan'])->toBe('2024-11-01');
    expect($jadwal->sumber)->toBe(JadwalTagihanTahap::SUMBER_PERIODE);
});

it('lets an explicit date win over the period start', function () {
    $periode = Semester::factory()->create(['tanggal_mulai' => '2024-09-01']);

    $jadwal = JadwalTagihanTahap::resolve($periode, '2024-10-05');

    expect($jadwal->untukTahap(1)['tanggal_tagihan'])->toBe('2024-10-05');
    expect($jadwal->sumber)->toBe(JadwalTagihanTahap::SUMBER_INPUT);
});

it('defaults the due date to 14 days after the bill date and shifts it per tahap', function () {
    $jadwal = JadwalTagihanTahap::resolve(null, '2024-09-01');

    expect($jadwal->untukTahap(1)['tanggal_jatuh_tempo'])->toBe('2024-09-15');
    expect($jadwal->untukTahap(2)['tanggal_jatuh_tempo'])->toBe('2024-10-15');
});

it('honours an explicit due date', function () {
    $jadwal = JadwalTagihanTahap::resolve(null, '2024-09-01', '2024-09-20');

    expect($jadwal->untukTahap(1)['tanggal_jatuh_tempo'])->toBe('2024-09-20');
    expect($jadwal->untukTahap(2)['tanggal_jatuh_tempo'])->toBe('2024-10-20');
});

it('does not overflow the month when the base day does not exist in the next month', function () {
    $jadwal = JadwalTagihanTahap::resolve(null, '2024-01-31');

    // Tanpa addMonthsNoOverflow, 31 Januari + 1 bulan melompat ke 2 Maret.
    expect($jadwal->untukTahap(2)['tanggal_tagihan'])->toBe('2024-02-29');
});

it('refuses to guess when neither an explicit date nor a period start is available', function () {
    $periode = Semester::factory()->create(['tanggal_mulai' => null]);

    expect(JadwalTagihanTahap::resolve($periode))->toBeNull();
    expect(JadwalTagihanTahap::resolve(null))->toBeNull();
});

it('generates API tagihan dated from the period rather than the run date', function () {
    $admin = adminUser('admin_keuangan');
    $periode = Semester::factory()->create(['tanggal_mulai' => '2024-09-01']);
    $angkatan = Semester::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create(['id_semester_masuk' => $angkatan->id]);
    strukturTahap($periode, $angkatan, [1, 2]);

    $this->actingAs($admin)->postJson('/api/tagihan/generate', [
        'id_periode' => $periode->id,
        'id_angkatan' => $angkatan->id,
        'opsi_tahap' => 'all',
    ])->assertOk()->assertJson(['created_count' => 2]);

    $tagihan = Tagihan::where('id_mahasiswa', $mahasiswa->id)->orderBy('tahap')->get();

    expect($tagihan[0]->tanggal_tagihan->toDateString())->toBe('2024-09-01');
    expect($tagihan[0]->tanggal_jatuh_tempo->toDateString())->toBe('2024-09-15');
    expect($tagihan[1]->tanggal_tagihan->toDateString())->toBe('2024-10-01');
    expect($tagihan[1]->tanggal_jatuh_tempo->toDateString())->toBe('2024-10-15');
});

it('uses the dates the operator supplied instead of silently discarding them', function () {
    $admin = adminUser('admin_keuangan');
    $periode = Semester::factory()->create(['tanggal_mulai' => '2024-09-01']);
    $angkatan = Semester::factory()->create();
    Mahasiswa::factory()->create(['id_semester_masuk' => $angkatan->id]);
    strukturTahap($periode, $angkatan, [1]);

    $this->actingAs($admin)->postJson('/api/tagihan/generate', [
        'id_periode' => $periode->id,
        'id_angkatan' => $angkatan->id,
        'opsi_tahap' => 'all',
        'tanggal_tagihan' => '2024-10-10',
        'tanggal_jatuh_tempo' => '2024-10-31',
    ])->assertOk();

    $tagihan = Tagihan::where('id_semester', $periode->id)->firstOrFail();
    expect($tagihan->tanggal_tagihan->toDateString())->toBe('2024-10-10');
    expect($tagihan->tanggal_jatuh_tempo->toDateString())->toBe('2024-10-31');
});

it('rejects an API generate when no date can be determined', function () {
    $admin = adminUser('admin_keuangan');
    $periode = Semester::factory()->create(['tanggal_mulai' => null]);
    $angkatan = Semester::factory()->create();
    Mahasiswa::factory()->create(['id_semester_masuk' => $angkatan->id]);
    strukturTahap($periode, $angkatan, [1]);

    $this->actingAs($admin)->postJson('/api/tagihan/generate', [
        'id_periode' => $periode->id,
        'id_angkatan' => $angkatan->id,
        'opsi_tahap' => 'all',
    ])->assertStatus(422);

    expect(Tagihan::where('id_semester', $periode->id)->count())->toBe(0);
});

it('generates panel tagihan dated from the period and stores tahap in its column', function () {
    $admin = adminUser('admin_keuangan');
    $periode = Semester::factory()->create(['tanggal_mulai' => '2024-09-01']);
    $angkatan = Semester::factory()->create();
    $mahasiswa = Mahasiswa::factory()->create(['id_semester_masuk' => $angkatan->id]);
    strukturTahap($periode, $angkatan, [1, 2]);

    $component = Livewire::actingAs($admin)->test(Generate::class);
    $key = $component->instance()->groupedStrukturBiaya()->first()['key'];

    $component->call('openGenerateModal', $key)->call('generate');

    $tagihan = Tagihan::where('id_mahasiswa', $mahasiswa->id)->orderBy('tahap')->get();

    expect($tagihan->pluck('tahap')->all())->toBe([1, 2]);
    expect($tagihan[0]->tanggal_tagihan->toDateString())->toBe('2024-09-01');
    expect($tagihan[1]->tanggal_tagihan->toDateString())->toBe('2024-10-01');
    // Penanda teks lama tidak boleh ditulis lagi ke keterangan.
    expect($tagihan->pluck('keterangan')->filter(fn ($k) => str_contains((string) $k, '[TAHAP:')))->toBeEmpty();
});

it('blocks the panel generate when the period has no start date and none was typed', function () {
    $admin = adminUser('admin_keuangan');
    $periode = Semester::factory()->create(['tanggal_mulai' => null]);
    $angkatan = Semester::factory()->create();
    Mahasiswa::factory()->create(['id_semester_masuk' => $angkatan->id]);
    strukturTahap($periode, $angkatan, [1]);

    $component = Livewire::actingAs($admin)->test(Generate::class);
    $key = $component->instance()->groupedStrukturBiaya()->first()['key'];

    $component->call('openGenerateModal', $key)
        ->call('generate')
        ->assertHasErrors('tanggalTagihan');

    expect(Tagihan::where('id_semester', $periode->id)->count())->toBe(0);
});

it('generates from the panel using the typed date when the period has none', function () {
    $admin = adminUser('admin_keuangan');
    $periode = Semester::factory()->create(['tanggal_mulai' => null]);
    $angkatan = Semester::factory()->create();
    Mahasiswa::factory()->create(['id_semester_masuk' => $angkatan->id]);
    strukturTahap($periode, $angkatan, [1]);

    $component = Livewire::actingAs($admin)->test(Generate::class);
    $key = $component->instance()->groupedStrukturBiaya()->first()['key'];

    $component->call('openGenerateModal', $key)
        ->set('tanggalTagihan', '2024-03-01')
        ->call('generate')
        ->assertHasNoErrors();

    $tagihan = Tagihan::where('id_semester', $periode->id)->firstOrFail();
    expect($tagihan->tanggal_tagihan->toDateString())->toBe('2024-03-01');
    expect($tagihan->tanggal_jatuh_tempo->toDateString())->toBe('2024-03-15');
});
