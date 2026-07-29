<?php

use App\Livewire\Admin\Perkuliahan\Show;
use App\Models\Dosen;
use App\Models\Jadwal;
use App\Models\Kehadiran;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Perkuliahan;

/**
 * Collection::sortBy([$closure, $closure]) memanggil tiap closure sebagai comparator dua-argumen
 * ($a, $b), bukan sebagai pengambil nilai satu-argumen — dipakai secara salah di beberapa tempat
 * yang mengurutkan sesi perkuliahan secara kronologis untuk rekap kehadiran, sehingga pertemuan
 * bisa tampil dengan nomor urut yang salah begitu ada lebih dari satu sesi. Test ini memastikan
 * urutan tetap kronologis (waktu_mulai naik, id sebagai tie-break) di titik-titik yang memakainya.
 */
it('orders perkuliahan sessions chronologically in PerkuliahanController::getMyPerkuliahan (/api/perkuliahan/my)', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $kelas = Kelas::factory()->create(['id_dosen_pic' => $dosen->id]);
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);

    $sesiTerbaru = Perkuliahan::factory()->create(['id_jadwal' => $jadwal->id, 'waktu_mulai' => now()]);
    $sesiLama = Perkuliahan::factory()->create(['id_jadwal' => $jadwal->id, 'waktu_mulai' => now()->subWeeks(2)]);
    $sesiTengah = Perkuliahan::factory()->create(['id_jadwal' => $jadwal->id, 'waktu_mulai' => now()->subWeek()]);

    $response = $this->actingAs($dosenUser)->getJson('/api/perkuliahan/my?id_semester_masuk='.$kelas->id_semester);

    $response->assertOk();
    $sesi = $response->json('data.0.perkuliahan');

    expect($sesi)->toHaveCount(3);
    expect($sesi[0]['id'])->toBe($sesiLama->id);
    expect($sesi[0]['pertemuan_ke'])->toBe(1);
    expect($sesi[1]['id'])->toBe($sesiTengah->id);
    expect($sesi[1]['pertemuan_ke'])->toBe(2);
    expect($sesi[2]['id'])->toBe($sesiTerbaru->id);
    expect($sesi[2]['pertemuan_ke'])->toBe(3);
});

it('orders perkuliahan sessions chronologically in KehadiranController::getRekapByKelas (/api/kehadiran/rekap/kelas/{id})', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $kelas = Kelas::factory()->create(['id_dosen_pic' => $dosen->id]);
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);

    $sesiTerbaru = Perkuliahan::factory()->create(['id_jadwal' => $jadwal->id, 'waktu_mulai' => now()]);
    $sesiLama = Perkuliahan::factory()->create(['id_jadwal' => $jadwal->id, 'waktu_mulai' => now()->subWeeks(2)]);

    $mhs = Mahasiswa::factory()->create();
    Krs::factory()->create(['id_mahasiswa' => $mhs->id, 'id_kelas' => $kelas->id]);
    Kehadiran::create(['id_perkuliahan' => $sesiLama->id, 'id_mhs' => $mhs->id, 'status' => 'hadir']);
    Kehadiran::create(['id_perkuliahan' => $sesiTerbaru->id, 'id_mhs' => $mhs->id, 'status' => 'alfa']);

    $response = $this->actingAs($dosenUser)->getJson("/api/kehadiran/rekap/kelas/{$kelas->id}");

    $response->assertOk();
    $perkuliahan = $response->json('perkuliahan');

    expect($perkuliahan)->toHaveCount(2);
    expect($perkuliahan[0]['id'])->toBe($sesiLama->id);
    expect($perkuliahan[0]['pertemuan_ke'])->toBe(1);
    expect($perkuliahan[1]['id'])->toBe($sesiTerbaru->id);
    expect($perkuliahan[1]['pertemuan_ke'])->toBe(2);

    $mahasiswaRow = collect($response->json('mahasiswa'))->firstWhere('id_mahasiswa', $mhs->id);
    expect($mahasiswaRow['kehadiran'][1]['status'])->toBe('hadir');
    expect($mahasiswaRow['kehadiran'][2]['status'])->toBe('alfa');
});

it('orders perkuliahan sessions chronologically on the admin Perkuliahan rekap grid', function () {
    $admin = adminUser();
    $kelas = Kelas::factory()->create();
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelas->id]);

    $sesiTerbaru = Perkuliahan::factory()->create(['id_jadwal' => $jadwal->id, 'waktu_mulai' => now()]);
    $sesiLama = Perkuliahan::factory()->create(['id_jadwal' => $jadwal->id, 'waktu_mulai' => now()->subWeeks(2)]);

    $mhs = Mahasiswa::factory()->create();
    Krs::factory()->create(['id_mahasiswa' => $mhs->id, 'id_kelas' => $kelas->id, 'approved_at' => now()]);
    Kehadiran::create(['id_perkuliahan' => $sesiLama->id, 'id_mhs' => $mhs->id, 'status' => 'hadir']);
    Kehadiran::create(['id_perkuliahan' => $sesiTerbaru->id, 'id_mhs' => $mhs->id, 'status' => 'alfa']);

    $rekap = Livewire\Livewire::actingAs($admin)
        ->test(Show::class, ['id' => $kelas->id])
        ->instance()
        ->rekap();

    expect($rekap['perkuliahan'])->toHaveCount(2);
    expect($rekap['perkuliahan'][0]->id)->toBe($sesiLama->id);
    expect($rekap['perkuliahan'][0]->pertemuan_ke)->toBe(1);
    expect($rekap['perkuliahan'][1]->id)->toBe($sesiTerbaru->id);
    expect($rekap['perkuliahan'][1]->pertemuan_ke)->toBe(2);
});

it('forbids a dosen with no access when calling getMyPerkuliahan and getRekapByKelas is dosen-scoped', function () {
    $dosenUser = dosenUser();
    $kelasLuarAkses = Kelas::factory()->create();
    $jadwal = Jadwal::factory()->create(['id_kelas' => $kelasLuarAkses->id]);
    Perkuliahan::factory()->create(['id_jadwal' => $jadwal->id]);

    $response = $this->actingAs($dosenUser)->getJson("/api/kehadiran/rekap/kelas/{$kelasLuarAkses->id}");

    $response->assertStatus(403);
});
