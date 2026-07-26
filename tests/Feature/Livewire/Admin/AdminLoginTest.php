<?php

use App\Models\User;

it('logs the admin out', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('does not let a non-admin user open the panel admin', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa']);

    $this->actingAs($mahasiswa)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});
