<?php

use App\Livewire\Admin\Sistem\Plugin as PluginComponent;
use App\Models\Plugin;
use App\Services\Plugins\PluginManifestReader;
use App\Services\Plugins\PluginZipExtractor;
use App\Support\Plugins\PluginBootManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/**
 * Bangun ZIP fixture plugin yang valid di $zipPath. $extraEntries dipakai untuk
 * menyisipkan entry tambahan (mis. entry zip-slip atau entry berukuran besar)
 * setelah entry manifest/provider yang valid. $migrationBody, kalau diisi,
 * menggantikan isi default migration fixture (dipakai test migrate action agar
 * TIDAK menjalankan DDL asli — CREATE TABLE di MySQL melakukan implicit commit
 * yang merusak transaksi RefreshDatabase untuk sisa test suite berjalan).
 * $settingsRoute, kalau diisi, menambahkan field settings_route ke manifest
 * DAN mendaftarkan route bernama itu di routes/web.php fixture-nya.
 */
function buildPluginZip(
    string $zipPath,
    string $slug = 'test-plugin',
    array $extraEntries = [],
    ?string $migrationBody = null,
    ?string $settingsRoute = null,
): void {
    $studly = str_replace(' ', '', ucwords(str_replace('-', ' ', $slug)));
    $tableSlug = str_replace('-', '_', $slug);

    $manifest = [
        'name' => 'Test Plugin',
        'slug' => $slug,
        'version' => '1.0.0',
        'description' => 'Plugin fixture untuk test.',
        'provider' => "Plugins\\{$studly}\\{$studly}ServiceProvider",
    ];

    if ($settingsRoute) {
        $manifest['settings_route'] = $settingsRoute;
    }

    $settingsRouteLine = $settingsRoute
        ? "Route::get('/plugins/{$slug}/settings', fn () => 'settings page')->name('{$settingsRoute}');\n"
        : '';

    $entries = [
        'plugin.json' => json_encode($manifest),
        "src/{$studly}ServiceProvider.php" => <<<PHP
<?php

namespace Plugins\\{$studly};

use Illuminate\\Support\\ServiceProvider;

class {$studly}ServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        \$this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        \$this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}

PHP,
        'routes/web.php' => <<<PHP
<?php

use Illuminate\\Support\\Facades\\Route;

Route::get('/plugins/{$slug}/ping', function () {
    return response('pong');
});
{$settingsRouteLine}
PHP,
        "database/migrations/2026_01_01_000000_{$tableSlug}_create_dummy_table.php" => $migrationBody ?? <<<PHP
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_{$tableSlug}_dummy', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_{$tableSlug}_dummy');
    }
};

PHP,
    ];

    $entries = array_merge($entries, $extraEntries);

    File::ensureDirectoryExists(dirname($zipPath));

    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    foreach ($entries as $name => $content) {
        $zip->addFromString($name, $content);
    }

    $zip->close();
}

/**
 * Livewire::test()->set() untuk properti WithFileUploads butuh instance
 * Illuminate\Http\Testing\File (hasil UploadedFile::fake()), bukan
 * Illuminate\Http\UploadedFile biasa — internal testing harness Livewire
 * mengakses properti public $name yang cuma ada di kelas fake itu. Dibangun
 * dari createWithContent() (bukan create() yang isinya sampah acak) supaya
 * isi zip fixture yang sesungguhnya tetap terbawa dan bisa diekstrak/divalidasi
 * sungguhan oleh PluginInstaller.
 */
function pluginUploadedFile(string $zipPath): UploadedFile
{
    return UploadedFile::fake()->createWithContent(basename($zipPath), File::get($zipPath));
}

/**
 * source_path plugin disimpan relatif terhadap base_path() (lihat
 * Plugin::sourceAbsolutePath()), sedangkan config('plugins.install_path')
 * berupa path absolut (default: storage/app/plugins) — helper ini
 * menerjemahkan slug ke path relatif itu supaya test tidak hardcode lokasi.
 */
function pluginSourcePath(string $slug): string
{
    return trim(str_replace(base_path(), '', config('plugins.install_path')), '/').'/'.$slug;
}

