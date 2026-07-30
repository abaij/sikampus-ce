<?php

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\ResetPassword;
use App\Mail\ResetPasswordMandiri;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
});

it('mengirim link reset password untuk email yang terdaftar', function () {
    $user = User::factory()->create();

    Livewire::test(ForgotPassword::class)
        ->set('email', $user->email)
        ->call('sendResetLink')
        ->assertSet('errorMessage', '')
        ->assertSet('successMessage', 'Link reset password berhasil dikirim ke email Anda. Silakan cek inbox Anda.');

    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeTrue();

    Mail::assertQueued(ResetPasswordMandiri::class, function ($mail) use ($user) {
        return $mail->user->is($user) && str_contains($mail->resetUrl, route('reset-password')) && str_contains($mail->resetUrl, urlencode($user->email));
    });
});

it('menolak email yang tidak terdaftar', function () {
    Livewire::test(ForgotPassword::class)
        ->set('email', 'tidak-ada@example.com')
        ->call('sendResetLink')
        ->assertSet('successMessage', '');

    Mail::assertNothingQueued();
});

it('memperbarui password lewat link yang valid', function () {
    $user = User::factory()->create(['password' => Hash::make('password-lama')]);

    Livewire::test(ForgotPassword::class)
        ->set('email', $user->email)
        ->call('sendResetLink');

    $tokenRow = DB::table('password_reset_tokens')->where('email', $user->email)->first();

    $capturedUrl = null;
    Mail::assertQueued(ResetPasswordMandiri::class, function ($mail) use (&$capturedUrl) {
        $capturedUrl = $mail->resetUrl;

        return true;
    });

    $query = [];
    parse_str((string) parse_url($capturedUrl, PHP_URL_QUERY), $query);

    $this->get('/reset-password?'.http_build_query(['token' => $query['token'], 'email' => $user->email]))
        ->assertOk()
        ->assertSee('Password Baru');

    Livewire::withQueryParams(['token' => $query['token'], 'email' => $user->email])
        ->test(ResetPassword::class)
        ->set('password', 'password-baru123')
        ->set('passwordConfirmation', 'password-baru123')
        ->call('resetPassword')
        ->assertSet('successMessage', 'Password berhasil diperbarui. Silakan masuk dengan password baru Anda.');

    $user->refresh();
    expect(Hash::check('password-baru123', $user->password))->toBeTrue();
    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();
});

it('menolak reset password jika konfirmasi tidak cocok', function () {
    $user = User::factory()->create();

    Livewire::withQueryParams(['token' => 'sembarang-token', 'email' => $user->email])
        ->test(ResetPassword::class)
        ->set('password', 'password-baru123')
        ->set('passwordConfirmation', 'beda123456')
        ->call('resetPassword')
        ->assertHasErrors(['passwordConfirmation']);
});

it('menolak reset password dengan token tidak valid', function () {
    $user = User::factory()->create();

    Livewire::withQueryParams(['token' => 'token-ngawur', 'email' => $user->email])
        ->test(ResetPassword::class)
        ->set('password', 'password-baru123')
        ->set('passwordConfirmation', 'password-baru123')
        ->call('resetPassword')
        ->assertSet('successMessage', '');
});

it('menampilkan halaman error saat token/email tidak ada di query string', function () {
    $this->get('/reset-password')
        ->assertOk()
        ->assertSee('Link tidak valid');
});
