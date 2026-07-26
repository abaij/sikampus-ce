<?php

use App\Livewire\Admin\Profil;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.profil'))->assertRedirect(route('login'));
});

it('shows the avatar dropdown with initials instead of a plain logout button', function () {
    $admin = adminUser();

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('confirm-logout-modal', false)
        ->assertSee(route('admin.profil'), false);
});

it('renders the profil page prefilled with the current admin data', function () {
    $admin = adminUser();
    $admin->update(['name' => 'Admin Uji', 'phone' => '0811']);

    Livewire::actingAs($admin)
        ->test(Profil::class)
        ->assertSet('name', 'Admin Uji')
        ->assertSet('phone', '0811');
});

it('updates the profil information', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Profil::class)
        ->set('name', 'Nama Baru')
        ->set('email', 'baru@example.com')
        ->set('phone', '0899')
        ->call('saveProfil');

    expect($admin->fresh()->name)->toBe('Nama Baru');
    expect($admin->fresh()->email)->toBe('baru@example.com');
});

it('rejects an email already used by another user', function () {
    $admin = adminUser();
    User::factory()->create(['email' => 'terpakai@example.com']);

    Livewire::actingAs($admin)
        ->test(Profil::class)
        ->set('email', 'terpakai@example.com')
        ->call('saveProfil')
        ->assertHasErrors(['email']);
});

it('changes the password when the current password is correct', function () {
    $admin = adminUser();
    $admin->update(['password' => Hash::make('password-lama')]);

    Livewire::actingAs($admin)
        ->test(Profil::class)
        ->set('current_password', 'password-lama')
        ->set('new_password', 'password-baru')
        ->set('new_password_confirmation', 'password-baru')
        ->call('savePassword')
        ->assertHasNoErrors();

    expect(Hash::check('password-baru', $admin->fresh()->password))->toBeTrue();
});

it('rejects the password change when the current password is wrong', function () {
    $admin = adminUser();
    $admin->update(['password' => Hash::make('password-lama')]);

    Livewire::actingAs($admin)
        ->test(Profil::class)
        ->set('current_password', 'salah')
        ->set('new_password', 'password-baru')
        ->set('new_password_confirmation', 'password-baru')
        ->call('savePassword')
        ->assertHasErrors(['current_password']);

    expect(Hash::check('password-lama', $admin->fresh()->password))->toBeTrue();
});
