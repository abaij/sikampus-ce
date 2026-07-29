<?php

use App\Livewire\Dosen\Nilai\Rekap;
use App\Models\Dosen;
use App\Models\JenisPenilaian;
use App\Models\Jenjang;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\Mahasiswa;
use App\Models\Nilai;
use App\Models\NilaiRevisi;
use App\Models\Notifikasi;
use App\Models\Prodi;
use App\Models\RentangNilai;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('redirects unauthenticated users to the login page', function () {
    $kelas = Kelas::factory()->create();

    $this->get(route('dosen.nilai.rekap', $kelas->id))->assertRedirect(route('login'));
});

it('forbids a dosen who is neither pic nor has an active jadwal for the kelas', function () {
    $dosenUser = dosenUser();
    $kelas = Kelas::factory()->create();

    Livewire::actingAs($dosenUser)->test(Rekap::class, ['kelasId' => $kelas->id])->assertForbidden();
});

it('calculates the final grade with the default rentang and stores it as not-yet-final', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $jenjang = Jenjang::factory()->create();
    $prodi = Prodi::factory()->create(['id_jenjang' => $jenjang->id]);
    $kelas = Kelas::factory()->create(['id_dosen_pic' => $dosen->id, 'id_prodi' => $prodi->id]);

    RentangNilai::factory()->create(['id_jenjang' => $jenjang->id, 'nilai_huruf' => 'A', 'nilai_angka' => 4, 'nilai_rendah' => 85, 'nilai_tinggi' => 100]);
    RentangNilai::factory()->create(['id_jenjang' => $jenjang->id, 'nilai_huruf' => 'B', 'nilai_angka' => 3, 'nilai_rendah' => 70, 'nilai_tinggi' => 84.99]);

    $jenisPenilaian = JenisPenilaian::factory()->create(['status' => 'manual', 'bobot' => 100]);
    $mhs = Mahasiswa::factory()->create();
    $krs = Krs::factory()->create(['id_mahasiswa' => $mhs->id, 'id_kelas' => $kelas->id]);
    DB::table('nilai_komponen')->insert(['id_krs' => $krs->id, 'id_jenis_penilaian' => $jenisPenilaian->id, 'nilai' => 90, 'id_dosen' => $dosen->id, 'created_at' => now(), 'updated_at' => now()]);

    Livewire::actingAs($dosenUser)
        ->test(Rekap::class, ['kelasId' => $kelas->id])
        ->call('kalkulasiDenganRentangDefault');

    $nilai = Nilai::where('id_krs', $krs->id)->firstOrFail();
    expect($nilai->huruf_mutu)->toBe('A');
    expect((float) $nilai->angka_mutu)->toBe(4.0);
    expect($nilai->is_final)->toBeNull();
});

it('does not calculate a grade for a krs missing a component value for any jenis penilaian', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $jenjang = Jenjang::factory()->create();
    $prodi = Prodi::factory()->create(['id_jenjang' => $jenjang->id]);
    $kelas = Kelas::factory()->create(['id_dosen_pic' => $dosen->id, 'id_prodi' => $prodi->id]);
    RentangNilai::factory()->create(['id_jenjang' => $jenjang->id, 'nilai_huruf' => 'A', 'nilai_angka' => 4, 'nilai_rendah' => 0, 'nilai_tinggi' => 100]);

    // Dua jenis penilaian ada di sistem, tapi mahasiswa cuma diisi satu komponen -> kalkulasi harus gagal untuknya.
    JenisPenilaian::factory()->create(['status' => 'manual', 'bobot' => 50]);
    $jenisPenilaian2 = JenisPenilaian::factory()->create(['status' => 'manual', 'bobot' => 50]);
    $mhs = Mahasiswa::factory()->create();
    $krs = Krs::factory()->create(['id_mahasiswa' => $mhs->id, 'id_kelas' => $kelas->id]);
    DB::table('nilai_komponen')->insert(['id_krs' => $krs->id, 'id_jenis_penilaian' => $jenisPenilaian2->id, 'nilai' => 90, 'id_dosen' => $dosen->id, 'created_at' => now(), 'updated_at' => now()]);

    Livewire::actingAs($dosenUser)
        ->test(Rekap::class, ['kelasId' => $kelas->id])
        ->call('kalkulasiDenganRentangDefault');

    expect(Nilai::where('id_krs', $krs->id)->exists())->toBeFalse();
});

