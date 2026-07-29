<?php

use App\Livewire\Dosen\Dashboard;
use App\Models\Dosen;
use App\Models\DosenWali;
use App\Models\Jadwal;
use App\Models\JadwalDosen;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\KurikulumMatkul;
use App\Models\Mahasiswa;
use App\Models\Matkul;
use App\Models\Semester;
use Livewire\Livewire;

it('renders the dashboard for a dosen', function () {
    $dosenUser = dosenUser();

    Livewire::actingAs($dosenUser)
        ->test(Dashboard::class)
        ->assertOk();
});

it('sums sks diajukan/disetujui only from active-semester krs of active bimbingan mahasiswa', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semesterAktif = Semester::factory()->active()->create();
    $semesterLain = Semester::factory()->create();

    $matkulDisetujui = Matkul::factory()->create(['sks' => 3]);
    $matkulBelum = Matkul::factory()->create(['sks' => 2]);
    $kurikulumMatkulDisetujui = KurikulumMatkul::factory()->create(['id_matkul' => $matkulDisetujui->id]);
    $kurikulumMatkulBelum = KurikulumMatkul::factory()->create(['id_matkul' => $matkulBelum->id]);

    $kelasDisetujui = Kelas::factory()->create(['id_kurikulum_matkul' => $kurikulumMatkulDisetujui->id, 'id_semester' => $semesterAktif->id]);
    $kelasBelum = Kelas::factory()->create(['id_kurikulum_matkul' => $kurikulumMatkulBelum->id, 'id_semester' => $semesterAktif->id]);
    $kelasSemesterLain = Kelas::factory()->create(['id_semester' => $semesterLain->id]);

    $mahasiswa = Mahasiswa::factory()->create();
    DosenWali::factory()->create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $mahasiswa->id, 'status' => 'active']);

    Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelasDisetujui->id, 'approved_at' => now()]);
    Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelasBelum->id, 'approved_at' => null]);
    // KRS di semester lain tidak boleh ikut terhitung.
    Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelasSemesterLain->id, 'approved_at' => now()]);

    Livewire::actingAs($dosenUser)
        ->test(Dashboard::class)
        ->assertSet('dosenId', $dosen->id);

    $component = Livewire::actingAs($dosenUser)->test(Dashboard::class);
    $stats = $component->instance()->krsStats();

    expect($stats['diajukan'])->toBe(5)
        ->and($stats['disetujui'])->toBe(3)
        ->and($stats['belum_disetujui'])->toBe(2);
});

it('only counts jadwal mengajar that falls within the current week', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semesterAktif = Semester::factory()->active()->create();
    $kelas = Kelas::factory()->create(['id_semester' => $semesterAktif->id]);

    $hariIni = strtolower(now()->translatedFormat('l'));
    $hariMap = ['monday' => 'senin', 'tuesday' => 'selasa', 'wednesday' => 'rabu', 'thursday' => 'kamis', 'friday' => 'jumat', 'saturday' => 'sabtu', 'sunday' => 'minggu'];
    $hariSekarang = $hariMap[$hariIni] ?? 'senin';

    $jadwalMingguIni = Jadwal::factory()->create(['id_kelas' => $kelas->id, 'hari' => $hariSekarang, 'tanggal' => null]);
    JadwalDosen::create(['id_jadwal' => $jadwalMingguIni->id, 'id_dosen' => $dosen->id, 'status' => 'active']);

    // Jadwal dengan tanggal eksplisit jauh di masa depan tidak boleh ikut minggu ini.
    $jadwalMingguDepan = Jadwal::factory()->create(['id_kelas' => $kelas->id, 'tanggal' => now()->addWeeks(3)]);
    JadwalDosen::create(['id_jadwal' => $jadwalMingguDepan->id, 'id_dosen' => $dosen->id, 'status' => 'active']);

    $rows = Livewire::actingAs($dosenUser)->test(Dashboard::class)->instance()->jadwalMingguIni();

    expect($rows)->toHaveCount(1);
    expect($rows[0]['id_jadwal'])->toBe($jadwalMingguIni->id);
});
