<?php

namespace App\Support\Plugins;

use App\Models\Plugin;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mendaftarkan service provider tiap plugin yang enabled saat boot aplikasi.
 * Dipanggil dari AppServiceProvider::register() — pada tahap ini Laravel belum
 * mem-parse routes/web.php atau routes/api.php (withRouting() menunda loadRoutes()
 * sampai SEMUA provider selesai register()+boot(), lihat RouteServiceProvider),
 * jadi loadRoutesFrom()/loadMigrationsFrom() yang dipanggil provider plugin di
 * boot()-nya dijamin selesai sebelum app coba resolve route apa pun.
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

            $plugins = Plugin::enabled()->get();
        } catch (Throwable $e) {
            return;
        }

        foreach ($plugins as $plugin) {
            self::registerAutoloader($plugin);

            try {
                $app->register($plugin->provider_class);
            } catch (Throwable $e) {
                // Satu plugin rusak/hilang tidak boleh men-500-kan seluruh app
                // untuk semua user — log lalu lanjut ke plugin berikutnya.
                report($e);

                continue;
            }
        }
    }

    private static function registerAutoloader(Plugin $plugin): void
    {
        $namespacePrefix = 'Plugins\\'.Str::studly($plugin->slug).'\\';
        $srcDir = rtrim($plugin->sourceAbsolutePath(), '/').'/src/';

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