afterEach(function () {
    File::deleteDirectory(config('plugins.install_path'));
    File::deleteDirectory(storage_path('app/private/plugin-fixtures'));

    // Test migrate action sengaja memakai migration DML-only (bukan CREATE TABLE)
    // supaya tidak memicu implicit commit MySQL yang merusak transaksi
    // RefreshDatabase untuk sisa test suite — bersihkan baris marker + baris
    // `migrations` yang tertinggal secara manual di sini.
    DB::table('settings')->where('key', 'plugin_test_plugin_migrated_marker')->delete();
    DB::table('migrations')->where('migration', 'like', '%test_plugin_create_dummy_table%')->delete();
});

it('installs a valid plugin zip and keeps it disabled by default', function () {
    $admin = adminUser();
    $zipPath = storage_path('app/private/plugin-fixtures/valid.zip');
    buildPluginZip($zipPath);

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->set('pluginZip', pluginUploadedFile($zipPath))
        ->call('install')
        ->assertHasNoErrors();

    $plugin = Plugin::where('slug', 'test-plugin')->first();

    expect($plugin)->not->toBeNull();
    expect($plugin->enabled)->toBeFalse();
    expect($plugin->provider_class)->toBe('Plugins\\TestPlugin\\TestPluginServiceProvider');
    expect(File::isDirectory(config('plugins.install_path').'/test-plugin'))->toBeTrue();
});

it('rejects a zip-slip attempt and does not write files outside plugins/', function () {
    $admin = adminUser();
    $zipPath = storage_path('app/private/plugin-fixtures/zip-slip.zip');

    buildPluginZip($zipPath, 'zip-slip-plugin', [
        '../../evil.php' => '<?php echo "pwned"; ?>',
    ]);

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->set('pluginZip', pluginUploadedFile($zipPath))
        ->call('install');

    expect(Plugin::count())->toBe(0);
    expect(File::exists(base_path('evil.php')))->toBeFalse();
    expect(File::isDirectory(config('plugins.install_path').'/zip-slip-plugin'))->toBeFalse();
});

it('rejects a zip exceeding the configured max extracted size', function () {
    config(['plugins.max_extracted_size_kb' => 1]);

    $admin = adminUser();
    $zipPath = storage_path('app/private/plugin-fixtures/oversized.zip');

    buildPluginZip($zipPath, 'oversized-plugin', [
        'src/filler.bin' => str_repeat('A', 5 * 1024),
    ]);

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->set('pluginZip', pluginUploadedFile($zipPath))
        ->call('install');

    expect(Plugin::count())->toBe(0);
    expect(File::isDirectory(config('plugins.install_path').'/oversized-plugin'))->toBeFalse();
});

it('makes an enabled plugin route reachable and a disabled one 404', function () {
    // Route registration hanya terjadi saat AppServiceProvider::register() jalan
    // (sekali per boot aplikasi/proses). Untuk mensimulasikan "request berikutnya
    // setelah enable" tanpa reboot penuh proses test, panggil PluginBootManager
    // langsung setelah plugin ditempatkan di disk + baris DB dibuat.
    $slug = 'ping-plugin';
    $zipPath = storage_path('app/private/plugin-fixtures/ping.zip');
    buildPluginZip($zipPath, $slug);

    $extractDir = storage_path('app/private/plugin-fixtures/ping-extracted');
    (new PluginZipExtractor)->extract($zipPath, $extractDir, 102400);
    $manifest = (new PluginManifestReader)->read($extractDir);

    File::ensureDirectoryExists(config('plugins.install_path'));
    File::copyDirectory($extractDir, config('plugins.install_path').'/'.$slug);

    $plugin = Plugin::create([
        'name' => $manifest->name,
        'slug' => $manifest->slug,
        'version' => $manifest->version,
        'description' => $manifest->description,
        'provider_class' => $manifest->providerClass,
        'source_path' => pluginSourcePath($slug),
        'has_web_routes' => true,
        'has_api_routes' => false,
        'migrations_relative_path' => pluginSourcePath($slug).'/database/migrations',
        'enabled' => false,
    ]);

    PluginBootManager::bootEnabledPlugins($this->app);
    $this->get('/plugins/'.$slug.'/ping')->assertNotFound();

    $plugin->update(['enabled' => true]);
    PluginBootManager::bootEnabledPlugins($this->app);
    $this->get('/plugins/'.$slug.'/ping')->assertOk()->assertSee('pong');
});

