<?php

namespace App\Support\Plugins;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mendaftarkan service provider tiap plugin yang enabled saat boot aplikasi.
 * Dipanggil dari AppServiceProvider::register() lewat callback booting() — di
 * titik itu SEMUA provider (termasuk DatabaseServiceProvider milik framework)
 * sudah selesai register(), tapi belum tentu selesai boot(). Secara spesifik
 * Model::$resolver (dipakai Eloquent) baru di-set di
 * DatabaseServiceProvider::boot(), jadi query di sini SENGAJA pakai query
 * builder mentah (DB::table) alih-alih Eloquent (App\Models\Plugin) — Eloquent
 * akan gagal dengan "Call to a member function connection() on null" kalau
 * dipanggil sebelum DatabaseServiceProvider::boot() jalan.
 *
 * PENTING: provider plugin SENGAJA TIDAK didaftarkan lewat $app->register().
 * Kenapa: di titik ini (callback booting()) $app belum isBooted(), jadi
 * $app->register() tidak langsung memanggil boot() providernya — ia cuma
 * menaruh provider itu ke daftar $app->serviceProviders, yang BELAKANGAN
 * di-boot oleh loop internal Illuminate\Foundation\Application::boot()
 * (array_walk atas serviceProviders) TANPA try/catch sama sekali. Akibatnya
 * try/catch di sini dulu terlihat melindungi, padahal boot() plugin yang
 * sebenarnya lempar exception tetap merambat sampai ke Kernel dan men-500-kan
 * SELURUH aplikasi untuk semua user — persis kebalikan dari tujuan "satu
 * plugin rusak tidak boleh mematikan seluruh app". Supaya isolasi ini benar
 * -benar berlaku, register() DAN boot() plugin dipanggil manual di sini,
 * masing-masing dibungkus try/catch sendiri — boot() ditunda lewat
 * $app->booted(...) (dijadwalkan, bukan dipanggil langsung) supaya baru jalan
 * setelah SEMUA provider inti (termasuk yang menyiapkan routing) selesai.
 */
class PluginBootManager
{
    public static function bootEnabledPlugins(Application $app): void
    {
        try {
            if (! Schema::hasTable('plugins')) {
                // Fresh install: migration `plugins` belum jalan, atau console
                // command (mis. migrate) belum sempat konek DB.
                return;
            }

            $plugins = DB::table('plugins')->where('enabled', true)->get();
        } catch (Throwable $e) {
            return;
        }

        // ServiceProvider::loadRoutesFrom() (dipanggil provider tiap plugin di
        // boot()-nya) SENGAJA no-op kalau route sudah di-cache
        // (php artisan route:cache) — Laravel menganggap file cache itu snapshot
        // final semua route, jadi route plugin manapun yang dipasang/enabled
        // SETELAH cache dibuat tidak akan pernah terdaftar sampai cache
        // dibersihkan. Ini bukan bug di plugin-nya sendiri, tapi kalau tidak
        // di-log eksplisit di sini gejalanya cuma muncul jauh di hilir sebagai
        // RouteNotFoundException ("Route [...] not defined") yang membingungkan
        // saat di-debug. `SuperadminPluginController::enable()`/`disable()`
        // sudah memanggil `route:clear` setiap kali, tapi itu tidak mencegah
        // deploy berikutnya meng-cache ulang — proses deploy aplikasi ini
        // sebaiknya TIDAK menjalankan `route:cache`/`artisan optimize` selama
        // sistem plugin dipakai.
        $hasRouteBearingPlugin = collect($plugins)->contains(fn ($plugin) => $plugin->has_web_routes || $plugin->has_api_routes);

        if ($hasRouteBearingPlugin && $app instanceof CachesRoutes && $app->routesAreCached()) {
            Log::warning('Plugin dengan route terinstal & enabled, tapi routes sedang di-cache (routes:cache) — route plugin TIDAK akan terdaftar sampai cache dibersihkan (php artisan route:clear) dan proses deploy berhenti menjalankan route:cache/artisan optimize.');
        }

        foreach ($plugins as $plugin) {
            self::registerAutoloader($plugin);
            self::registerAndBoot($app, $plugin);
        }
    }

    private static function registerAndBoot(Application $app, object $plugin): void
    {
        $providerClass = $plugin->provider_class;

        try {
            /** @var ServiceProvider $provider */
            $provider = new $providerClass($app);
            $provider->register();

            // Meniru apa yang Application::register() lakukan untuk properti
            // $bindings/$singletons deklaratif — supaya provider plugin yang
            // memakainya tetap berperilaku seperti provider biasa.
            if (isset($provider->bindings) && is_array($provider->bindings)) {
                foreach ($provider->bindings as $abstract => $concrete) {
                    $app->bind($abstract, $concrete);
                }
            }

            if (isset($provider->singletons) && is_array($provider->singletons)) {
                foreach ($provider->singletons as $abstract => $concrete) {
                    $app->singleton($abstract, $concrete);
                }
            }
        } catch (Throwable $e) {
            // Satu plugin rusak/hilang (mis. class tidak ketemu, register()
            // lempar exception) tidak boleh men-500-kan seluruh app untuk
            // semua user — log lalu lanjut ke plugin berikutnya.
            report($e);

            return;
        }

        // boot() ditunda ke fase bootedCallbacks (setelah SEMUA provider inti,
        // termasuk routing, selesai) dan dibungkus try/catch terpisah di sini
        // — supaya isolasinya benar-benar berlaku, bukan cuma dijanjikan.
        $app->booted(function () use ($provider): void {
            try {
                if (method_exists($provider, 'boot')) {
                    app()->call([$provider, 'boot']);
                }
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    private static function registerAutoloader(object $plugin): void
    {
        $namespacePrefix = 'Plugins\\'.Str::studly($plugin->slug).'\\';
        $srcDir = rtrim(base_path($plugin->source_path), '/').'/src/';

        spl_autoload_register(function (string $class) use ($namespacePrefix, $srcDir): void {
            if (! str_starts_with($class, $namespacePrefix)) {
                return;
            }

            $relative = substr($class, strlen($namespacePrefix));
            $file = $srcDir.str_replace('\\', '/', $relative).'.php';

            if (is_file($file)) {
                require_once $file;
            }
        });
    }
}
