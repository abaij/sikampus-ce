<?php

use App\Models\User;

it('logs in a superadmin/akademik/keuangan user and redirects to the admin dashboard', function () {
    $admin = adminUser('admin_akademik');

    $this->post(route('admin.login'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($admin);
});

it('rejects a user without an admin role even with correct credentials', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa']);

    $this->post(route('admin.login'), [
        'email' => $mahasiswa->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs the admin out', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.logout'))
        ->assertRedirect(route('admin.login'));

    $this->assertGuest();
});
