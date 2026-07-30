<?php

use App\Livewire\Mahasiswa\YudisiumWisuda\Index as YudisiumWisudaIndex;
use App\Models\Mahasiswa;
use App\Models\User;
use App\Models\Wisuda;
use App\Models\WisudaMahasiswa;
use App\Models\Yudisium;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function ywMahasiswaUser(): array
{
    $user = User::factory()->create(['role' => 'mahasiswa']);
    $mahasiswa = Mahasiswa::factory()->create(['id_user' => $user->id]);

    return [$user, $mahasiswa];
}

it('redirects unauthenticated users to the login page', function () {
    $this->get(route('mahasiswa.akhir-studi.yudisium-wisuda'))->assertRedirect(route('login'));
});

it('shows placeholders when neither yudisium nor wisuda data exists', function () {
    [$user] = ywMahasiswaUser();

    $this->actingAs($user)->get(route('mahasiswa.akhir-studi.yudisium-wisuda'))
        ->assertOk()
        ->assertSee('Data yudisium belum tersedia')
        ->assertSee('belum terdaftar sebagai peserta wisuda');
});

it('shows yudisium data and lets an eligible mahasiswa register for wisuda', function () {
    [$user, $mahasiswa] = ywMahasiswaUser();
    Yudisium::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'no_ijazah' => 'IJZ-001']);
    $wisuda = Wisuda::factory()->create(['nama' => 'Wisuda Ke-50', 'status' => 'active']);

    $this->actingAs($user)->get(route('mahasiswa.akhir-studi.yudisium-wisuda'))
        ->assertOk()
        ->assertSee('IJZ-001')
        ->assertSee('Daftar wisuda');

    Livewire::actingAs($user)
        ->test(YudisiumWisudaIndex::class)
        ->call('openDaftarModal')
        ->assertSet('daftarIdWisuda', (string) $wisuda->id)
        ->call('submitDaftar')
        ->assertHasNoErrors();

    $row = WisudaMahasiswa::where('id_mahasiswa', $mahasiswa->id)->firstOrFail();
    expect($row->id_wisuda)->toBe($wisuda->id);
    expect($row->status)->toBe('pending');
});

it('blocks wisuda registration without yudisium data', function () {
    [$user] = ywMahasiswaUser();
    $wisuda = Wisuda::factory()->create(['status' => 'active']);

    Livewire::actingAs($user)
        ->test(YudisiumWisudaIndex::class)
        ->set('daftarIdWisuda', (string) $wisuda->id)
        ->call('submitDaftar')
        ->assertHasErrors('daftarIdWisuda');

    expect(WisudaMahasiswa::count())->toBe(0);
});

it('blocks a second wisuda registration once already registered', function () {
    [$user, $mahasiswa] = ywMahasiswaUser();
    Yudisium::factory()->create(['id_mahasiswa' => $mahasiswa->id]);
    $wisuda1 = Wisuda::factory()->create(['status' => 'active']);
    $wisuda2 = Wisuda::factory()->create(['status' => 'active']);
    WisudaMahasiswa::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_wisuda' => $wisuda1->id]);

    Livewire::actingAs($user)
        ->test(YudisiumWisudaIndex::class)
        ->set('daftarIdWisuda', (string) $wisuda2->id)
        ->call('submitDaftar')
        ->assertHasErrors('daftarIdWisuda');

    expect(WisudaMahasiswa::where('id_mahasiswa', $mahasiswa->id)->count())->toBe(1);
});

it('lets a registered mahasiswa upload their wisuda photo', function () {
    Storage::fake('public');

    [$user, $mahasiswa] = ywMahasiswaUser();
    Yudisium::factory()->create(['id_mahasiswa' => $mahasiswa->id]);
    $wisuda = Wisuda::factory()->create(['status' => 'active']);
    WisudaMahasiswa::factory()->create(['id_mahasiswa' => $mahasiswa->id, 'id_wisuda' => $wisuda->id]);

    Livewire::actingAs($user)
        ->test(YudisiumWisudaIndex::class)
        ->set('fotoFile', UploadedFile::fake()->image('wisuda.jpg'))
        ->call('uploadFoto')
        ->assertHasNoErrors();

    $row = WisudaMahasiswa::where('id_mahasiswa', $mahasiswa->id)->firstOrFail();
    Storage::disk('public')->assertExists($row->foto);
});

it('blocks photo upload before a wisuda schedule exists', function () {
    [$user, $mahasiswa] = ywMahasiswaUser();
    Yudisium::factory()->create(['id_mahasiswa' => $mahasiswa->id]);

    Livewire::actingAs($user)
        ->test(YudisiumWisudaIndex::class)
        ->set('fotoFile', UploadedFile::fake()->image('wisuda.jpg'))
        ->call('uploadFoto')
        ->assertHasErrors('fotoFile');
});
