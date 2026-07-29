<?php

use App\Livewire\Dosen\Krs\Index;
use App\Models\Dosen;
use App\Models\DosenWali;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\KurikulumMatkul;
use App\Models\Mahasiswa;
use App\Models\Matkul;
use App\Models\Notifikasi;
use App\Models\Semester;
use App\Models\User;
use Livewire\Livewire;

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('dosen.krs'))->assertRedirect(route('login'));
});

it('forbids a non-dosen user', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa']);

    $this->actingAs($mahasiswa)->get(route('dosen.krs'))->assertForbidden();
});

it('only lists active bimbingan mahasiswa and computes their krs stats for the selected semester', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semesterAktif = Semester::factory()->active()->create();
    $matkulA = Matkul::factory()->create(['sks' => 3]);
    $matkulB = Matkul::factory()->create(['sks' => 2]);
    $kmA = KurikulumMatkul::factory()->create(['id_matkul' => $matkulA->id]);
    $kmB = KurikulumMatkul::factory()->create(['id_matkul' => $matkulB->id]);
    $kelasA = Kelas::factory()->create(['id_kurikulum_matkul' => $kmA->id, 'id_semester' => $semesterAktif->id]);
    $kelasB = Kelas::factory()->create(['id_kurikulum_matkul' => $kmB->id, 'id_semester' => $semesterAktif->id]);

    $mahasiswaBimbingan = Mahasiswa::factory()->create(['nama' => 'Aktif Bimbingan']);
    DosenWali::create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $mahasiswaBimbingan->id, 'status' => 'active']);
    Krs::factory()->create(['id_mahasiswa' => $mahasiswaBimbingan->id, 'id_kelas' => $kelasA->id, 'approved_at' => now()]);
    Krs::factory()->create(['id_mahasiswa' => $mahasiswaBimbingan->id, 'id_kelas' => $kelasB->id, 'approved_at' => null]);

    $mahasiswaNonaktif = Mahasiswa::factory()->create(['nama' => 'Nonaktif Bimbingan']);
    DosenWali::create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $mahasiswaNonaktif->id, 'status' => 'inactive']);

    $rows = Livewire::actingAs($dosenUser)->test(Index::class)->instance()->rows();

    expect($rows->total())->toBe(1);
    $stat = $rows->first()->statistik_krs;
    expect($stat['total'])->toBe(2)
        ->and($stat['diacc'])->toBe(1)
        ->and($stat['persentase_diacc'])->toBe(50.0);
});

it('filters the mahasiswa list by name or nim search', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $cocok = Mahasiswa::factory()->create(['nama' => 'Budi Santoso', 'nim' => '20240001']);
    $tidakCocok = Mahasiswa::factory()->create(['nama' => 'Siti Aminah', 'nim' => '20240002']);
    DosenWali::create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $cocok->id, 'status' => 'active']);
    DosenWali::create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $tidakCocok->id, 'status' => 'active']);

    $rows = Livewire::actingAs($dosenUser)->test(Index::class)->set('search', 'Budi')->instance()->rows();

    expect($rows->total())->toBe(1);
    expect($rows->first()->nama)->toBe('Budi Santoso');
});

it('opens the krs modal only for mahasiswa that are an active bimbingan of the dosen', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $bukanBimbingan = Mahasiswa::factory()->create();

    Livewire::actingAs($dosenUser)
        ->test(Index::class)
        ->call('openKrsModal', $bukanBimbingan->id)
        ->assertForbidden();
});

it('approves selected krs, sets approved_by/approved_at, and notifies the mahasiswa', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $userMahasiswa = User::factory()->create(['role' => 'mahasiswa']);
    $mahasiswa = Mahasiswa::factory()->create(['id_user' => $userMahasiswa->id]);
    DosenWali::create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $mahasiswa->id, 'status' => 'active']);

    $kelas = Kelas::factory()->create();
    $krsPending = Krs::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_kelas' => $kelas->id, 'approved_at' => null]);

    $component = Livewire::actingAs($dosenUser)
        ->test(Index::class)
        ->call('openKrsModal', $mahasiswa->id)
        ->set('selectedKrsIds', [$krsPending->id])
        ->call('approveSelected');

    $krsPending->refresh();
    expect($krsPending->approved_at)->not->toBeNull();
    expect($krsPending->approved_by)->not->toBeNull();
    expect(Notifikasi::where('id_user', $userMahasiswa->id)->where('tipe', 'krs_disetujui')->exists())->toBeTrue();
});

it('rejects approving a krs that belongs to a mahasiswa not assigned to this dosen', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $mahasiswaSaya = Mahasiswa::factory()->create();
    DosenWali::create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $mahasiswaSaya->id, 'status' => 'active']);

    $mahasiswaLain = Mahasiswa::factory()->create();
    $krsOrangLain = Krs::factory()->create(['id_mahasiswa' => $mahasiswaLain->id, 'approved_at' => null]);

    Livewire::actingAs($dosenUser)
        ->test(Index::class)
        ->call('openKrsModal', $mahasiswaSaya->id)
        ->set('selectedKrsIds', [$krsOrangLain->id])
        ->call('approveSelected')
        ->assertForbidden();

    expect($krsOrangLain->fresh()->approved_at)->toBeNull();
});
