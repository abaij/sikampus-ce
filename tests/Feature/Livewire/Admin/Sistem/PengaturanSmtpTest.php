<?php

use App\Livewire\Admin\Sistem\Pengaturan;
use App\Services\EnvFileWriter;
use Livewire\Livewire;

// Path .env palsu di-bind ke container di setiap test (lihat bindFakeEnv()) supaya file .env
// proyek yang asli tidak pernah tersentuh sama sekali oleh test ini.
function bindFakeEnv(string $seed = ''): string
{
    $path = tempnam(sys_get_temp_dir(), 'envtest_');
    file_put_contents($path, $seed);
    app()->instance(EnvFileWriter::class, new EnvFileWriter($path));

    return $path;
}

afterEach(function () {
    if (isset($this->envPath) && file_exists($this->envPath)) {
        unlink($this->envPath);
    }
});

it('redirects unauthenticated users to the admin login page', function () {
    $this->envPath = bindFakeEnv();

    $this->get(route('admin.sistem.pengaturan'))->assertRedirect(route('login'));
});

it('forbids an admin who is not superadmin', function () {
    $this->envPath = bindFakeEnv();
    $admin = adminUser('admin_akademik');

    $this->actingAs($admin)->get(route('admin.sistem.pengaturan'))->assertForbidden();
});

it('renders prefilled from existing env values for a superadmin, without ever echoing the stored password', function () {
    $this->envPath = bindFakeEnv(<<<'ENV'
    MAIL_MAILER=smtp
    MAIL_HOST=mail.kandaga.com
    MAIL_PORT=465
    MAIL_USERNAME="siak@sikampus.com"
    MAIL_PASSWORD="rahasia-lama"
    MAIL_SCHEME=smtps
    MAIL_FROM_ADDRESS="siak@sikampus.com"
    MAIL_FROM_NAME="SIAK"
    ENV);
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

it('saves new smtp settings into the env file, leaving unrelated keys untouched', function () {
    $this->envPath = bindFakeEnv("APP_NAME=\"Sikampus\"\nAPP_ENV=local\n");
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

    $env = new EnvFileWriter($this->envPath);
    expect($env->get('MAIL_MAILER'))->toBe('smtp');
    expect($env->get('MAIL_HOST'))->toBe('smtp.contoh.com');
    expect($env->get('MAIL_PORT'))->toBe('587');
    expect($env->get('MAIL_USERNAME'))->toBe('noreply@contoh.com');
    expect($env->get('MAIL_PASSWORD'))->toBe('sandi-baru');
    expect($env->get('MAIL_FROM_ADDRESS'))->toBe('noreply@contoh.com');
    expect($env->get('MAIL_FROM_NAME'))->toBe('Kampus Contoh');
    expect($env->get('APP_NAME'))->toBe('Sikampus');
});

it('keeps the stored password unchanged when the password field is left blank', function () {
    $this->envPath = bindFakeEnv(<<<'ENV'
    MAIL_HOST=mail.lama.com
    MAIL_PASSWORD="jangan-berubah"
    ENV);
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

    $env = new EnvFileWriter($this->envPath);
    expect($env->get('MAIL_HOST'))->toBe('mail.baru.com');
    expect($env->get('MAIL_PASSWORD'))->toBe('jangan-berubah');
});

it('treats a legacy MAIL_SCHEME=null env line as the empty "otomatis" option and saves fine untouched', function () {
    // Reproduksi persis .env proyek ini saat ini: MAIL_SCHEME=null (bareword, bukan string
    // kosong). Tanpa normalisasi di EnvFileWriter, properti encryption akan berisi literal
    // "null" yang gagal validasi in:,smtps walau user tidak menyentuh dropdown-nya sama sekali.
    $this->envPath = bindFakeEnv(<<<'ENV'
    MAIL_HOST=mail.lama.com
    MAIL_SCHEME=null
    ENV);
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Pengaturan::class)
        ->assertSet('encryption', '')
        ->set('port', '587')
        ->set('username', 'user@contoh.com')
        ->set('fromAddress', 'user@contoh.com')
        ->set('fromName', 'Kampus')
        ->call('save')
        ->assertHasNoErrors();

    $env = new EnvFileWriter($this->envPath);
    expect($env->get('MAIL_SCHEME'))->toBe('');
});

it('requires host, port, username, from address, and from name', function () {
    $this->envPath = bindFakeEnv();
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
    $this->envPath = bindFakeEnv();
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
