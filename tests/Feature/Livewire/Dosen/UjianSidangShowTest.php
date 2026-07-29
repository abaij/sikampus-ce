<?php

use App\Livewire\Dosen\UjianSidang\Show;
use App\Models\Dosen;
use App\Models\JenisMatkul;
use App\Models\Kelas;
use App\Models\Krs;
use App\Models\KurikulumMatkul;
use App\Models\Mahasiswa;
use App\Models\Matkul;
use App\Models\Nilai;
use App\Models\Prodi;
use App\Models\RentangNilai;
use App\Models\Semester;
use App\Models\UjianSidang;
use App\Models\UjianSidangPenguji;
use Livewire\Livewire;

it('redirects unauthenticated users to the login page', function () {
    $ta = buatTugasAkhirDosen();
    $sidang = UjianSidang::create(['id_tugas_akhir' => $ta->id, 'id_semester' => Semester::factory()->create()->id, 'tanggal_daftar' => now(), 'status' => 'draft']);
    $penguji = UjianSidangPenguji::create(['id_ujian_sidang' => $sidang->id, 'id_dosen' => Dosen::factory()->create()->id, 'is_ketua' => true, 'status' => 'draft']);

    $this->get(route('dosen.ujian-sidang.show', $penguji->id))->assertRedirect(route('login'));
});

it('forbids a dosen who does not own this penguji row', function () {
    $dosenUser = dosenUser();
    $ta = buatTugasAkhirDosen();
    $sidang = UjianSidang::create(['id_tugas_akhir' => $ta->id, 'id_semester' => Semester::factory()->create()->id, 'tanggal_daftar' => now(), 'status' => 'draft']);
    $penguji = UjianSidangPenguji::create(['id_ujian_sidang' => $sidang->id, 'id_dosen' => Dosen::factory()->create()->id, 'is_ketua' => true, 'status' => 'draft']);

    Livewire::actingAs($dosenUser)->test(Show::class, ['id' => $penguji->id])->assertForbidden();
});

it('saves nilai and catatan for the logged in dosen penguji', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $ta = buatTugasAkhirDosen();
    $sidang = UjianSidang::create(['id_tugas_akhir' => $ta->id, 'id_semester' => Semester::factory()->create()->id, 'tanggal_daftar' => now(), 'status' => 'draft']);
    $penguji = UjianSidangPenguji::create(['id_ujian_sidang' => $sidang->id, 'id_dosen' => $dosen->id, 'is_ketua' => false, 'status' => 'draft']);

    Livewire::actingAs($dosenUser)
        ->test(Show::class, ['id' => $penguji->id])
        ->set('formNilai', '88')
        ->set('formCatatan', 'Presentasi baik')
        ->call('saveNilai')
        ->assertHasNoErrors();

    $penguji->refresh();
    expect((float) $penguji->nilai)->toBe(88.0);
    expect($penguji->catatan)->toBe('Presentasi baik');
    expect($penguji->updated_by)->toBe($dosenUser->name);
});

it('does not expose finalisasi preview to a non-ketua penguji', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $ta = buatTugasAkhirDosen();
    $sidang = UjianSidang::create(['id_tugas_akhir' => $ta->id, 'id_semester' => Semester::factory()->create()->id, 'tanggal_daftar' => now(), 'status' => 'draft']);
    $penguji = UjianSidangPenguji::create(['id_ujian_sidang' => $sidang->id, 'id_dosen' => $dosen->id, 'is_ketua' => false, 'status' => 'draft']);

    $preview = Livewire::actingAs($dosenUser)->test(Show::class, ['id' => $penguji->id])->instance()->previewFinalisasi();

    expect($preview['ok'])->toBeFalse();
});

it('finalizes the ujian sidang score into the transkrip as ketua penguji', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $jenjang = Prodi::factory()->create()->jenjang;
    RentangNilai::create([
        'id_jenjang' => $jenjang->id,
        'nilai_huruf' => 'A',
        'nilai_angka' => 4,
        'nilai_rendah' => 80,
        'nilai_tinggi' => 100,
        'is_lulus' => true,
    ]);

    $prodi = Prodi::factory()->create(['id_jenjang' => $jenjang->id]);
    $mahasiswa = Mahasiswa::factory()->create(['id_prodi' => $prodi->id]);
    $semesterTa = Semester::factory()->active()->create();

    $jenisTa = JenisMatkul::firstOrCreate(['kode' => 'TA'], ['nama' => 'Tugas Akhir']);
    $matkul = Matkul::factory()->create(['id_jenis_matkul' => $jenisTa->id, 'id_prodi' => $prodi->id]);
    $kurikulumMatkul = KurikulumMatkul::factory()->create(['id_matkul' => $matkul->id, 'sks' => 6]);
    $kelas = Kelas::factory()->create([
        'id_kurikulum_matkul' => $kurikulumMatkul->id,
        'id_prodi' => $prodi->id,
        'id_semester' => $semesterTa->id,
    ]);
    Krs::create([
        'id_mahasiswa' => $mahasiswa->id,
        'id_kelas' => $kelas->id,
        'approved_at' => now(),
    ]);

    $ta = buatTugasAkhirDosen(['mahasiswa' => $mahasiswa, 'semester' => $semesterTa, 'status' => 'approved']);
    $sidang = UjianSidang::create([
        'id_tugas_akhir' => $ta->id,
        'id_semester' => Semester::factory()->create()->id,
        'tanggal_daftar' => now(),
        'status' => 'approved',
    ]);
    $penguji = UjianSidangPenguji::create([
        'id_ujian_sidang' => $sidang->id,
        'id_dosen' => $dosen->id,
        'is_ketua' => true,
        'nilai' => 90,
        'status' => 'approved',
    ]);
    UjianSidangPenguji::create([
        'id_ujian_sidang' => $sidang->id,
        'id_dosen' => Dosen::factory()->create()->id,
        'is_ketua' => false,
        'nilai' => 86,
        'status' => 'approved',
    ]);

    $component = Livewire::actingAs($dosenUser)->test(Show::class, ['id' => $penguji->id]);

    expect($component->instance()->previewFinalisasi()['ok'])->toBeTrue();

    $component->call('openFinalisasiConfirm')->call('finalisasiNilai');

    $krs = Krs::where('id_mahasiswa', $mahasiswa->id)->firstOrFail();
    $nilai = Nilai::where('id_krs', $krs->id)->firstOrFail();
    expect($nilai->huruf_mutu)->toBe('A');
    expect((bool) $nilai->is_final)->toBeTrue();
});

it('rejects finalizing when a non-ketua penguji calls it directly', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $ta = buatTugasAkhirDosen(['status' => 'approved']);
    $sidang = UjianSidang::create(['id_tugas_akhir' => $ta->id, 'id_semester' => Semester::factory()->create()->id, 'tanggal_daftar' => now(), 'status' => 'approved']);
    $penguji = UjianSidangPenguji::create(['id_ujian_sidang' => $sidang->id, 'id_dosen' => $dosen->id, 'is_ketua' => false, 'nilai' => 80, 'status' => 'approved']);

    Livewire::actingAs($dosenUser)
        ->test(Show::class, ['id' => $penguji->id])
        ->call('finalisasiNilai')
        ->assertStatus(403);
});
