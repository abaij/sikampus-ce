<?php

use App\Livewire\Dosen\Perwalian\Index;
use App\Models\Dosen;
use App\Models\DosenWali;
use App\Models\Mahasiswa;
use App\Models\User;
use Livewire\Livewire;

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('dosen.perwalian'))->assertRedirect(route('login'));
});

it('forbids a non-dosen user', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa']);

    $this->actingAs($mahasiswa)->get(route('dosen.perwalian'))->assertForbidden();
});

it('only lists active bimbingan mahasiswa, not inactive ones', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $aktif = Mahasiswa::factory()->create();
    $nonaktif = Mahasiswa::factory()->create();
    DosenWali::create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $aktif->id, 'status' => 'active']);
    DosenWali::create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $nonaktif->id, 'status' => 'inactive']);

    $rows = Livewire::actingAs($dosenUser)->test(Index::class)->instance()->rows();

    expect($rows->total())->toBe(1);
    expect($rows->first()->mahasiswa->id)->toBe($aktif->id);
});

it('filters by search and paginates', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    $cocok = Mahasiswa::factory()->create(['nim' => '20241234']);
    $tidakCocok = Mahasiswa::factory()->create(['nim' => '99999999']);
    DosenWali::create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $cocok->id, 'status' => 'active']);
    DosenWali::create(['id_dosen' => $dosen->id, 'id_mahasiswa' => $tidakCocok->id, 'status' => 'active']);

    Livewire::actingAs($dosenUser)
        ->test(Index::class)
        ->set('search', '1234')
        ->assertSee($cocok->nim)
        ->assertDontSee($tidakCocok->nim);
});
