<?php

use App\Livewire\Admin\Pembayaran\LaporanPelunasan;
use App\Models\Mahasiswa;
use App\Models\Pembayaran;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\Tagihan;
use Livewire\Livewire;

it('renders the laporan pelunasan page as a full page', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.keuangan.pembayaran.laporan-pelunasan'))
        ->assertOk()
        ->assertSee('Laporan Pelunasan Tagihan');
});

it('aggregates total tagihan and only counts approved payments toward pelunasan', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Dwi Kartika', 'nim' => '2023000900']);
    $tagihan = Tagihan::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'total' => 1000000]);
    Pembayaran::factory()->create(['id_tagihan' => $tagihan->id, 'nominal' => 400000, 'approved_at' => now()]);
    // Belum disetujui — tidak boleh ikut dihitung sebagai pembayaran disetujui.
    Pembayaran::factory()->create(['id_tagihan' => $tagihan->id, 'nominal' => 300000, 'approved_at' => null]);

    $component = Livewire::actingAs($admin)->test(LaporanPelunasan::class);
    $row = $component->instance()->render()->getData()['rows']->firstWhere('nim', '2023000900');

    expect($row->total_tagihan)->toBe(1000000.0);
    expect($row->total_pembayaran)->toBe(400000.0);
    expect($row->sisa)->toBe(600000.0);
    expect($row->persentase)->toBe(40.0);
});

it('excludes mahasiswa with no tagihan at all', function () {
    $admin = adminUser();
    Mahasiswa::factory()->create(['nama' => 'Tanpa Tagihan', 'nim' => '2023000901']);

    Livewire::actingAs($admin)
        ->test(LaporanPelunasan::class)
        ->assertDontSee('Tanpa Tagihan');
});

it('includes mahasiswa with tagihan but zero approved payments', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Belum Bayar Sama Sekali', 'nim' => '2023000902']);
    Tagihan::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'total' => 500000]);

    Livewire::actingAs($admin)
        ->test(LaporanPelunasan::class)
        ->assertSee('Belum Bayar Sama Sekali')
        ->assertSee('Rp0');
});

it('filters by prodi', function () {
    $admin = adminUser();
    $prodiA = Prodi::factory()->create(['nama' => 'Prodi Pelunasan A']);
    $prodiB = Prodi::factory()->create(['nama' => 'Prodi Pelunasan B']);
    $mahasiswaA = Mahasiswa::factory()->create(['id_prodi' => $prodiA->id, 'nama' => 'Mahasiswa Pelunasan A']);
    $mahasiswaB = Mahasiswa::factory()->create(['id_prodi' => $prodiB->id, 'nama' => 'Mahasiswa Pelunasan B']);
    Tagihan::factory()->create(['id_mahasiswa' => $mahasiswaA->id]);
    Tagihan::factory()->create(['id_mahasiswa' => $mahasiswaB->id]);

    Livewire::actingAs($admin)
        ->test(LaporanPelunasan::class)
        ->set('filterProdi', (string) $prodiA->id)
        ->assertSee('Mahasiswa Pelunasan A')
        ->assertDontSee('Mahasiswa Pelunasan B');
});

it('filters by tagihan semester', function () {
    $admin = adminUser();
    $semesterA = Semester::factory()->create();
    $semesterB = Semester::factory()->create();
    $mahasiswaA = Mahasiswa::factory()->create(['nama' => 'Mahasiswa Semester A']);
    $mahasiswaB = Mahasiswa::factory()->create(['nama' => 'Mahasiswa Semester B']);
    Tagihan::factory()->create(['id_mahasiswa' => $mahasiswaA->id, 'id_semester' => $semesterA->id]);
    Tagihan::factory()->create(['id_mahasiswa' => $mahasiswaB->id, 'id_semester' => $semesterB->id]);

    Livewire::actingAs($admin)
        ->test(LaporanPelunasan::class)
        ->set('filterSemester', (string) $semesterA->id)
        ->assertSee('Mahasiswa Semester A')
        ->assertDontSee('Mahasiswa Semester B');
});

it('searches by nama or nim', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Farhan Search Target', 'nim' => '2023000903']);
    $lain = Mahasiswa::factory()->create(['nama' => 'Lain Sama Sekali']);
    Tagihan::factory()->create(['id_mahasiswa' => $mahasiswa->id]);
    Tagihan::factory()->create(['id_mahasiswa' => $lain->id]);

    Livewire::actingAs($admin)
        ->test(LaporanPelunasan::class)
        ->set('search', '2023000903')
        ->assertSee('Farhan Search Target')
        ->assertDontSee('Lain Sama Sekali');
});

it('exports the report to an xlsx file', function () {
    $admin = adminUser();
    $mahasiswa = Mahasiswa::factory()->create();
    Tagihan::factory()->create(['id_mahasiswa' => $mahasiswa->id]);

    $response = Livewire::actingAs($admin)
        ->test(LaporanPelunasan::class)
        ->call('exportExcel');

    $response->assertFileDownloaded();
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.keuangan.pembayaran.laporan-pelunasan'))->assertRedirect(route('login'));
});
