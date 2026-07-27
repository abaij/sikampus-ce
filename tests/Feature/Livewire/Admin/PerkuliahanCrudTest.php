<?php

use App\Livewire\Admin\Perkuliahan\Index;
use App\Livewire\Admin\Perkuliahan\Show;
use App\Models\Jadwal;
use App\Models\Kehadiran;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\KurikulumMatkul;
use App\Models\Mahasiswa;
use App\Models\Matkul;
use App\Models\Perkuliahan;
use App\Models\Prodi;
use Livewire\Livewire;

it('renders index and show page', function () {
    $admin = adminUser();
    $matkul = Matkul::factory()->create(['nama' => 'Pemrograman Web', 'kode' => 'IF101']);
    $kurikulumMatkul = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id]);
    $kelas = Kelas::factory()->create(['id_kurikulum_matkul' => $kurikulumMatkul->id]);
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);
    Perkuliahan::factory()->create(['id_jadwal' => $jadwal->id]);

    $this->actingAs($admin)->get(route('admin.akademik.perkuliahan'))->assertOk()->assertSee('Pemrograman Web');
    $this->actingAs($admin)->get(route('admin.akademik.perkuliahan.show', $kelas->id))->assertOk()->assertSee('Pemrograman Web');
});

it('shows the jadwal count and dosen pic on the index table', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    Jadwal::factory()->count(3)->create(['id_kelas' => $kelas->id]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('3');
});

it('shows perkuliahan sessions with attendance counts grouped by jadwal', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);
    $perkuliahan = Perkuliahan::factory()->create(['id_jadwal' => $jadwal->id, 'materi' => 'Pengenalan Aljabar']);

    $mahasiswa1 = Mahasiswa::factory()->create();
    $mahasiswa2 = Mahasiswa::factory()->create();
    Krs::factory()->create(['id_kelas' => $kelas->id, 'id_mahasiswa' => $mahasiswa1->id, 'approved_at' => now()]);
    Krs::factory()->create(['id_kelas' => $kelas->id, 'id_mahasiswa' => $mahasiswa2->id, 'approved_at' => now()]);
    Kehadiran::factory()->create(['id_perkuliahan' => $perkuliahan->id, 'id_mhs' => $mahasiswa1->id, 'status' => 'hadir']);
    Kehadiran::factory()->create(['id_perkuliahan' => $perkuliahan->id, 'id_mhs' => $mahasiswa2->id, 'status' => 'alfa']);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $kelas->id])
        ->call('toggleJadwal', $jadwal->id)
        ->assertSee('Pengenalan Aljabar')
        ->assertSee('2') // jumlah mahasiswa
        ->assertSet('jumlahMahasiswa', 2);
});

it('shows the rekap kehadiran grid when the modal is opened', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);
    $perkuliahan = Perkuliahan::factory()->create(['id_jadwal' => $jadwal->id]);
    $mahasiswa = Mahasiswa::factory()->create(['nim' => '2021001', 'nama' => 'Budi Santoso']);
    Krs::factory()->create(['id_kelas' => $kelas->id, 'id_mahasiswa' => $mahasiswa->id, 'approved_at' => now()]);
    Kehadiran::factory()->create(['id_perkuliahan' => $perkuliahan->id, 'id_mhs' => $mahasiswa->id, 'status' => 'hadir']);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $kelas->id])
        ->call('openRekap')
        ->assertSet('showRekapModal', true)
        ->assertSee('2021001')
        ->assertSee('Budi Santoso');
});

it('exports the rekap kehadiran as a downloadable xlsx file', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);
    $perkuliahan = Perkuliahan::factory()->create(['id_jadwal' => $jadwal->id]);
    $mahasiswa = Mahasiswa::factory()->create();
    Krs::factory()->create(['id_kelas' => $kelas->id, 'id_mahasiswa' => $mahasiswa->id, 'approved_at' => now()]);
    Kehadiran::factory()->create(['id_perkuliahan' => $perkuliahan->id, 'id_mhs' => $mahasiswa->id, 'status' => 'hadir']);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $kelas->id])
        ->call('exportKehadiran')
        ->assertFileDownloaded();
});

it('admin dengan scope prodi hanya melihat kelas miliknya di daftar perkuliahan', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $matkulA = Matkul::factory()->create(['nama' => 'Kelas Prodi A']);
    $matkulB = Matkul::factory()->create(['nama' => 'Kelas Prodi B']);
    Kelas::factory()->create([
        'id_prodi' => $prodiA->id,
        'id_kurikulum_matkul' => KurikulumMatkul::factory()->create(['id_matkul' => $matkulA->id]),
    ]);
    Kelas::factory()->create([
        'id_prodi' => $prodiB->id,
        'id_kurikulum_matkul' => KurikulumMatkul::factory()->create(['id_matkul' => $matkulB->id]),
    ]);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Kelas Prodi A')
        ->assertDontSee('Kelas Prodi B');
});

it('admin dengan scope prodi tidak bisa membuka detail kelas di luar scope-nya', function () {
    $prodiA = Prodi::factory()->create();
    $prodiB = Prodi::factory()->create();
    $kelasB = Kelas::factory()->create(['id_prodi' => $prodiB->id]);

    $admin = adminUser('admin_akademik');
    scopeAdminToProdi($admin, $prodiA->id);

    Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $kelasB->id])
        ->assertStatus(403);
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.akademik.perkuliahan'))->assertRedirect(route('login'));
});
