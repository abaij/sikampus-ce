<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $user, string $token): string {
            $frontendUrl = rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/');
            $email = urlencode((string) $user->email);
            $token = urlencode($token);

            return "{$frontendUrl}/reset-password?token={$token}&email={$email}";
        });

        // Dibagikan lewat View Composer (bukan @php di layouts/web.blade.php) karena view anak yang
        // extends layout ini (mis. auth/login.blade.php) merender @section-nya SEBELUM parent — jadi
        // variabel @php di parent tidak pernah terlihat oleh section milik anak. Composer men-supply
        // variabel yang sama ke kedua view secara independen, jadi favicon, brand mark header,
        // footer, dan brand mark halaman login semuanya konsisten dari satu sumber.
        View::composer(['layouts.web', 'auth.login'], function ($view): void {
            $univSettings = Setting::whereIn('key', ['app_univ_name', 'app_univ_logo'])->pluck('value', 'key');
            $namaPerguruanTinggi = trim((string) $univSettings->get('app_univ_name'));
            $logoPerguruanTinggi = trim((string) $univSettings->get('app_univ_logo'));

            $logoPerguruanTinggiSrc = null;
            if ($logoPerguruanTinggi !== '') {
                $logoPerguruanTinggiSrc = str_starts_with($logoPerguruanTinggi, 'http') || str_starts_with($logoPerguruanTinggi, 'data:image')
                    ? $logoPerguruanTinggi
                    : asset(ltrim($logoPerguruanTinggi, '/'));
            }

            $view->with(compact('namaPerguruanTinggi', 'logoPerguruanTinggiSrc'));
        });
    }
}
