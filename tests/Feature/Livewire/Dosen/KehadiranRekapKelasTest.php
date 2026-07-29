<?php

use App\Livewire\Dosen\Kehadiran\RekapKelas;
use App\Models\Dosen;
use App\Models\Jadwal;
use App\Models\Kehadiran;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Perkuliahan;
use Livewire\Livewire;

it('redirects unauthenticated users to the login page', function () {
    $kelas = Kelas::factory()->create();

    $this->get(route('dosen.kehadiran.rekap', $kelas->id))->assertRedirect(route('login'));
});

it('forbids a dosen with no access to the kelas', function () {
    $dosenUser = dosenUser();
    $kelas = Kelas::factory()->create();

    Livewire::actingAs($dosenUser)->test(RekapKelas::class, ['id' => $kelas->id])->assertForbidden();
});

it('builds the attendance matrix with one column per pertemuan in chronological order', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $kelas = Kelas::factory()->create(['id_dosen_pic' => $dosen->id]);
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);

    $p1 = Perkuliahan::factory()->create(['id_jadwal' => $jadwal->id, 'waktu_mulai' => now()->subWeeks(2)]);
    $p2 = Perkuliahan::factory()->create(['id_jadwal' => $jadwal->id, 'waktu_mulai' => now()->subWeek()]);

    $mhs = Mahasiswa::factory()->create(['nim' => '20230001']);
    Krs::factory()->create(['id_mahasiswa' => $mhs->id, 'id_kelas' => $kelas->id]);
    Kehadiran::create(['id_perkuliahan' => $p1->id, 'id_mhs' => $mhs->id, 'status' => 'hadir']);
    Kehadiran::create(['id_perkuliahan' => $p2->id, 'id_mhs' => $mhs->id, 'status' => 'izin', 'keterangan' => 'Acara keluarga']);

    $rekap = Livewire::actingAs($dosenUser)->test(RekapKelas::class, ['id' => $kelas->id])->instance()->rekap();

    expect($rekap['perkuliahan'])->toHaveCount(2);
    expect($rekap['perkuliahan'][0]->pertemuan_ke)->toBe(1);
    expect($rekap['mahasiswa'])->toHaveCount(1);
    expect($rekap['mahasiswa'][0]->kehadiran[1]['status'])->toBe('hadir');
    expect($rekap['mahasiswa'][0]->kehadiran[2]['status'])->toBe('izin');
});