it('runs a plugin migration through the migrate action', function () {
    // Migration fixture di sini sengaja DML-only (insert ke tabel `settings` yang
    // sudah ada), bukan CREATE TABLE — supaya test ini tidak memicu implicit
    // commit MySQL yang merusak transaksi RefreshDatabase untuk test lain di
    // proses yang sama. Ini tetap membuktikan Artisan::call('migrate', ['--path'
    // => ...]) benar-benar menjalankan file migration milik plugin.
    $dmlOnlyMigration = <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->insert([
            'key' => 'plugin_test_plugin_migrated_marker',
            'value' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'plugin_test_plugin_migrated_marker')->delete();
    }
};

PHP;

    $admin = adminUser();
    $zipPath = storage_path('app/private/plugin-fixtures/migrate.zip');
    buildPluginZip($zipPath, migrationBody: $dmlOnlyMigration);

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->set('pluginZip', pluginUploadedFile($zipPath))
        ->call('install');

    $plugin = Plugin::where('slug', 'test-plugin')->firstOrFail();

    expect(DB::table('settings')->where('key', 'plugin_test_plugin_migrated_marker')->exists())->toBeFalse();

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->call('migrate', $plugin->slug);

    expect(DB::table('settings')->where('key', 'plugin_test_plugin_migrated_marker')->exists())->toBeTrue();
    expect($plugin->fresh()->last_migrated_at)->not->toBeNull();
});

it('deletes a plugin when the confirmation slug matches exactly', function () {
    $admin = adminUser();
    $zipPath = storage_path('app/private/plugin-fixtures/to-delete.zip');
    buildPluginZip($zipPath, 'to-delete-plugin');

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->set('pluginZip', pluginUploadedFile($zipPath))
        ->call('install');

    expect(Plugin::where('slug', 'to-delete-plugin')->exists())->toBeTrue();
    expect(File::isDirectory(config('plugins.install_path').'/to-delete-plugin'))->toBeTrue();

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->call('confirmDelete', 'to-delete-plugin')
        ->set('confirmSlugInput', 'to-delete-plugin')
        ->call('destroy');

    expect(Plugin::where('slug', 'to-delete-plugin')->exists())->toBeFalse();
    expect(File::isDirectory(config('plugins.install_path').'/to-delete-plugin'))->toBeFalse();
});

it('tolerates leading/trailing whitespace in the confirmation slug (autofill/mobile keyboard artifacts)', function () {
    $admin = adminUser();
    $zipPath = storage_path('app/private/plugin-fixtures/to-delete-trim.zip');
    buildPluginZip($zipPath, 'to-delete-trim-plugin');

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->set('pluginZip', pluginUploadedFile($zipPath))
        ->call('install');

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->call('confirmDelete', 'to-delete-trim-plugin')
        ->set('confirmSlugInput', '  to-delete-trim-plugin  ')
        ->call('destroy');

    expect(Plugin::where('slug', 'to-delete-trim-plugin')->exists())->toBeFalse();
});

it('refuses to delete a plugin when the confirmation slug does not match', function () {
    $admin = adminUser();
    $zipPath = storage_path('app/private/plugin-fixtures/keep.zip');
    buildPluginZip($zipPath, 'keep-plugin');

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->set('pluginZip', pluginUploadedFile($zipPath))
        ->call('install');

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->call('confirmDelete', 'keep-plugin')
        ->set('confirmSlugInput', 'wrong-slug')
        ->call('destroy');

    expect(Plugin::where('slug', 'keep-plugin')->exists())->toBeTrue();
    expect(File::isDirectory(config('plugins.install_path').'/keep-plugin'))->toBeTrue();
});

it('blocks non-superadmin users from the plugin management page', function () {
    $nonSuperadmin = adminUser('admin_akademik');

    $this->actingAs($nonSuperadmin)->get(route('admin.sistem.plugin'))
        ->assertForbidden();
});

it('redirects unauthenticated users to login', function () {
    $this->get(route('admin.sistem.plugin'))
        ->assertRedirect(route('login'));
});

