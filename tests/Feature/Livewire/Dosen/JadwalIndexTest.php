<?php

use App\Livewire\Dosen\Jadwal\Index;
use App\Models\Dosen;
use App\Models\Jadwal;
use App\Models\JadwalDosen;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\User;
use Livewire\Livewire;

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('dosen.jadwal'))->assertRedirect(route('login'));
});

it('forbids a non-dosen user', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa']);

    $this->actingAs($mahasiswa)->get(route('dosen.jadwal'))->assertForbidden();
});

it('lists the active jadwal_dosen rows for the logged-in dosen as a flat table', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semesterAktif = Semester::factory()->active()->create();
    $kelas = Kelas::factory()->create(['id_semester' => $semesterAktif->id]);
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id, 'hari' => 'senin', 'is_active' => true]);
    JadwalDosen::create(['id_jadwal' => $jadwal->id, 'id_dosen' => $dosen->id, 'status' => 'active']);

    $rows = Livewire::actingAs($dosenUser)
        ->test(Index::class)
        ->instance()
        ->jadwalRows();

    expect($rows)->toHaveCount(1);
    expect($rows->first()->jadwal->id)->toBe($jadwal->id);
});

it('sorts rows by hari then jam_mulai, not by a naive combined numeric key', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semesterAktif = Semester::factory()->active()->create();
    $kelas = Kelas::factory()->create(['id_semester' => $semesterAktif->id]);

    // Senin sore harus tetap terurut sebelum Selasa pagi, walau formula angka tunggal
    // ($hariNum * 10000 + jamMulai) yang dipakai JadwalDosenController::getMyJadwal akan
    // salah urut di kasus ini (lihat catatan di Index::jadwalRows()).
    $jadwalSeninSore = Jadwal::factory()->create(['id_kelas' => $kelas->id, 'hari' => 'senin', 'jam_mulai' => '18:00', 'is_active' => true]);
    $jadwalSelasaPagi = Jadwal::factory()->create(['id_kelas' => $kelas->id, 'hari' => 'selasa', 'jam_mulai' => '07:00', 'is_active' => true]);

    JadwalDosen::create(['id_jadwal' => $jadwalSeninSore->id, 'id_dosen' => $dosen->id, 'status' => 'active']);
    JadwalDosen::create(['id_jadwal' => $jadwalSelasaPagi->id, 'id_dosen' => $dosen->id, 'status' => 'active']);

    $rows = Livewire::actingAs($dosenUser)->test(Index::class)->instance()->jadwalRows();

    expect($rows->pluck('id_jadwal')->all())->toBe([$jadwalSeninSore->id, $jadwalSelasaPagi->id]);
});

it('filters jadwal rows by the selected semester', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semesterA = Semester::factory()->create();
    $semesterB = Semester::factory()->create();
    $kelasA = Kelas::factory()->create(['id_semester' => $semesterA->id]);
    $kelasB = Kelas::factory()->create(['id_semester' => $semesterB->id]);
    $jadwalA = Jadwal::factory()->create(['id_kelas' => $kelasA->id, 'is_active' => true]);
    $jadwalB = Jadwal::factory()->create(['id_kelas' => $kelasB->id, 'is_active' => true]);
    JadwalDosen::create(['id_jadwal' => $jadwalA->id, 'id_dosen' => $dosen->id, 'status' => 'active']);
    JadwalDosen::create(['id_jadwal' => $jadwalB->id, 'id_dosen' => $dosen->id, 'status' => 'active']);

    $rows = Livewire::actingAs($dosenUser)
        ->test(Index::class)
        ->set('filterSemester', (string) $semesterB->id)
        ->instance()
        ->jadwalRows();

    expect($rows->pluck('id_jadwal')->all())->toBe([$jadwalB->id]);
});

it('excludes jadwal_dosen rows that are not active', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semesterAktif = Semester::factory()->active()->create();
    $kelas = Kelas::factory()->create(['id_semester' => $semesterAktif->id]);
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id, 'hari' => 'senin', 'is_active' => true]);
    JadwalDosen::create(['id_jadwal' => $jadwal->id, 'id_dosen' => $dosen->id, 'status' => 'inactive']);

    $rows = Livewire::actingAs($dosenUser)->test(Index::class)->instance()->jadwalRows();

    expect($rows)->toHaveCount(0);
});

it('links each row to the jadwal detail page', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semesterAktif = Semester::factory()->active()->create();
    $kelas = Kelas::factory()->create(['id_semester' => $semesterAktif->id]);
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id, 'hari' => 'senin', 'is_active' => true]);
    JadwalDosen::create(['id_jadwal' => $jadwal->id, 'id_dosen' => $dosen->id, 'status' => 'active']);

    $this->actingAs($dosenUser)
        ->get(route('dosen.jadwal'))
        ->assertOk()
        ->assertSee(route('dosen.jadwal.detail', ['kelasId' => $kelas->id, 'jadwalId' => $jadwal->id, 'id_semester' => $semesterAktif->id]), false);
});
