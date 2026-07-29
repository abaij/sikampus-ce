<?php

namespace App\Providers;

use App\Models\Dosen;
use App\Models\Mahasiswa;
use App\Models\Semester;
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
        View::composer(['layouts.web', 'layouts.dosen', 'layouts.mahasiswa', 'auth.login'], function ($view): void {
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

        // Hanya untuk navbar panel (layouts.web), bukan halaman login — semester aktif tidak relevan
        // sebelum user masuk.
        View::composer('layouts.web', function ($view): void {
            $semesterAktif = Semester::where('is_active', true)->whereNull('deleted_at')->value('nama');

            $view->with('semesterAktif', $semesterAktif);
        });

        // Sidebar dosen (layouts.dosen) butuh kode_dosen & status kaprodi/sekprodi untuk tombol
        // "Administrasi Prodi" — dibagikan lewat composer dengan alasan sama seperti di atas
        // (variabel @php di parent tidak terlihat oleh @section milik anak).
        View::composer('layouts.dosen', function ($view): void {
            $user = auth()->user();
            $dosen = $user ? Dosen::where('id_user', $user->id)->first() : null;

            // Portal /prodi (kaprodi/sekprodi) hanya ada di siak-frontend (Next.js), tidak ada
            // padanan di panel Blade ini — tombolnya membuka FRONTEND_URL di tab baru, sama
            // seperti ResetPassword::createUrlUsing() di atas.
            $frontendUrl = rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/');

            $view->with([
                'dosenSidebarKodeDosen' => $dosen?->kode_dosen,
                'dosenSidebarFotoUrl' => $dosen?->foto ? asset('storage/'.ltrim($dosen->foto, '/')) : null,
                'dosenHasProdiScope' => $user?->hasProdiScope() ?? false,
                'dosenProdiPortalUrl' => $frontendUrl.'/prodi',
            ]);
        });

        // Sidebar mahasiswa (layouts.mahasiswa) butuh NIM untuk kartu info user — dibagikan
        // lewat composer dengan alasan sama seperti layouts.dosen di atas.
        View::composer('layouts.mahasiswa', function ($view): void {
            $user = auth()->user();
            $mahasiswa = $user ? Mahasiswa::where('id_user', $user->id)->first() : null;

            $view->with('mahasiswaSidebarNim', $mahasiswa?->nim);
        });
    }
}
