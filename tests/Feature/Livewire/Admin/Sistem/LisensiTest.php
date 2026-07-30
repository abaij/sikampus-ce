<?php

use App\Livewire\Admin\Sistem\Lisensi;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.sistem.lisensi'))->assertRedirect(route('login'));
});

it('forbids an admin who is not superadmin', function () {
    $admin = adminUser('admin_akademik');

    $this->actingAs($admin)->get(route('admin.sistem.lisensi'))->assertForbidden();
});

it('renders prefilled from the existing app_license_key setting', function () {
    Setting::create(['key' => 'app_license_key', 'value' => 'ABCD-1234-EFGH-5678']);
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Lisensi::class)
        ->assertSet('licenseKey', 'ABCD-1234-EFGH-5678');
});

it('saves the license key into the settings table', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Lisensi::class)
        ->set('licenseKey', 'NEW-LICENSE-KEY-0001')
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::where('key', 'app_license_key')->value('value'))->toBe('NEW-LICENSE-KEY-0001');
});

it('overwrites an existing license key without creating a duplicate row', function () {
    Setting::create(['key' => 'app_license_key', 'value' => 'OLD-KEY']);
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Lisensi::class)
        ->assertSet('licenseKey', 'OLD-KEY')
        ->set('licenseKey', 'REPLACED-KEY')
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::where('key', 'app_license_key')->count())->toBe(1);
    expect(Setting::where('key', 'app_license_key')->value('value'))->toBe('REPLACED-KEY');
});

it('allows clearing the license key by saving an empty value', function () {
    Setting::create(['key' => 'app_license_key', 'value' => 'SOME-KEY']);
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Lisensi::class)
        ->set('licenseKey', '')
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::where('key', 'app_license_key')->value('value'))->toBe('');
});

it('rejects a license key longer than 255 characters', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Lisensi::class)
        ->set('licenseKey', str_repeat('A', 256))
        ->call('save')
        ->assertHasErrors(['licenseKey']);
});

it('reports the installation to the sikampus server when a license key is saved', function () {
    config(['sikampus_server.url' => 'https://sikampus.example.com']);
    Http::fake();
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Lisensi::class)
        ->set('licenseKey', 'NEW-LICENSE-KEY-0001')
        ->call('save')
        ->assertHasNoErrors();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://sikampus.example.com/api/installations'
            && $request['license_key'] === 'NEW-LICENSE-KEY-0001'
            && $request->hasHeader('Content-Type', 'application/json');
    });
});

it('does not report to the sikampus server when the server url is not configured', function () {
    config(['sikampus_server.url' => '']);
    Http::fake();
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Lisensi::class)
        ->set('licenseKey', 'NEW-LICENSE-KEY-0001')
        ->call('save')
        ->assertHasNoErrors();

    Http::assertNothingSent();
});

it('does not report to the sikampus server when the license key is cleared', function () {
    config(['sikampus_server.url' => 'https://sikampus.example.com']);
    Setting::create(['key' => 'app_license_key', 'value' => 'SOME-KEY']);
    Http::fake();
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Lisensi::class)
        ->set('licenseKey', '')
        ->call('save')
        ->assertHasNoErrors();

    Http::assertNothingSent();
});
