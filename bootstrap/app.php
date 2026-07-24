<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Untuk request API yang belum login, jangan panggil route('login') (tidak ada di API)
        // sehingga tidak terjadi RouteNotFoundException. Redirect = null → nanti exception
        // handler mengembalikan 401 JSON berkat shouldRenderJsonWhen.
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('api/*')) {
                return null;
            }

            if ($request->is('admin/*')) {
                return route('admin.login');
            }

            return route('login');
        });

        // Aktifkan middleware stateful API agar Sanctum bisa memproses
        // permintaan berbasis cookie (SPA) maupun bearer token.
        $middleware->statefulApi();

        // Pastikan CORS middleware aktif untuk semua request
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        // Aktifkan CORS middleware untuk API routes
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // Aktifkan CORS middleware untuk web routes juga (untuk preflight requests)
        $middleware->web(append: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // Daftarkan alias untuk middleware role-based
        $middleware->alias([
            'guest' => \Illuminate\Auth\Middleware\RedirectIfAuthenticated::class,
            'role.admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'role.admin.web' => \App\Http\Middleware\EnsureUserIsAdminWeb::class,
            'role.superadmin' => \App\Http\Middleware\EnsureUserIsSuperadmin::class,
            'role.admin.keuangan' => \App\Http\Middleware\EnsureUserHasKeuanganAccess::class,
            'role.admin.prodi' => \App\Http\Middleware\EnsureUserIsAdminProdi::class,
            'role.mahasiswa' => \App\Http\Middleware\EnsureUserIsMahasiswa::class,
            'role.dosen' => \App\Http\Middleware\EnsureUserIsDosen::class,
            'partner.api.key' => \App\Http\Middleware\EnsurePartnerApiKey::class,
            'superadmin.web' => \App\Http\Middleware\EnsureUserIsSuperadminWeb::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Request ke API selalu dapat response JSON (termasuk 401 Unauthenticated),
        // agar tidak redirect ke route('login') yang tidak ada di API.
        $exceptions->shouldRenderJsonWhen(function ($request, $e) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
