<?php

use App\Livewire\Dosen\Arsip\Index;
use App\Models\Dosen;
use App\Models\Jadwal;
use App\Models\JadwalDosen;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\User;
use Livewire\Livewire;

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('dosen.arsip'))->assertRedirect(route('login'));
});

it('forbids a non-dosen user', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa']);

    $this->actingAs($mahasiswa)->get(route('dosen.arsip'))->assertForbidden();
});

it('lists a unique kelas from active jadwal_dosen rows, filtered by semester', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semesterAktif = Semester::factory()->active()->create();
    $semesterLain = Semester::factory()->create();

    $kelasAktif = Kelas::factory()->create(['id_semester' => $semesterAktif->id]);
    $jadwal1 = Jadwal::factory()->create(['id_kelas' => $kelasAktif->id]);
    $jadwal2 = Jadwal::factory()->create(['id_kelas' => $kelasAktif->id]);
    JadwalDosen::create(['id_jadwal' => $jadwal1->id, 'id_dosen' => $dosen->id, 'status' => 'active']);
    JadwalDosen::create(['id_jadwal' => $jadwal2->id, 'id_dosen' => $dosen->id, 'status' => 'active']);

    $kelasLain = Kelas::factory()->create(['id_semester' => $semesterLain->id]);
    $jadwalLain = Jadwal::factory()->create(['id_kelas' => $kelasLain->id]);
    JadwalDosen::create(['id_jadwal' => $jadwalLain->id, 'id_dosen' => $dosen->id, 'status' => 'active']);

    // jadwal nonaktif -> tidak muncul
    $kelasNonaktif = Kelas::factory()->create(['id_semester' => $semesterAktif->id]);
    $jadwalNonaktif = Jadwal::factory()->create(['id_kelas' => $kelasNonaktif->id]);
    JadwalDosen::create(['id_jadwal' => $jadwalNonaktif->id, 'id_dosen' => $dosen->id, 'status' => 'inactive']);

    $rows = Livewire::actingAs($dosenUser)->test(Index::class)->instance()->rows();

    expect($rows)->toHaveCount(1);
    expect($rows[0]->id)->toBe($kelasAktif->id);

    $rows = Livewire::actingAs($dosenUser)->test(Index::class)->set('filterSemester', '')->instance()->rows();
    expect($rows)->toHaveCount(2);
});

it('does not list kelas where the dosen has no jadwal', function () {
    $dosenUser = dosenUser();
    Kelas::factory()->create();

    $rows = Livewire::actingAs($dosenUser)->test(Index::class)->instance()->rows();
    expect($rows)->toHaveCount(0);
});
