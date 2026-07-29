<?php

use App\Livewire\Dosen\Rps\Show;
use App\Models\Dosen;
use App\Models\Kelas;
use App\Models\KelasDosen;
use App\Models\Rps;
use App\Models\RpsCpl;
use App\Models\RpsCpmk;
use App\Models\RpsPembelajaran;
use App\Models\RpsSubcpmk;
use Livewire\Livewire;

it('redirects unauthenticated users to the login page', function () {
    $kelas = Kelas::factory()->create();

    $this->get(route('dosen.rps.show', $kelas->id))->assertRedirect(route('login'));
});

it('forbids a dosen who teaches the kelas but is not pic', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $kelas = Kelas::factory()->create();
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelas->id, 'is_pic' => false]);

    Livewire::actingAs($dosenUser)->test(Show::class, ['kelasId' => $kelas->id])->assertForbidden();
});

it('creates the rps row on first save of the info tab', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $kelas = Kelas::factory()->create();
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelas->id, 'is_pic' => true]);

    Livewire::actingAs($dosenUser)
        ->test(Show::class, ['kelasId' => $kelas->id])
        ->set('deskripsi_matkul', 'Mata kuliah pengantar pemrograman')
        ->set('model_pembelajaran', 'Blended learning')
        ->call('saveInfo')
        ->assertHasNoErrors();

    $rps = Rps::where('id_kelas', $kelas->id)->firstOrFail();
    expect($rps->deskripsi_matkul)->toBe('Mata kuliah pengantar pemrograman');
    expect($rps->model_pembelajaran)->toBe('Blended learning');
});

it('rejects adding a cpl before the rps row exists', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $kelas = Kelas::factory()->create();
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelas->id, 'is_pic' => true]);

    Livewire::actingAs($dosenUser)
        ->test(Show::class, ['kelasId' => $kelas->id])
        ->call('openCplModal')
        ->set('form_cpl', 'Mampu berpikir komputasional')
        ->call('saveCpl')
        ->assertHasErrors(['form_cpl']);

    expect(RpsCpl::count())->toBe(0);
});

it('adds, edits, and deletes a cpl once the rps row exists', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $kelas = Kelas::factory()->create();
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelas->id, 'is_pic' => true]);
    $rps = Rps::create(['id_kelas' => $kelas->id]);

    $component = Livewire::actingAs($dosenUser)->test(Show::class, ['kelasId' => $kelas->id]);

    $component->call('openCplModal')
        ->set('form_cpl', 'Mampu berpikir komputasional')
        ->call('saveCpl')
        ->assertHasNoErrors();

    $cpl = RpsCpl::where('id_rps', $rps->id)->firstOrFail();
    expect($cpl->cpl)->toBe('Mampu berpikir komputasional');

    $component->call('openCplModal', $cpl->id)
        ->set('form_cpl', 'Diperbarui')
        ->call('saveCpl');
    expect($cpl->fresh()->cpl)->toBe('Diperbarui');

    $component->call('deleteCpl', $cpl->id);
    expect(RpsCpl::find($cpl->id))->toBeNull();
});

it('adds a cpmk with a nested sub-cpmk, and deleting the cpmk cascades to its sub-cpmk', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $kelas = Kelas::factory()->create();
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelas->id, 'is_pic' => true]);
    $rps = Rps::create(['id_kelas' => $kelas->id]);

    $component = Livewire::actingAs($dosenUser)->test(Show::class, ['kelasId' => $kelas->id]);

    $component->call('openCpmkModal')
        ->set('form_cpmk', 'Mampu menerapkan struktur data')
        ->call('saveCpmk')
        ->assertHasNoErrors();
    $cpmk = RpsCpmk::where('id_rps', $rps->id)->firstOrFail();

    $component->call('openSubcpmkModal', $cpmk->id)
        ->set('form_subcpmk', 'Mampu mengimplementasikan linked list')
        ->call('saveSubcpmk')
        ->assertHasNoErrors();
    $sub = RpsSubcpmk::where('id_cpmk', $cpmk->id)->firstOrFail();
    expect($sub->subcpmk)->toBe('Mampu mengimplementasikan linked list');

    $component->call('deleteCpmk', $cpmk->id);
    expect(RpsCpmk::find($cpmk->id))->toBeNull();
    expect(RpsSubcpmk::find($sub->id))->toBeNull();
});

it('adds, edits, and deletes a rincian pembelajaran row', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $kelas = Kelas::factory()->create();
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $kelas->id, 'is_pic' => true]);
    $rps = Rps::create(['id_kelas' => $kelas->id]);

    $component = Livewire::actingAs($dosenUser)->test(Show::class, ['kelasId' => $kelas->id]);

    $component->call('openPembelajaranModal')
        ->set('form_urutan_pertemuan', '1')
        ->set('form_materi', 'Pengenalan array')
        ->set('form_bobot', '10')
        ->call('savePembelajaran')
        ->assertHasNoErrors();

    $row = RpsPembelajaran::where('id_rps', $rps->id)->firstOrFail();
    expect($row->urutan_pertemuan)->toBe(1);
    expect((float) $row->bobot)->toBe(10.0);

    $component->call('openPembelajaranModal', $row->id)
        ->set('form_materi', 'Pengenalan array dan matriks')
        ->call('savePembelajaran');
    expect($row->fresh()->materi)->toBe('Pengenalan array dan matriks');

    $component->call('deletePembelajaran', $row->id);
    expect(RpsPembelajaran::find($row->id))->toBeNull();
});

it('rejects mutating rps sub-entities for a kelas the dosen does not own as pic', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();
    $myKelas = Kelas::factory()->create();
    KelasDosen::create(['id_dosen' => $dosen->id, 'id_kelas' => $myKelas->id, 'is_pic' => true]);
    Rps::create(['id_kelas' => $myKelas->id]);

    $otherDosen = Dosen::factory()->create();
    $otherKelas = Kelas::factory()->create();
    KelasDosen::create(['id_dosen' => $otherDosen->id, 'id_kelas' => $otherKelas->id, 'is_pic' => true]);
    $otherRps = Rps::create(['id_kelas' => $otherKelas->id]);
    $otherCpl = RpsCpl::create(['id_rps' => $otherRps->id, 'cpl' => 'Milik dosen lain']);

    Livewire::actingAs($dosenUser)
        ->test(Show::class, ['kelasId' => $myKelas->id])
        ->call('deleteCpl', $otherCpl->id)
        ->assertStatus(404);
});
