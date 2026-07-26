<?php

use App\Models\User;

it('redirects a guest to the login page', function () {
    $this->get(route('dosen.dashboard'))->assertRedirect(route('login'));
});

it('shows the placeholder dashboard to a dosen', function () {
    $dosen = User::factory()->create(['role' => 'dosen']);

    $this->actingAs($dosen)
        ->get(route('dosen.dashboard'))
        ->assertOk();
});

it('forbids a non-dosen user', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa']);

    $this->actingAs($mahasiswa)
        ->get(route('dosen.dashboard'))
        ->assertForbidden();
});
