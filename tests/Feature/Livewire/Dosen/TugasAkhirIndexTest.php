<?php

use App\Livewire\Dosen\TugasAkhir\Index;
use App\Models\Dosen;
use App\Models\Mahasiswa;
use App\Models\Semester;
use App\Models\TugasAkhir;
use App\Models\TugasAkhirPembimbing;
use App\Models\User;
use Livewire\Livewire;

function buatTugasAkhirDosen(array $overrides = []): TugasAkhir
{
    $mahasiswa = $overrides['mahasiswa'] ?? Mahasiswa::factory()->create();
    $semester = $overrides['semester'] ?? Semester::factory()->active()->create();

    return TugasAkhir::create(array_merge([
        'id_mahasiswa' => $mahasiswa->id,
        'id_semester' => $semester->id,
        'judul' => 'Sistem Informasi Akademik Berbasis Web',
        'status' => 'approved',
        'is_proposal' => true,
    ], array_diff_key($overrides, array_flip(['mahasiswa', 'semester']))));
}

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('dosen.tugas-akhir'))->assertRedirect(route('login'));
});

it('forbids a non-dosen user', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa']);

    $this->actingAs($mahasiswa)->get(route('dosen.tugas-akhir'))->assertForbidden();
});

it('lists only approved tugas akhir where the dosen is a pembimbing', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semesterAktif = Semester::factory()->active()->create();
    $ta = buatTugasAkhirDosen(['semester' => $semesterAktif, 'status' => 'approved']);
    TugasAkhirPembimbing::create(['id_tugas_akhir' => $ta->id, 'id_dosen' => $dosen->id, 'peran' => 'pembimbing']);

    // tugas akhir lain: belum disetujui -> tidak muncul meski dosen ini pembimbing
    $taBelumDisetujui = buatTugasAkhirDosen(['semester' => $semesterAktif, 'status' => 'submitted']);
    TugasAkhirPembimbing::create(['id_tugas_akhir' => $taBelumDisetujui->id, 'id_dosen' => $dosen->id, 'peran' => 'pembimbing']);

    // tugas akhir lain: disetujui tapi dosen ini bukan pembimbingnya
    $taOrangLain = buatTugasAkhirDosen(['semester' => $semesterAktif, 'status' => 'approved']);
    TugasAkhirPembimbing::create(['id_tugas_akhir' => $taOrangLain->id, 'id_dosen' => Dosen::factory()->create()->id, 'peran' => 'pembimbing']);

    $rows = Livewire::actingAs($dosenUser)->test(Index::class)->instance()->rows();

    expect($rows)->toHaveCount(1);
    expect($rows[0]->id)->toBe($ta->id);
});

it('only lists tugas akhir from the selected semester', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $semesterAktif = Semester::factory()->active()->create();
    $semesterLain = Semester::factory()->create();

    $taAktif = buatTugasAkhirDosen(['semester' => $semesterAktif, 'status' => 'approved']);
    TugasAkhirPembimbing::create(['id_tugas_akhir' => $taAktif->id, 'id_dosen' => $dosen->id, 'peran' => 'pembimbing']);

    $taLain = buatTugasAkhirDosen(['semester' => $semesterLain, 'status' => 'approved']);
    TugasAkhirPembimbing::create(['id_tugas_akhir' => $taLain->id, 'id_dosen' => $dosen->id, 'peran' => 'pembimbing']);

    $rows = Livewire::actingAs($dosenUser)->test(Index::class)->instance()->rows();
    expect($rows)->toHaveCount(1);
    expect($rows[0]->id)->toBe($taAktif->id);

    $rows = Livewire::actingAs($dosenUser)->test(Index::class)->set('filterSemester', '')->instance()->rows();
    expect($rows)->toHaveCount(2);
});
