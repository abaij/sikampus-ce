<?php

use App\Livewire\Admin\Sistem\Pengaturan;
use App\Models\Setting;
use App\Providers\AppServiceProvider;
use Livewire\Livewire;

it('redirects unauthenticated users to the admin login page', function () {
    $this->get(route('admin.sistem.pengaturan'))->assertRedirect(route('login'));
});

it('forbids an admin who is not superadmin', function () {
    $admin = adminUser('admin_akademik');

    $this->actingAs($admin)->get(route('admin.sistem.pengaturan'))->assertForbidden();
});

it('renders prefilled from existing settings for a superadmin, without ever echoing the stored password', function () {
    Setting::create(['key' => 'app_mail_host', 'value' => 'mail.kandaga.com']);
    Setting::create(['key' => 'app_mail_port', 'value' => '465']);
    Setting::create(['key' => 'app_mail_username', 'value' => 'siak@sikampus.com']);
    Setting::create(['key' => 'app_mail_password', 'value' => 'rahasia-lama']);
    Setting::create(['key' => 'app_mail_encryption', 'value' => 'smtps']);
    Setting::create(['key' => 'app_mail_from_address', 'value' => 'siak@sikampus.com']);
    Setting::create(['key' => 'app_mail_from_name', 'value' => 'SIAK']);
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Pengaturan::class)
        ->assertSet('host', 'mail.kandaga.com')
        ->assertSet('port', '465')
        ->assertSet('username', 'siak@sikampus.com')
        ->assertSet('encryption', 'smtps')
        ->assertSet('fromAddress', 'siak@sikampus.com')
        ->assertSet('fromName', 'SIAK')
        ->assertSet('password', '')
        ->assertSet('hasStoredPassword', true);
});

it('saves new smtp settings into the settings table', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Pengaturan::class)
        ->set('host', 'smtp.contoh.com')
        ->set('port', '587')
        ->set('username', 'noreply@contoh.com')
        ->set('password', 'sandi-baru')
        ->set('encryption', '')
        ->set('fromAddress', 'noreply@contoh.com')
        ->set('fromName', 'Kampus Contoh')
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::where('key', 'app_mail_host')->value('value'))->toBe('smtp.contoh.com');
    expect(Setting::where('key', 'app_mail_port')->value('value'))->toBe('587');
    expect(Setting::where('key', 'app_mail_username')->value('value'))->toBe('noreply@contoh.com');
    expect(Setting::where('key', 'app_mail_password')->value('value'))->toBe('sandi-baru');
    expect(Setting::where('key', 'app_mail_from_address')->value('value'))->toBe('noreply@contoh.com');
    expect(Setting::where('key', 'app_mail_from_name')->value('value'))->toBe('Kampus Contoh');
});

it('overwrites existing settings in place without creating duplicate rows', function () {
    Setting::create(['key' => 'app_mail_host', 'value' => 'mail.lama.com']);
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Pengaturan::class)
        ->set('host', 'mail.baru.com')
        ->set('port', '587')
        ->set('username', 'user@contoh.com')
        ->set('fromAddress', 'user@contoh.com')
        ->set('fromName', 'Kampus')
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::where('key', 'app_mail_host')->count())->toBe(1);
    expect(Setting::where('key', 'app_mail_host')->value('value'))->toBe('mail.baru.com');
});

it('keeps the stored password unchanged when the password field is left blank', function () {
    Setting::create(['key' => 'app_mail_host', 'value' => 'mail.lama.com']);
    Setting::create(['key' => 'app_mail_password', 'value' => 'jangan-berubah']);
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Pengaturan::class)
        ->set('host', 'mail.baru.com')
        ->set('port', '587')
        ->set('username', 'user@contoh.com')
        ->set('fromAddress', 'user@contoh.com')
        ->set('fromName', 'Kampus')
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::where('key', 'app_mail_host')->value('value'))->toBe('mail.baru.com');
    expect(Setting::where('key', 'app_mail_password')->value('value'))->toBe('jangan-berubah');
});

it('requires host, port, username, from address, and from name', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Pengaturan::class)
        ->set('host', '')
        ->set('port', '')
        ->set('username', '')
        ->set('fromAddress', 'bukan-email')
        ->set('fromName', '')
        ->call('save')
        ->assertHasErrors(['host', 'port', 'username', 'fromAddress', 'fromName']);
});

it('rejects a port outside the valid range', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Pengaturan::class)
        ->set('host', 'mail.contoh.com')
        ->set('port', '70000')
        ->set('username', 'user@contoh.com')
        ->set('fromAddress', 'user@contoh.com')
        ->set('fromName', 'Kampus')
        ->call('save')
        ->assertHasErrors(['port']);
});

it('applies saved smtp settings to the runtime mail config on the next request', function () {
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Pengaturan::class)
        ->set('host', 'smtp.contoh.com')
        ->set('port', '2525')
        ->set('username', 'user@contoh.com')
        ->set('encryption', 'smtps')
        ->set('fromAddress', 'user@contoh.com')
        ->set('fromName', 'Kampus Contoh')
        ->call('save')
        ->assertHasNoErrors();

    // AppServiceProvider::boot() membaca ulang tabel settings di setiap bootstrap request —
    // panggil method-nya langsung di sini untuk mensimulasikan itu (boot() sungguhan sudah jalan
    // sekali sebelum baris di atas disimpan, jadi config belum ter-refresh otomatis di test ini).
    (new AppServiceProvider(app()))->applyMailSettingsFromDatabase();

    expect(config('mail.default'))->toBe('smtp');
    expect(config('mail.mailers.smtp.host'))->toBe('smtp.contoh.com');
    expect(config('mail.mailers.smtp.port'))->toBe(2525);
    expect(config('mail.mailers.smtp.scheme'))->toBe('smtps');
    expect(config('mail.from.address'))->toBe('user@contoh.com');
    expect(config('mail.from.name'))->toBe('Kampus Contoh');
});
