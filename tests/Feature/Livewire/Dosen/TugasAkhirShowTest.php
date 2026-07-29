<?php

use App\Livewire\Dosen\TugasAkhir\Show;
use App\Models\Dosen;
use App\Models\TugasAkhirBimbingan;
use App\Models\TugasAkhirPembimbing;
use Livewire\Livewire;

it('redirects unauthenticated users to the login page', function () {
    $ta = buatTugasAkhirDosen();

    $this->get(route('dosen.tugas-akhir.show', $ta->id))->assertRedirect(route('login'));
});

it('forbids a dosen who is not a pembimbing on this tugas akhir', function () {
    $dosenUser = dosenUser();
    $ta = buatTugasAkhirDosen(['status' => 'approved']);

    Livewire::actingAs($dosenUser)->test(Show::class, ['id' => $ta->id])->assertForbidden();
});

it('forbids a pembimbing when the judul is not yet approved', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $ta = buatTugasAkhirDosen(['status' => 'submitted']);
    TugasAkhirPembimbing::create(['id_tugas_akhir' => $ta->id, 'id_dosen' => $dosen->id, 'peran' => 'pembimbing']);

    Livewire::actingAs($dosenUser)->test(Show::class, ['id' => $ta->id])->assertForbidden();
});

it('shows the tugas akhir detail and only this dosen bimbingan history', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $dosenLain = Dosen::factory()->create();
    $ta = buatTugasAkhirDosen(['status' => 'approved']);
    TugasAkhirPembimbing::create(['id_tugas_akhir' => $ta->id, 'id_dosen' => $dosen->id, 'peran' => 'pembimbing']);

    TugasAkhirBimbingan::create([
        'id_tugas_akhir' => $ta->id,
        'id_dosen' => $dosen->id,
        'tanggal_bimbingan' => '2026-01-05',
        'catatan_dosen' => 'Perbaiki bab 2',
    ]);
    TugasAkhirBimbingan::create([
        'id_tugas_akhir' => $ta->id,
        'id_dosen' => $dosenLain->id,
        'tanggal_bimbingan' => '2026-01-06',
        'catatan_dosen' => 'Milik dosen lain',
    ]);

    $riwayat = Livewire::actingAs($dosenUser)->test(Show::class, ['id' => $ta->id])->instance()->riwayatBimbingan();

    expect($riwayat)->toHaveCount(1);
    expect($riwayat[0]->catatan_dosen)->toBe('Perbaiki bab 2');
});

it('records a bimbingan entry and rejects a duplicate date', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $ta = buatTugasAkhirDosen(['status' => 'approved']);
    TugasAkhirPembimbing::create(['id_tugas_akhir' => $ta->id, 'id_dosen' => $dosen->id, 'peran' => 'pembimbing']);

    Livewire::actingAs($dosenUser)
        ->test(Show::class, ['id' => $ta->id])
        ->call('openBimbinganModal')
        ->set('form_tanggal_bimbingan', '2026-02-01')
        ->set('form_catatan_dosen', 'Diskusi metodologi')
        ->call('saveBimbingan')
        ->assertHasNoErrors();

    $row = TugasAkhirBimbingan::where('id_tugas_akhir', $ta->id)->firstOrFail();
    expect($row->catatan_dosen)->toBe('Diskusi metodologi');
    expect($row->created_by)->toBe($dosenUser->name);

    Livewire::actingAs($dosenUser)
        ->test(Show::class, ['id' => $ta->id])
        ->call('openBimbinganModal')
        ->set('form_tanggal_bimbingan', '2026-02-01')
        ->call('saveBimbingan')
        ->assertHasErrors('form_tanggal_bimbingan');

    expect(TugasAkhirBimbingan::where('id_tugas_akhir', $ta->id)->count())->toBe(1);
});
