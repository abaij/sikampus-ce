<?php

use App\Livewire\Dosen\Jadwal\Detail;
use App\Models\Dosen;
use App\Models\Jadwal;
use App\Models\JadwalDosen;
use App\Models\JenisKuliah;
use App\Models\Kelas;
use App\Models\KelasDosen;
use App\Models\Ruangan;
use App\Models\User;
use Livewire\Livewire;

it('redirects unauthenticated users to the login page', function () {
    $kelas = Kelas::factory()->create();
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);

    $this->get(route('dosen.jadwal.detail', ['kelasId' => $kelas->id, 'jadwalId' => $jadwal->id]))
        ->assertRedirect(route('login'));
});

it('forbids a non-dosen user', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa']);
    $kelas = Kelas::factory()->create();
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);

    $this->actingAs($mahasiswa)
        ->get(route('dosen.jadwal.detail', ['kelasId' => $kelas->id, 'jadwalId' => $jadwal->id]))
        ->assertForbidden();
});

it('forbids a dosen with no relation to the kelas, the pic assignment, or the jadwal_dosen row', function () {
    $dosenUser = dosenUser();
    $kelas = Kelas::factory()->create();
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);

    Livewire::actingAs($dosenUser)
        ->test(Detail::class, ['kelasId' => $kelas->id, 'jadwalId' => $jadwal->id])
        ->assertForbidden();
});

it('allows access via an active jadwal_dosen row even without a kelas_dosen row', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $kelas = Kelas::factory()->create();
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id, 'bahasan' => 'Bahasan awal']);
    JadwalDosen::create(['id_jadwal' => $jadwal->id, 'id_dosen' => $dosen->id, 'status' => 'active']);

    Livewire::actingAs($dosenUser)
        ->test(Detail::class, ['kelasId' => $kelas->id, 'jadwalId' => $jadwal->id])
        ->assertOk()
        ->assertSet('bahasan', 'Bahasan awal');
});

it('returns 404 when the jadwal does not belong to the given kelasId', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $kelasBenar = Kelas::factory()->create();
    $kelasSalah = Kelas::factory()->create();
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelasBenar->id]);
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelasBenar->id, 'is_pic' => true]);

    Livewire::actingAs($dosenUser)
        ->test(Detail::class, ['kelasId' => $kelasSalah->id, 'jadwalId' => $jadwal->id])
        ->assertStatus(404);
});

it('updates hari, tanggal, ruangan, and jenis kuliah for the pic dosen', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $kelas = Kelas::factory()->create();
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id, 'hari' => 'senin']);
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelas->id, 'is_pic' => true]);

    $ruangan = Ruangan::factory()->create();
    $jenisKuliah = JenisKuliah::factory()->create();

    Livewire::actingAs($dosenUser)
        ->test(Detail::class, ['kelasId' => $kelas->id, 'jadwalId' => $jadwal->id])
        ->call('startEdit')
        ->set('hari', 'rabu')
        ->set('id_ruangan', (string) $ruangan->id)
        ->set('id_jenis_kuliah', (string) $jenisKuliah->id)
        ->call('saveJadwal')
        ->assertHasNoErrors();

    $jadwal->refresh();
    expect($jadwal->hari)->toBe('rabu');
    expect($jadwal->id_ruangan)->toBe($ruangan->id);
    expect($jadwal->id_jenis_kuliah)->toBe($jenisKuliah->id);
});

it('saves the bahasan field independently of the jadwal edit form', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $kelas = Kelas::factory()->create();
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelas->id, 'is_pic' => true]);

    Livewire::actingAs($dosenUser)
        ->test(Detail::class, ['kelasId' => $kelas->id, 'jadwalId' => $jadwal->id])
        ->set('bahasan', 'Membahas rekursi dan iterasi')
        ->call('saveBahasan')
        ->assertHasNoErrors();

    expect($jadwal->fresh()->bahasan)->toBe('Membahas rekursi dan iterasi');
});