it('applies a custom rentang range via the preview modal', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $jenjang = Jenjang::factory()->create();
    $prodi = Prodi::factory()->create(['id_jenjang' => $jenjang->id]);
    $kelas = Kelas::factory()->create(['id_dosen_pic' => $dosen->id, 'id_prodi' => $prodi->id]);
    RentangNilai::factory()->create(['id_jenjang' => $jenjang->id, 'nilai_huruf' => 'A', 'nilai_angka' => 4, 'nilai_rendah' => 85, 'nilai_tinggi' => 100]);

    $jenisPenilaian = JenisPenilaian::factory()->create(['status' => 'manual', 'bobot' => 100]);
    $mhs = Mahasiswa::factory()->create();
    $krs = Krs::factory()->create(['id_mahasiswa' => $mhs->id, 'id_kelas' => $kelas->id]);
    DB::table('nilai_komponen')->insert(['id_krs' => $krs->id, 'id_jenis_penilaian' => $jenisPenilaian->id, 'nilai' => 80, 'id_dosen' => $dosen->id, 'created_at' => now(), 'updated_at' => now()]);

    $component = Livewire::actingAs($dosenUser)->test(Rekap::class, ['kelasId' => $kelas->id])->call('openRentangModal');
    $component->set('rentangForm.0.nilai_rendah', 0)
        ->set('rentangForm.0.nilai_tinggi', 100)
        ->call('terapkanRentangCustom');

    $nilai = Nilai::where('id_krs', $krs->id)->firstOrFail();
    expect($nilai->huruf_mutu)->toBe('A');
});

it('finalizes nilai for the kelas and notifies each mahasiswa', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $kelas = Kelas::factory()->create(['id_dosen_pic' => $dosen->id]);

    $userMhs = User::factory()->create(['role' => 'mahasiswa']);
    $mhs = Mahasiswa::factory()->create(['id_user' => $userMhs->id]);
    $krs = Krs::factory()->create(['id_mahasiswa' => $mhs->id, 'id_kelas' => $kelas->id]);
    Nilai::factory()->create(['id_krs' => $krs->id, 'huruf_mutu' => 'A', 'angka_mutu' => 4, 'is_final' => null]);

    Livewire::actingAs($dosenUser)->test(Rekap::class, ['kelasId' => $kelas->id])->call('finalisasi');

    expect(Nilai::where('id_krs', $krs->id)->value('is_final'))->toBeTrue();
    expect(Notifikasi::where('id_user', $userMhs->id)->where('tipe', 'nilai_final')->exists())->toBeTrue();
});

it('updates nilai without revisi via update-by-krs semantics', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $kelas = Kelas::factory()->create(['id_dosen_pic' => $dosen->id]);
    $mhs = Mahasiswa::factory()->create();
    $krs = Krs::factory()->create(['id_mahasiswa' => $mhs->id, 'id_kelas' => $kelas->id]);

    Livewire::actingAs($dosenUser)
        ->test(Rekap::class, ['kelasId' => $kelas->id])
        ->call('openEditModal', $krs->id)
        ->set('editHurufMutu', 'B')
        ->set('editAngkaMutu', '3')
        ->set('editRevisiChecked', false)
        ->call('saveEditNilai')
        ->assertHasNoErrors();

    $nilai = Nilai::where('id_krs', $krs->id)->firstOrFail();
    expect($nilai->huruf_mutu)->toBe('B');
    expect(NilaiRevisi::where('id_krs', $krs->id)->count())->toBe(0);
});

it('stores a revisi entry and bumps the revisi counter when revisi is checked', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $kelas = Kelas::factory()->create(['id_dosen_pic' => $dosen->id]);
    $mhs = Mahasiswa::factory()->create();
    $krs = Krs::factory()->create(['id_mahasiswa' => $mhs->id, 'id_kelas' => $kelas->id]);
    Nilai::factory()->create(['id_krs' => $krs->id, 'huruf_mutu' => 'C', 'angka_mutu' => 2, 'revisi' => 0]);

    Livewire::actingAs($dosenUser)
        ->test(Rekap::class, ['kelasId' => $kelas->id])
        ->call('openEditModal', $krs->id)
        ->set('editHurufMutu', 'A')
        ->set('editAngkaMutu', '4')
        ->set('editKeterangan', 'Koreksi salah input')
        ->set('editRevisiChecked', true)
        ->call('saveEditNilai')
        ->assertHasNoErrors();

    $nilai = Nilai::where('id_krs', $krs->id)->firstOrFail();
    expect($nilai->huruf_mutu)->toBe('A');
    expect($nilai->revisi)->toBe(1);
    expect(NilaiRevisi::where('id_krs', $krs->id)->count())->toBe(1);
});