it('shows a working Pengaturan link when a plugin is enabled and declares a valid settings_route', function () {
    $admin = adminUser();
    $slug = 'with-settings';
    $settingsRouteName = "plugins.{$slug}.edit";
    $zipPath = storage_path('app/private/plugin-fixtures/with-settings.zip');
    buildPluginZip($zipPath, $slug, settingsRoute: $settingsRouteName);

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->set('pluginZip', pluginUploadedFile($zipPath))
        ->call('install');

    $plugin = Plugin::where('slug', $slug)->firstOrFail();
    expect($plugin->settings_route)->toBe($settingsRouteName);
    // Belum enabled -> settingsUrl() harus null meski settings_route terisi,
    // karena route-nya belum tentu terdaftar (provider belum di-boot).
    expect($plugin->settingsUrl())->toBeNull();

    $plugin->update(['enabled' => true]);
    PluginBootManager::bootEnabledPlugins($this->app);

    // PluginBootManager dipanggil manual di tengah proses test yang sudah
    // full-boot (bukan lewat siklus booting() alami) — beda dari request asli,
    // di sini index nama route (dipakai Route::has()/route()) tidak otomatis
    // di-refresh sampai kita minta eksplisit. Di request produksi sungguhan
    // ini terjadi otomatis sebagai bagian akhir siklus boot (sudah diverifikasi
    // manual lewat browser sebelumnya untuk storage-monitor).
    Route::getRoutes()->refreshNameLookups();

    expect($plugin->settingsUrl())->not->toBeNull();
    expect($plugin->settingsUrl())->toContain('plugins/'.$slug.'/settings');

    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->assertSee('plugins/'.$slug.'/settings', false);
});

it('shows the Pengaturan link immediately after clicking Aktifkan, without a page reload', function () {
    $admin = adminUser();
    $slug = 'live-enable-settings';
    $settingsRouteName = "plugins.{$slug}.edit";
    $zipPath = storage_path('app/private/plugin-fixtures/live-enable-settings.zip');
    buildPluginZip($zipPath, $slug, settingsRoute: $settingsRouteName);

    $component = Livewire::actingAs($admin)->test(PluginComponent::class)
        ->set('pluginZip', pluginUploadedFile($zipPath))
        ->call('install');

    // enable() jalan lewat method Livewire yang sesungguhnya (bukan
    // PluginBootManager::bootEnabledPlugins() manual seperti test lain di
    // atas) — reproduksi persis apa yang terjadi saat user klik "Aktifkan"
    // di browser, dalam SATU request yang sama (tanpa reload halaman).
    $component->call('enable', $slug)
        ->assertSee('plugins/'.$slug.'/settings', false);

    expect(Plugin::where('slug', $slug)->firstOrFail()->settingsUrl())
        ->toContain('plugins/'.$slug.'/settings');
});

it('hides the Pengaturan link for a disabled plugin even if settings_route is declared', function () {
    $slug = 'disabled-with-settings';

    Plugin::create([
        'name' => 'Disabled With Settings',
        'slug' => $slug,
        'version' => '1.0.0',
        'provider_class' => 'Plugins\\DisabledWithSettings\\DisabledWithSettingsServiceProvider',
        'source_path' => pluginSourcePath($slug),
        'settings_route' => "plugins.{$slug}.edit",
        'enabled' => false,
    ]);

    $plugin = Plugin::where('slug', $slug)->firstOrFail();

    expect($plugin->settingsUrl())->toBeNull();
});

it('does not crash when a declared settings_route is not actually registered', function () {
    $admin = adminUser();
    $slug = 'broken-settings';

    // settings_route diisi manual di DB (bukan lewat manifest reader, yang
    // memvalidasi format-nya) untuk mensimulasikan plugin yang provider-nya
    // gagal mendaftarkan route yang dijanjikan di manifest-nya.
    Plugin::create([
        'name' => 'Broken Settings',
        'slug' => $slug,
        'version' => '1.0.0',
        'provider_class' => 'Plugins\\BrokenSettings\\BrokenSettingsServiceProvider',
        'source_path' => pluginSourcePath($slug),
        'settings_route' => 'plugins.broken-settings.does-not-exist',
        'enabled' => true,
    ]);

    $plugin = Plugin::where('slug', $slug)->firstOrFail();
    expect($plugin->settingsUrl())->toBeNull();

    // Halaman manajemen tetap harus render normal (tidak 500) walau ada baris
    // plugin dengan settings_route yang rutenya tidak pernah terdaftar.
    Livewire::actingAs($admin)->test(PluginComponent::class)
        ->assertOk()
        ->assertDontSee('does-not-exist');
});
