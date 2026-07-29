<?php

use App\Livewire\Dosen\Profil;
use App\Models\Dosen;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('dosen.profil'))->assertRedirect(route('login'));
});

it('forbids a non-dosen user', function () {
    $mahasiswa = User::factory()->create(['role' => 'mahasiswa']);

    $this->actingAs($mahasiswa)->get(route('dosen.profil'))->assertForbidden();
});

it('renders the profil page prefilled from the linked dosen record', function () {
    $dosenUser = dosenUser([], ['nama' => 'Dosen Uji', 'kode_dosen' => 'DSN-001']);

    Livewire::actingAs($dosenUser)
        ->test(Profil::class)
        ->assertSet('activeTab', 'biodata')
        ->assertSet('nama', 'Dosen Uji')
        ->assertSet('kode_dosen', 'DSN-001');
});

it('updates the editable contact fields', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    Livewire::actingAs($dosenUser)
        ->test(Profil::class)
        ->set('nama', 'Nama Dosen Baru')
        ->set('no_hp', '0812345')
        ->call('saveProfil');

    expect($dosen->fresh()->nama)->toBe('Nama Dosen Baru');
    expect($dosen->fresh()->no_hp)->toBe('0812345');
});

it('updates the extended biodata fields (tempat lahir, tanggal lahir, jenis kelamin, agama, wilayah)', function () {
    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    Livewire::actingAs($dosenUser)
        ->test(Profil::class)
        ->set('tempat_lahir', 'Bogor')
        ->set('tanggal_lahir', '1990-01-15')
        ->set('jenis_kelamin', 'L')
        ->set('status_perkawinan', 'Kawin')
        ->set('kewarganegaraan', 'Indonesia')
        ->call('saveProfil')
        ->assertHasNoErrors();

    $dosen->refresh();
    expect($dosen->tempat_lahir)->toBe('Bogor');
    expect($dosen->tanggal_lahir->format('Y-m-d'))->toBe('1990-01-15');
    expect($dosen->jenis_kelamin)->toBe('L');
    expect($dosen->status_perkawinan)->toBe('Kawin');
    expect($dosen->kewarganegaraan)->toBe('Indonesia');
});

it('changes the password when the current password is correct', function () {
    $dosenUser = dosenUser(['password' => Hash::make('password-lama')]);

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
    $dosenUser = dosenUser(['password' => Hash::make('password-lama')]);

    Livewire::actingAs($dosenUser)
        ->test(Profil::class)
        ->set('current_password', 'salah')
        ->set('new_password', 'password-baru')
        ->set('new_password_confirmation', 'password-baru')
        ->call('savePassword')
        ->assertHasErrors(['current_password']);
});

it('uploads a new foto', function () {
    Storage::fake('public');

    $dosenUser = dosenUser();
    $dosen = Dosen::where('id_user', $dosenUser->id)->firstOrFail();

    Livewire::actingAs($dosenUser)
        ->test(Profil::class)
        ->set('foto_upload', UploadedFile::fake()->image('foto.jpg'))
        ->call('saveFoto')
        ->assertHasNoErrors();

    $dosen->refresh();
    expect($dosen->foto)->not->toBeNull();
    Storage::disk('public')->assertExists($dosen->foto);
});

it('rejects a non-image foto upload', function () {
    Storage::fake('public');

    $dosenUser = dosenUser();

    Livewire::actingAs($dosenUser)
        ->test(Profil::class)
        ->set('foto_upload', UploadedFile::fake()->create('dokumen.pdf', 100))
        ->call('saveFoto')
        ->assertHasErrors(['foto_upload']);
});
