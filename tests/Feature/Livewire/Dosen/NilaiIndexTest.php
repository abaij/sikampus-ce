<?php

use App\Livewire\Dosen\Nilai\Index;
use App\Models\Dosen;
use App\Models\Jadwal;
use App\Models\JadwalDosen;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\KurikulumMatkul;
use App\Models\Mahasiswa;
use App\Models\Matkul;
use App\Models\Semester;
use App\Models\User;
use Livewire\Livewire;

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('dosen.nilai'))->assertRedirect(route('login'));
});

it('forbids a non-dosen user', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa']);

    $this->actingAs($mahasiswa)->get(route('dosen.nilai'))->assertForbidden();
});

it('lists a kelas where the dosen is pic, with the mahasiswa count', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semesterAktif = Semester::factory()->active()->create();
    $matkul = Matkul::factory()->create(['kode' => 'IF301', 'nama' => 'Kecerdasan Buatan']);
    $km = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id]);
    $kelas = Kelas::factory()->create(['id_kurikulum_matkul' => $km->id, 'id_semester' => $semesterAktif->id, 'id_dosen_pic' => $dosen->id]);

    $mhs = Mahasiswa::factory()->create();
    Krs::factory()->create(['id_mahasiswa' => $mhs->id, 'id_kelas' => $kelas->id]);

    $rows = Livewire::actingAs($dosenUser)->test(Index::class)->instance()->rows();

    expect($rows)->toHaveCount(1);
    expect($rows[0]['kode_matkul'])->toBe('IF301');
    expect($rows[0]['jumlah_mahasiswa'])->toBe(1);
});

it('lists a kelas via an active jadwal_dosen row even without being the kelas pic', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semesterAktif = Semester::factory()->active()->create();
    $kelas = Kelas::factory()->create(['id_semester' => $semesterAktif->id]);
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);
    JadwalDosen::create(['id_jadwal' => $jadwal->id, 'id_dosen' => $dosen->id, 'status' => 'active']);

    $rows = Livewire::actingAs($dosenUser)->test(Index::class)->instance()->rows();

    expect($rows)->toHaveCount(1);
});

it('only lists kelas from the active semester', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semesterAktif = Semester::factory()->active()->create();
    $semesterLain = Semester::factory()->create();
    Kelas::factory()->create(['id_semester' => $semesterAktif->id, 'id_dosen_pic' => $dosen->id]);
    Kelas::factory()->create(['id_semester' => $semesterLain->id, 'id_dosen_pic' => $dosen->id]);

    $rows = Livewire::actingAs($dosenUser)->test(Index::class)->instance()->rows();

    expect($rows)->toHaveCount(1);
});
