<?php

use App\Livewire\Admin\Dashboard;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Semester;
use App\Models\StatusAkademik;
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
