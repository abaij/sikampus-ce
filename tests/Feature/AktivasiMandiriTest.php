<?php

use App\Livewire\Auth\Aktivasi;
use App\Mail\VerifyEmailActivation;
use App\Models\Dosen;
use App\Models\Mahasiswa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
});

it('menemukan mahasiswa tanpa akun dan lanjut ke step 2', function () {
    $mahasiswa = Mahasiswa::factory()->create(['id_user' => null]);

    Livewire::test(Aktivasi::class)
        ->set('role', 'mahasiswa')
        ->set('identifier', $mahasiswa->nim)
        ->call('checkIdentifier')
        ->assertSet('step', 2)
        ->assertSet('hasAccount', false)
        ->assertSet('email', $mahasiswa->email);
});

it('menampilkan pesan gagal saat nim tidak ditemukan', function () {
    Livewire::test(Aktivasi::class)
        ->set('role', 'mahasiswa')
        ->set('identifier', 'NIM-TIDAK-ADA')
        ->call('checkIdentifier')
        ->assertSet('step', 1)
        ->assertSet('errorMessage', 'NIM tidak ditemukan.');
});

it('mendeteksi mahasiswa yang sudah punya akun', function () {
    $user = User::factory()->create(['email_verified_at' => null]);
    $mahasiswa = Mahasiswa::factory()->create(['id_user' => $user->id]);

    Livewire::test(Aktivasi::class)
        ->set('role', 'mahasiswa')
        ->set('identifier', $mahasiswa->nim)
        ->call('checkIdentifier')
        ->assertSet('step', 1)
        ->assertSet('hasAccount', true)
        ->assertSet('emailVerified', false);
});

it('membuat akun mahasiswa baru dan mengirim email verifikasi', function () {
    $mahasiswa = Mahasiswa::factory()->create(['id_user' => null]);

    Livewire::test(Aktivasi::class)
        ->set('role', 'mahasiswa')
        ->set('identifier', $mahasiswa->nim)
        ->call('checkIdentifier')
        ->set('email', 'mhs.baru@example.com')
        ->set('password', 'password123')
        ->set('passwordConfirmation', 'password123')
        ->call('register')
        ->assertSet('step', 3);

    $mahasiswa->refresh();
    expect($mahasiswa->id_user)->not->toBeNull();

    $user = User::find($mahasiswa->id_user);
    expect($user->username)->toBe($mahasiswa->nim)
        ->and($user->email)->toBe('mhs.baru@example.com')
        ->and($user->role)->toBe('mahasiswa')
        ->and($user->email_verified_at)->toBeNull();

    expect(DB::table('email_verifications')->where('email', 'mhs.baru@example.com')->exists())->toBeTrue();

    Mail::assertQueued(VerifyEmailActivation::class, function ($mail) use ($user) {
        return $mail->user->is($user) && str_contains($mail->verificationUrl, route('verify-email'));
    });
});

it('membuat akun dosen baru dengan email wajib diisi manual', function () {
    $dosen = Dosen::factory()->create(['id_user' => null]);

    Livewire::test(Aktivasi::class)
        ->set('role', 'dosen')
        ->set('identifier', $dosen->kode_dosen)
        ->call('checkIdentifier')
        ->assertSet('step', 2)
        ->assertSet('email', '')
        ->set('email', 'dosen.baru@example.com')
        ->set('password', 'password123')
        ->set('passwordConfirmation', 'password123')
        ->call('register')
        ->assertSet('step', 3);

    $dosen->refresh();
    expect($dosen->id_user)->not->toBeNull();

    $user = User::find($dosen->id_user);
    expect($user->username)->toBe($dosen->kode_dosen)
        ->and($user->role)->toBe('dosen');
});

it('menolak registrasi jika konfirmasi password tidak cocok', function () {
    $mahasiswa = Mahasiswa::factory()->create(['id_user' => null]);

    Livewire::test(Aktivasi::class)
        ->set('role', 'mahasiswa')
        ->set('identifier', $mahasiswa->nim)
        ->call('checkIdentifier')
        ->set('email', 'mhs@example.com')
        ->set('password', 'password123')
        ->set('passwordConfirmation', 'lainnya123')
        ->call('register')
        ->assertHasErrors(['passwordConfirmation']);

    $mahasiswa->refresh();
    expect($mahasiswa->id_user)->toBeNull();
});

it('mengirim ulang email verifikasi untuk akun yang belum terverifikasi', function () {
    $user = User::factory()->create(['email_verified_at' => null]);
    $mahasiswa = Mahasiswa::factory()->create(['id_user' => $user->id, 'email' => $user->email]);

    Livewire::test(Aktivasi::class)
        ->set('role', 'mahasiswa')
        ->set('identifier', $mahasiswa->nim)
        ->call('checkIdentifier')
        ->call('resendVerification')
        ->assertSet('successMessage', 'Email verifikasi telah dikirim ulang. Silakan cek inbox email Anda.');

    Mail::assertQueued(VerifyEmailActivation::class);
});

it('memverifikasi email lewat token yang valid', function () {
    $mahasiswa = Mahasiswa::factory()->create(['id_user' => null]);

    Livewire::test(Aktivasi::class)
        ->set('role', 'mahasiswa')
        ->set('identifier', $mahasiswa->nim)
        ->call('checkIdentifier')
        ->set('email', 'verify.me@example.com')
        ->set('password', 'password123')
        ->set('passwordConfirmation', 'password123')
        ->call('register');

    $verification = DB::table('email_verifications')->where('email', 'verify.me@example.com')->first();

    $this->get('/verify-email?'.http_build_query(['token' => $verification->token, 'email' => 'verify.me@example.com']))
        ->assertOk()
        ->assertSee('Email berhasil diverifikasi');

    $user = User::where('email', 'verify.me@example.com')->first();
    expect($user->email_verified_at)->not->toBeNull();
});

it('menolak token verifikasi yang tidak valid', function () {
    $this->get('/verify-email?'.http_build_query(['token' => 'token-ngawur', 'email' => 'siapa@example.com']))
        ->assertOk()
        ->assertSee('Verifikasi gagal');
});
