<?php

use App\Models\Prodi;
use App\Models\User;

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('prodi.dashboard'))->assertRedirect(route('login'));
});

it('forbids a dosen who is neither kaprodi nor sekprodi', function () {
    $dosenUser = dosenUser();

    $this->actingAs($dosenUser)->get(route('prodi.dashboard'))->assertForbidden();
});

it('forbids a mahasiswa', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa']);

    $this->actingAs($mahasiswa)->get(route('prodi.dashboard'))->assertForbidden();
});

it('allows a dosen who is kepala prodi', function () {
    $prodi = Prodi::factory()->create();
    $kaprodi = kaprodiUser($prodi, 'kaprodi');

    $this->actingAs($kaprodi)
        ->get(route('prodi.dashboard'))
        ->assertOk()
        ->assertSee('Dashboard Prodi');
});

it('allows a dosen who is sekretaris prodi', function () {
    $prodi = Prodi::factory()->create();
    $sekprodi = kaprodiUser($prodi, 'sekprodi');

    $this->actingAs($sekprodi)
        ->get(route('prodi.dashboard'))
        ->assertOk();
});

it('shows the prodi scope and peran badge in the sidebar', function () {
    $prodi = Prodi::factory()->create(['nama' => 'Teknik Informatika']);
    $kaprodi = kaprodiUser($prodi, 'kaprodi');

    $this->actingAs($kaprodi)
        ->get(route('prodi.dashboard'))
        ->assertOk()
        ->assertSee('Teknik Informatika')
        ->assertSee('Kepala Prodi');
});
