<?php

use App\Livewire\Mahasiswa\Profil;
use App\Models\Mahasiswa;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

function mahasiswaUserWithProfile(array $userAttributes = [], array $mahasiswaAttributes = []): User
{
    $user = User::factory()->create(array_merge(['role' => 'mahasiswa'], $userAttributes));
    Mahasiswa::factory()->create(array_merge(['id_user' => $user->id], $mahasiswaAttributes));

    return $user;
}

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('mahasiswa.profil'))->assertRedirect(route('login'));
});

it('forbids a non-mahasiswa user', function () {
    $dosen = User::factory()->create(['role' => 'dosen']);

    $this->actingAs($dosen)->get(route('mahasiswa.profil'))->assertForbidden();
});

it('renders the profil page prefilled from the linked mahasiswa record', function () {
    $mahasiswaUser = mahasiswaUserWithProfile([], ['nama' => 'Mahasiswa Uji', 'nim' => '2099001']);

    Livewire::actingAs($mahasiswaUser)
        ->test(Profil::class)
        ->assertSet('nama', 'Mahasiswa Uji')
        ->assertSet('nim', '2099001');
});

it('updates the editable contact fields', function () {
    $mahasiswaUser = mahasiswaUserWithProfile();
    $mahasiswa = Mahasiswa::where('id_user', $mahasiswaUser->id)->firstOrFail();

    Livewire::actingAs($mahasiswaUser)
        ->test(Profil::class)
        ->set('nama', 'Nama Mahasiswa Baru')
        ->set('handphone', '0812345')
        ->call('saveProfil');

    expect($mahasiswa->fresh()->nama)->toBe('Nama Mahasiswa Baru');
    expect($mahasiswa->fresh()->handphone)->toBe('0812345');
});

it('changes the password when the current password is correct', function () {
    $mahasiswaUser = mahasiswaUserWithProfile(['password' => Hash::make('password-lama')]);

    Livewire::actingAs($mahasiswaUser)
        ->test(Profil::class)
        ->set('current_password', 'password-lama')
        ->set('new_password', 'password-baru')
        ->set('new_password_confirmation', 'password-baru')
        ->call('savePassword')
        ->assertHasNoErrors();

    expect(Hash::check('password-baru', $mahasiswaUser->fresh()->password))->toBeTrue();
});

it('rejects the password change when the current password is wrong', function () {
    $mahasiswaUser = mahasiswaUserWithProfile(['password' => Hash::make('password-lama')]);

    Livewire::actingAs($mahasiswaUser)
        ->test(Profil::class)
        ->set('current_password', 'salah')
        ->set('new_password', 'password-baru')
        ->set('new_password_confirmation', 'password-baru')
        ->call('savePassword')
        ->assertHasErrors(['current_password']);
});
