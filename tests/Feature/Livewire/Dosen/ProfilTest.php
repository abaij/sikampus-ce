<?php

use App\Livewire\Dosen\Profil;
use App\Models\Dosen;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

function dosenUserWithProfile(array $userAttributes = [], array $dosenAttributes = []): User
{
    $user = User::factory()->create(array_merge(['role' => 'dosen'], $userAttributes));
    Dosen::factory()->create(array_merge(['id_user' => $user->id], $dosenAttributes));

    return $user;
}

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('dosen.profil'))->assertRedirect(route('login'));
});

it('forbids a non-dosen user', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa']);

    $this->actingAs($mahasiswa)->get(route('dosen.profil'))->assertForbidden();
});

it('renders the profil page prefilled from the linked dosen record', function () {
    $dosenUser = dosenUserWithProfile([], ['nama' => 'Dosen Uji', 'kode_dosen' => 'DSN-001']);

    Livewire::actingAs($dosenUser)
        ->test(Profil::class)
        ->assertSet('nama', 'Dosen Uji')
        ->assertSet('kode_dosen', 'DSN-001');
});

it('updates the editable contact fields', function () {
    $dosenUser = dosenUserWithProfile();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    Livewire::actingAs($dosenUser)
        ->test(Profil::class)
        ->set('nama', 'Nama Dosen Baru')
        ->set('no_hp', '0812345')
        ->call('saveProfil');

    expect($dosen->fresh()->nama)->toBe('Nama Dosen Baru');
    expect($dosen->fresh()->no_hp)->toBe('0812345');
});

it('changes the password when the current password is correct', function () {
    $dosenUser = dosenUserWithProfile(['password' => Hash::make('password-lama')]);

    Livewire::actingAs($dosenUser)
        ->test(Profil::class)
        ->set('current_password', 'password-lama')
        ->set('new_password', 'password-baru')
        ->set('new_password_confirmation', 'password-baru')
        ->call('savePassword')
        ->assertHasNoErrors();

    expect(Hash::check('password-baru', $dosenUser->fresh()->password))->toBeTrue();
});

it('rejects the password change when the current password is wrong', function () {
    $dosenUser = dosenUserWithProfile(['password' => Hash::make('password-lama')]);

    Livewire::actingAs($dosenUser)
        ->test(Profil::class)
        ->set('current_password', 'salah')
        ->set('new_password', 'password-baru')
        ->set('new_password_confirmation', 'password-baru')
        ->call('savePassword')
        ->assertHasErrors(['current_password']);
});
