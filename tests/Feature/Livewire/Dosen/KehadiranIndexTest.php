<?php

use App\Livewire\Dosen\Kehadiran\Index;
use App\Models\Dosen;
use App\Models\Jadwal;
use App\Models\JadwalDosen;
use App\Models\Kehadiran;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Perkuliahan;
use App\Models\Semester;
use App\Models\User;
use Livewire\Livewire;

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('dosen.kehadiran'))->assertRedirect(route('login'));
});

it('forbids a non-dosen user', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa']);

    $this->actingAs($mahasiswa)->get(route('dosen.kehadiran'))->assertForbidden();
});

it('groups perkuliahan sessions by kelas for the selected semester, with pertemuan_ke and jumlah_hadir', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semesterAktif = Semester::factory()->active()->create();
    $kelas = Kelas::factory()->create(['id_semester' => $semesterAktif->id, 'id_dosen_pic' => $dosen->id]);
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);

    $p1 = Perkuliahan::factory()->create(['id_jadwal' => $jadwal->id, 'waktu_mulai' => now()->subWeek()]);
    $p2 = Perkuliahan::factory()->create(['id_jadwal' => $jadwal->id, 'waktu_mulai' => now()]);

    $mhs = Mahasiswa::factory()->create();
    Krs::factory()->create(['id_mahasiswa' => $mhs->id, 'id_kelas' => $kelas->id]);
    Kehadiran::factory()->create(['id_perkuliahan' => $p1->id, 'id_mhs' => $mhs->id, 'status' => 'hadir']);

    $rows = Livewire::actingAs($dosenUser)->test(Index::class)->instance()->rows();

    expect($rows)->toHaveCount(1);
    expect($rows[0]['kelas']->id)->toBe($kelas->id);
    expect($rows[0]['perkuliahan'])->toHaveCount(2);
    expect($rows[0]['perkuliahan'][0]['pertemuan_ke'])->toBe(1);
    expect($rows[0]['perkuliahan'][0]['id'])->toBe($p1->id);
    expect($rows[0]['perkuliahan'][0]['jumlah_hadir'])->toBe(1);
    expect($rows[0]['perkuliahan'][1]['jumlah_hadir'])->toBe(0);
});

it('lists kelas via an active jadwal_dosen row even without being the kelas pic', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semesterAktif = Semester::factory()->active()->create();
    $kelas = Kelas::factory()->create(['id_semester' => $semesterAktif->id]);
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);
    JadwalDosen::create(['id_jadwal' => $jadwal->id, 'id_dosen' => $dosen->id, 'status' => 'active']);
    Perkuliahan::factory()->create(['id_jadwal' => $jadwal->id]);

    $rows = Livewire::actingAs($dosenUser)->test(Index::class)->instance()->rows();
    expect($rows)->toHaveCount(1);
});

it('does not expose rekap data for a kelas the dosen has no access to', function () {
    $dosenUser = dosenUser();
    $kelas = Kelas::factory()->create();

    $rekap = Livewire::actingAs($dosenUser)
        ->test(Index::class)
        ->call('openRekapModal', $kelas->id)
        ->instance()
        ->rekap();

    expect($rekap)->toBeNull();
});
