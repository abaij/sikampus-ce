<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\Plugins\PluginInstallException;
use App\Http\Controllers\Controller;
use App\Models\Plugin;
use App\Services\Plugins\PluginInstaller;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class SuperadminPluginController extends Controller
{
    public function index(): View
    {
        $plugins = Plugin::orderBy('name')->get();

        return view('superadmin.plugins.index', compact('plugins'));
    }

    public function store(Request $request, PluginInstaller $installer): RedirectResponse
    {
        $validated = $request->validate([
            'plugin_zip' => ['required', 'file', 'mimes:zip', 'max:'.config('plugins.max_zip_size_kb')],
        ], [
            'plugin_zip.required' => 'Silakan pilih berkas ZIP plugin yang akan diunggah.',
            'plugin_zip.file' => 'Berkas yang dipilih tidak valid.',
            'plugin_zip.mimes' => 'Format berkas harus zip.',
            'plugin_zip.max' => 'Ukuran berkas melebihi batas maksimum yang diizinkan.',
        ]);

        try {
            $plugin = $installer->install($validated['plugin_zip'], $request->user());
        } catch (PluginInstallException $e) {
            return redirect()
                ->route('superadmin.plugins')
                ->with('error', 'Gagal instal plugin: '.$e->getMessage());
        }

        return redirect()
            ->route('superadmin.plugins')
            ->with('status', "Plugin \"{$plugin->name}\" berhasil diinstal. Aktifkan dari daftar di bawah untuk mulai menggunakannya.");
    }

    public function migrate(Plugin $plugin): RedirectResponse
    {
        if ($plugin->migrations_relative_path === null) {
            return redirect()
                ->route('superadmin.plugins')
                ->with('error', "Plugin \"{$plugin->name}\" tidak memiliki migration.");
        }

        Artisan::call('migrate', [
            '--path' => $plugin->migrations_relative_path,
            '--force' => true,
        ]);

        $plugin->update(['last_migrated_at' => now()]);

        return redirect()
            ->route('superadmin.plugins')
            ->with('status', "Migration plugin \"{$plugin->name}\" dijalankan.\n\n".Artisan::output());
    }

    public function enable(Plugin $plugin): RedirectResponse
    {
        // Dicek SEBELUM clearCaches() (yang memanggil route:clear) supaya
        // pesannya mencerminkan kondisi nyata yang tadi mencegah route plugin
        // ini terdaftar — loadRoutesFrom() di provider plugin no-op kalau
        // route sedang di-cache (lihat PluginBootManager).
        $app = App::getFacadeRoot();
        $hadCachedRoutes = ($plugin->has_web_routes || $plugin->has_api_routes)
            && $app instanceof CachesRoutes
            && $app->routesAreCached();

        $plugin->update(['enabled' => true, 'disabled_at' => null]);

        $this->clearCaches();

        $status = "Plugin \"{$plugin->name}\" diaktifkan.";

        if ($hadCachedRoutes) {
            $status .= ' Catatan: routes sempat ter-cache saat plugin ini diaktifkan (sudah dibersihkan otomatis) — '
                .'pastikan proses deploy aplikasi TIDAK menjalankan `route:cache`/`artisan optimize`, karena itu akan '
                .'membuat route plugin berhenti terdaftar lagi sampai `route:clear` dijalankan ulang.';
        }

        return redirect()
            ->route('superadmin.plugins')
            ->with('status', $status);
    }

    public function disable(Plugin $plugin): RedirectResponse
    {
        $plugin->update(['enabled' => false, 'disabled_at' => now()]);

        $this->clearCaches();

        return redirect()
            ->route('superadmin.plugins')
            ->with('status', "Plugin \"{$plugin->name}\" dinonaktifkan.");
    }

    public function destroy(Plugin $plugin, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'confirm_slug' => ['required', 'string'],
        ], [
            'confirm_slug.required' => 'Ketik ulang slug plugin untuk konfirmasi penghapusan.',
        ]);

        if ($validated['confirm_slug'] !== $plugin->slug) {
            return redirect()
                ->route('superadmin.plugins')
                ->with('error', 'Slug konfirmasi tidak cocok. Plugin tidak dihapus.');
        }

        $name = $plugin->name;

        if (File::isDirectory($plugin->sourceAbsolutePath())) {
            File::deleteDirectory($plugin->sourceAbsolutePath());
        }

        $plugin->delete();

        $this->clearCaches();

        return redirect()
            ->route('superadmin.plugins')
            ->with('status', "Plugin \"{$name}\" dihapus. Migration yang sudah dijalankan TIDAK di-rollback otomatis.");
    }

    /**
     * Route cache & opcache bisa membuat perubahan enabled/disabled/uninstall
     * tidak langsung terlihat — dibersihkan best-effort di sini.
     */
    private function clearCaches(): void
    {
        Artisan::call('route:clear');

        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }
}
