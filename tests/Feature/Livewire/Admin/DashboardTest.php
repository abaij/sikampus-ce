<?php

use App\Livewire\Admin\Dashboard;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Pembayaran;
use App\Models\Role;
use App\Models\Semester;
use App\Models\StatusAkademik;
use App\Models\Tagihan;
use Livewire\Livewire;

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

it('renders the empty state for every widget when there is no data yet', function () {
    $admin = adminUser();

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Ringkasan Kampus')
        ->assertSee('Tidak ada antrian tindakan saat ini.')
        ->assertSee('Semua kelas yang sudah selesai perkuliahannya sudah difinalisasi nilainya.')
        ->assertSee('Tidak ada data untuk ditampilkan');
});

it('shows the active mahasiswa count for the active semester', function () {
    $admin = adminUser();
    Semester::factory()->active()->create(['nama' => 'Ganjil 2025/2026']);
    $statusAktif = StatusAkademik::factory()->create(['nama' => 'Aktif']);
    Mahasiswa::factory()->count(3)->create(['id_status_akademik' => $statusAktif->id]);

    Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->assertSee('Ganjil 2025/2026')
        ->assertSee('3');
});

it('shows the krs antrian count linked to the krs page', function () {
    $admin = adminUser();
    Krs::factory()->count(2)->create(['approved_at' => null]);

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('KRS menunggu approval')
        ->assertSee(route('admin.akademik.krs'), false);
});

it('renders the quick links pointing at the real akademik routes', function () {
    $admin = adminUser();

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Jadwal Kuliah')
        ->assertSee('Kurikulum')
        ->assertSee('Perkuliahan')
        ->assertSee(route('admin.akademik.jadwal'), false)
        ->assertSee(route('admin.akademik.kurikulum'), false)
        ->assertSee(route('admin.akademik.perkuliahan'), false);
});

it('shows both academic and finance sections for a superadmin', function () {
    $admin = adminUser();

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Mahasiswa Aktif')
        ->assertSee('Ringkasan Keuangan');
});

it('shows only the academic section for a user with only the Akademik role', function () {
    $admin = adminUser('admin_akademik');

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Mahasiswa Aktif')
        ->assertDontSee('Ringkasan Keuangan');
});

it('shows only the finance section for a user with only the Keuangan role', function () {
    $admin = adminUser('admin_keuangan');

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('Mahasiswa Aktif')
        ->assertSee('Ringkasan Keuangan');
});

it('shows both sections for a user holding both Akademik and Keuangan roles', function () {
    $admin = adminUser('admin_akademik');
    $keuanganRole = Role::firstOrCreate(['name' => 'Keuangan', 'guard_name' => 'web'], ['code' => 'keuangan']);
    $admin->assignRole($keuanganRole);

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Mahasiswa Aktif')
        ->assertSee('Ringkasan Keuangan');
});

it('computes finance stats from real tagihan and approved pembayaran, only counting approved payments', function () {
    $admin = adminUser('admin_keuangan');
    $semester = Semester::factory()->create();
    $tagihan = Tagihan::factory()->create(['id_semester' => $semester->id, 'total' => 1000000]);
    Pembayaran::factory()->create(['id_tagihan' => $tagihan->id, 'nominal' => 400000, 'approved_at' => now()]);
    Pembayaran::factory()->create(['id_tagihan' => $tagihan->id, 'nominal' => 300000, 'approved_at' => null]);

    $component = Livewire::actingAs($admin)->test(Dashboard::class);
    $stats = $component->instance()->keuanganStats()['ringkasan'];

    expect($stats['total_tagihan'])->toBe(1000000.0);
    expect($stats['total_terbayar'])->toBe(400000.0);
    expect($stats['total_piutang'])->toBe(600000.0);
    expect($stats['pembayaran_menunggu_approval_count'])->toBe(1);
    expect($stats['pembayaran_menunggu_approval_total'])->toBe(300000.0);
});
