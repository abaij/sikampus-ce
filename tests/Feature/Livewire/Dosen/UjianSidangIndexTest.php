<?php

use App\Livewire\Dosen\UjianSidang\Index;
use App\Models\Dosen;
use App\Models\Semester;
use App\Models\UjianSidang;
use App\Models\UjianSidangPenguji;
use App\Models\User;
use Livewire\Livewire;

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('dosen.ujian-sidang'))->assertRedirect(route('login'));
});

it('forbids a non-dosen user', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa']);

    $this->actingAs($mahasiswa)->get(route('dosen.ujian-sidang'))->assertForbidden();
});

it('lists ujian sidang where the dosen is a penguji, filtered by the sidang semester', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semesterAktif = Semester::factory()->active()->create();
    $semesterLain = Semester::factory()->create();

    $ta = buatTugasAkhirDosen();
    $sidangAktif = UjianSidang::create([
        'id_tugas_akhir' => $ta->id,
        'id_semester' => $semesterAktif->id,
        'tanggal_daftar' => now(),
        'status' => 'approved',
    ]);
    UjianSidangPenguji::create(['id_ujian_sidang' => $sidangAktif->id, 'id_dosen' => $dosen->id, 'is_ketua' => true, 'status' => 'draft']);

    $taLain = buatTugasAkhirDosen();
    $sidangLain = UjianSidang::create([
        'id_tugas_akhir' => $taLain->id,
        'id_semester' => $semesterLain->id,
        'tanggal_daftar' => now(),
        'status' => 'approved',
    ]);
    UjianSidangPenguji::create(['id_ujian_sidang' => $sidangLain->id, 'id_dosen' => $dosen->id, 'is_ketua' => false, 'status' => 'draft']);

    $rows = Livewire::actingAs($dosenUser)->test(Index::class)->instance()->rows();
    expect($rows)->toHaveCount(1);
    expect($rows[0]->id_ujian_sidang)->toBe($sidangAktif->id);

    $rows = Livewire::actingAs($dosenUser)->test(Index::class)->set('filterSemester', '')->instance()->rows();
    expect($rows)->toHaveCount(2);
});

it('does not list ujian sidang where the dosen is not a penguji', function () {
    $dosenUser = dosenUser();

    $ta = buatTugasAkhirDosen();
    $sidang = UjianSidang::create([
        'id_tugas_akhir' => $ta->id,
        'id_semester' => Semester::factory()->active()->create()->id,
        'tanggal_daftar' => now(),
        'status' => 'approved',
    ]);
    UjianSidangPenguji::create(['id_ujian_sidang' => $sidang->id, 'id_dosen' => Dosen::factory()->create()->id, 'is_ketua' => true, 'status' => 'draft']);

    $rows = Livewire::actingAs($dosenUser)->test(Index::class)->instance()->rows();
    expect($rows)->toHaveCount(0);
});
