<?php

use App\Livewire\Admin\PerguruanTinggi;
use App\Models\Kota;
use App\Models\Provinsi;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.perguruan-tinggi'))->assertRedirect(route('login'));
});

it('renders the page prefilled with existing settings', function () {
    $admin = adminUser();
    Setting::create(['key' => 'app_univ_name', 'value' => 'Universitas Uji']);
    Setting::create(['key' => 'app_univ_email', 'value' => 'info@uji.ac.id']);

    $this->actingAs($admin)->get(route('admin.perguruan-tinggi'))
        ->assertOk()
        ->assertSee('Universitas Uji');

    Livewire::actingAs($admin)
        ->test(PerguruanTinggi::class)
        ->assertSet('nama', 'Universitas Uji')
        ->assertSet('email', 'info@uji.ac.id');
});

it('resolves provinsi and kota dropdowns from stored setting names', function () {
    $admin = adminUser();
    Provinsi::factory()->create(['nama' => 'Jawa Barat']);
    // Provinsi::create() tidak mengisi balik id auto-increment ke instance-nya (lihat bug yang
    // dilaporkan lewat spawn_task), jadi id yang valid harus diambil lewat query ulang.
    $provinsi = Provinsi::where('nama', 'Jawa Barat')->firstOrFail();
    Kota::factory()->create(['nama' => 'Kota Bogor', 'id_provinsi' => $provinsi->id]);
    $kota = Kota::where('nama', 'Kota Bogor')->firstOrFail();
    Setting::create(['key' => 'app_univ_province', 'value' => 'Jawa Barat']);
    Setting::create(['key' => 'app_univ_city', 'value' => 'Kota Bogor']);

    Livewire::actingAs($admin)
        ->test(PerguruanTinggi::class)
        ->assertSet('id_provinsi', $provinsi->id)
        ->assertSet('id_kota', $kota->id);
});

it('saves the form as settings rows, resolving provinsi/kota id back to nama', function () {
    $admin = adminUser();
    Provinsi::factory()->create(['nama' => 'Jawa Barat']);
    $provinsi = Provinsi::where('nama', 'Jawa Barat')->firstOrFail();
    Kota::factory()->create(['nama' => 'Kota Bogor', 'id_provinsi' => $provinsi->id]);
    $kota = Kota::where('nama', 'Kota Bogor')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(PerguruanTinggi::class)
        ->set('nama', 'Universitas Bersama')
        ->set('alamat', 'Jl. Contoh No. 1')
        ->set('id_provinsi', $provinsi->id)
        ->set('id_kota', $kota->id)
        ->set('email', 'info@bersama.ac.id')
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::where('key', 'app_univ_name')->value('value'))->toBe('Universitas Bersama');
    expect(Setting::where('key', 'app_univ_address')->value('value'))->toBe('Jl. Contoh No. 1');
    expect(Setting::where('key', 'app_univ_province')->value('value'))->toBe('Jawa Barat');
    expect(Setting::where('key', 'app_univ_city')->value('value'))->toBe('Kota Bogor');
    expect(Setting::where('key', 'app_univ_email')->value('value'))->toBe('info@bersama.ac.id');
});

it('clears the selected kota when provinsi changes', function () {
    $admin = adminUser();
    Provinsi::factory()->create(['nama' => 'Provinsi A']);
    $provinsiA = Provinsi::where('nama', 'Provinsi A')->firstOrFail();
    Kota::factory()->create(['nama' => 'Kota A', 'id_provinsi' => $provinsiA->id]);
    $kota = Kota::where('nama', 'Kota A')->firstOrFail();
    Provinsi::factory()->create(['nama' => 'Provinsi B']);
    $provinsiB = Provinsi::where('nama', 'Provinsi B')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(PerguruanTinggi::class)
        ->set('id_provinsi', $provinsiA->id)
        ->set('id_kota', $kota->id)
        ->set('id_provinsi', $provinsiB->id)
        ->assertSet('id_kota', null);
});

it('requires nama', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(PerguruanTinggi::class)
        ->set('nama', '')
        ->call('save')
        ->assertHasErrors(['nama']);
});

it('uploads a logo and stores it under the public logos folder', function () {
    Storage::fake('public');
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(PerguruanTinggi::class)
        ->set('logoUpload', UploadedFile::fake()->image('logo.png'))
        ->assertSet('logoUpload', null);

    $files = Storage::disk('public')->files('logos');
    expect($files)->toHaveCount(1);
});

it('removes the logo from the form without deleting the stored file', function () {
    $admin = adminUser();
    Setting::create(['key' => 'app_univ_logo', 'value' => 'http://localhost/storage/logos/existing.png']);

    Livewire::actingAs($admin)
        ->test(PerguruanTinggi::class)
        ->assertSet('logo', 'http://localhost/storage/logos/existing.png')
        ->set('nama', 'Universitas Uji')
        ->call('removeLogo')
        ->assertSet('logo', '')
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::where('key', 'app_univ_logo')->value('value'))->toBe('');
});
