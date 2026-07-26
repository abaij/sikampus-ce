<?php

use App\Models\User;

it('redirects a guest to the login page', function () {
    $this->get(route('mahasiswa.dashboard'))->assertRedirect(route('login'));
});

it('shows the placeholder dashboard to a mahasiswa', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa']);

    $this->actingAs($mahasiswa)
        ->get(route('mahasiswa.dashboard'))
        ->assertOk();
});

it('forbids a non-mahasiswa user', function () {
    $dosen = User::factory()->create(['role' => 'dosen']);

    $this->actingAs($dosen)
        ->get(route('mahasiswa.dashboard'))
        ->assertForbidden();
});
