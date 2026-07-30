<?php

use App\Livewire\Admin\Sistem\Lisensi;
use App\Services\EnvFileWriter;
use Livewire\Livewire;

// Path .env palsu di-bind ke container di setiap test supaya file .env proyek yang asli tidak
// pernah tersentuh sama sekali oleh test ini — sama seperti PengaturanSmtpTest.
function bindFakeEnvForLisensi(string $seed = ''): string
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
    $this->envPath = bindFakeEnvForLisensi();

    $this->get(route('admin.sistem.lisensi'))->assertRedirect(route('login'));
});

it('forbids an admin who is not superadmin', function () {
    $this->envPath = bindFakeEnvForLisensi();
    $admin = adminUser('admin_akademik');

    $this->actingAs($admin)->get(route('admin.sistem.lisensi'))->assertForbidden();
});

it('renders prefilled from the existing APP_LICENSE_KEY value', function () {
    $this->envPath = bindFakeEnvForLisensi('APP_LICENSE_KEY="ABCD-1234-EFGH-5678"'.PHP_EOL);
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Lisensi::class)
        ->assertSet('licenseKey', 'ABCD-1234-EFGH-5678');
});

it('saves the license key into the env file, leaving unrelated keys untouched', function () {
    $this->envPath = bindFakeEnvForLisensi("APP_NAME=\"Sikampus\"\nAPP_ENV=local\n");
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Lisensi::class)
        ->set('licenseKey', 'NEW-LICENSE-KEY-0001')
        ->call('save')
        ->assertHasNoErrors();

    $env = new EnvFileWriter($this->envPath);
    expect($env->get('APP_LICENSE_KEY'))->toBe('NEW-LICENSE-KEY-0001');
    expect($env->get('APP_NAME'))->toBe('Sikampus');
});

it('overwrites an existing license key without duplicating the line', function () {
    $this->envPath = bindFakeEnvForLisensi('APP_LICENSE_KEY=OLD-KEY'.PHP_EOL);
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Lisensi::class)
        ->assertSet('licenseKey', 'OLD-KEY')
        ->set('licenseKey', 'REPLACED-KEY')
        ->call('save')
        ->assertHasNoErrors();

    $content = file_get_contents($this->envPath);
    expect(substr_count($content, 'APP_LICENSE_KEY='))->toBe(1);
    expect((new EnvFileWriter($this->envPath))->get('APP_LICENSE_KEY'))->toBe('REPLACED-KEY');
});

it('allows clearing the license key by saving an empty value', function () {
    $this->envPath = bindFakeEnvForLisensi('APP_LICENSE_KEY=SOME-KEY'.PHP_EOL);
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Lisensi::class)
        ->set('licenseKey', '')
        ->call('save')
        ->assertHasNoErrors();

    expect((new EnvFileWriter($this->envPath))->get('APP_LICENSE_KEY'))->toBe('');
});

it('rejects a license key longer than 255 characters', function () {
    $this->envPath = bindFakeEnvForLisensi();
    $admin = adminUser();

    Livewire::actingAs($admin)
        ->test(Lisensi::class)
        ->set('licenseKey', str_repeat('A', 256))
        ->call('save')
        ->assertHasErrors(['licenseKey']);
});
