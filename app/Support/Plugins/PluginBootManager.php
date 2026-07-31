<?php

namespace App\Support\Plugins;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
