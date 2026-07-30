<?php

use App\Services\EnvFileWriter;

beforeEach(function () {
    $this->path = tempnam(sys_get_temp_dir(), 'envtest_');
    file_put_contents($this->path, <<<'ENV'
    APP_NAME="Sikampus"
    APP_ENV=local
    MAIL_MAILER=log
    MAIL_HOST=127.0.0.1
    MAIL_PORT=2525
    MAIL_USERNAME=null
    MAIL_FROM_ADDRESS="hello@example.com"
    MAIL_FROM_NAME="${APP_NAME}"
    ENV);
});

afterEach(function () {
    if (file_exists($this->path)) {
        unlink($this->path);
    }
});

it('reads an unquoted value', function () {
    $env = new EnvFileWriter($this->path);

    expect($env->get('MAIL_HOST'))->toBe('127.0.0.1');
    expect($env->get('MAIL_PORT'))->toBe('2525');
});

it('reads a quoted value without the surrounding quotes', function () {
    $env = new EnvFileWriter($this->path);

    expect($env->get('MAIL_FROM_ADDRESS'))->toBe('hello@example.com');
});

it('reads a bare null token as an empty string, matching Laravel\'s own env() convention', function () {
    $env = new EnvFileWriter($this->path);

    // MAIL_USERNAME=null (tanpa kutip) di file seed beforeEach.
    expect($env->get('MAIL_USERNAME'))->toBe('');
});

it('returns an empty string for a key that does not exist', function () {
    $env = new EnvFileWriter($this->path);

    expect($env->get('MAIL_PASSWORD'))->toBe('');
});

it('overwrites an existing key in place without touching other lines', function () {
    $env = new EnvFileWriter($this->path);

    $env->set(['MAIL_HOST' => 'smtp.contoh.com']);

    $content = file_get_contents($this->path);
    expect($env->get('MAIL_HOST'))->toBe('smtp.contoh.com');
    expect($content)->toContain('APP_NAME="Sikampus"');
    expect($content)->toContain('APP_ENV=local');
    // Baris lain tidak digandakan.
    expect(substr_count($content, 'MAIL_HOST='))->toBe(1);
});

it('appends a new key that does not exist yet', function () {
    $env = new EnvFileWriter($this->path);

    $env->set(['MAIL_PASSWORD' => 'rahasia']);

    expect($env->get('MAIL_PASSWORD'))->toBe('rahasia');
});

it('quotes values containing special characters and leaves simple tokens bare', function () {
    $env = new EnvFileWriter($this->path);

    $env->set([
        'MAIL_USERNAME' => 'user@contoh.com',
        'MAIL_PORT' => '465',
    ]);

    $content = file_get_contents($this->path);
    expect($content)->toContain('MAIL_USERNAME="user@contoh.com"');
    expect($content)->toContain('MAIL_PORT=465');
    expect($env->get('MAIL_USERNAME'))->toBe('user@contoh.com');
});

it('preserves a literal variable reference like ${APP_NAME} when that key is not touched', function () {
    $env = new EnvFileWriter($this->path);

    $env->set(['MAIL_HOST' => 'smtp.contoh.com']);

    expect($env->get('MAIL_FROM_NAME'))->toBe('${APP_NAME}');
});

it('reports writability based on the real file/directory state', function () {
    $env = new EnvFileWriter($this->path);

    expect($env->exists())->toBeTrue();
    expect($env->isWritable())->toBeTrue();
});

it('reports a missing file as not existing but checks the parent directory for writability', function () {
    $missingPath = sys_get_temp_dir().'/envtest_missing_'.uniqid().'.env';
    $env = new EnvFileWriter($missingPath);

    expect($env->exists())->toBeFalse();
    expect($env->isWritable())->toBeTrue();
});
