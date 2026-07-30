<?php

use App\Models\Plugin;
use App\Services\Plugins\PluginManifestReader;
use App\Services\Plugins\PluginZipExtractor;
use App\Support\Plugins\PluginBootManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Bangun ZIP fixture plugin yang valid di $zipPath. $extraEntries dipakai untuk
 * menyisipkan entry tambahan (mis. entry zip-slip atau entry berukuran besar)
 * setelah entry manifest/provider yang valid. $migrationBody, kalau diisi,
 * menggantikan isi default migration fixture (dipakai test migrate action agar
 * TIDAK menjalankan DDL asli — CREATE TABLE di MySQL melakukan implicit commit
 * yang merusak transaksi RefreshDatabase untuk sisa test suite berjalan).
 */
function buildPluginZip(string $zipPath, string $slug = 'test-plugin', array $extraEntries = [], ?string $migrationBody = null): void
{
    $studly = str_replace(' ', '', ucwords(str_replace('-', ' ', $slug)));
    $tableSlug = str_replace('-', '_', $slug);

    $entries = [
        'plugin.json' => json_encode([
            'name' => 'Test Plugin',
            'slug' => $slug,
            'version' => '1.0.0',
            'description' => 'Plugin fixture untuk test.',
            'provider' => "Plugins\\{$studly}\\{$studly}ServiceProvider",
        ]),
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

function pluginUploadedFile(string $zipPath): UploadedFile
{
    return new UploadedFile($zipPath, 'plugin.zip', 'application/zip', null, true);
}

afterEach(function () {
    File::deleteDirectory(base_path('plugins'));
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

    $response = $this->actingAs($admin)->post(route('superadmin.plugins.store'), [
        'plugin_zip' => pluginUploadedFile($zipPath),
    ]);

    $response->assertRedirect(route('superadmin.plugins'));
    $response->assertSessionHas('status');

    $plugin = Plugin::where('slug', 'test-plugin')->first();

    expect($plugin)->not->toBeNull();
    expect($plugin->enabled)->toBeFalse();
    expect($plugin->provider_class)->toBe('Plugins\\TestPlugin\\TestPluginServiceProvider');
    expect(File::isDirectory(base_path('plugins/test-plugin')))->toBeTrue();
});

it('rejects a zip-slip attempt and does not write files outside plugins/', function () {
    $admin = adminUser();
    $zipPath = storage_path('app/private/plugin-fixtures/zip-slip.zip');

    buildPluginZip($zipPath, 'zip-slip-plugin', [
        '../../evil.php' => '<?php echo "pwned"; ?>',
    ]);

    $response = $this->actingAs($admin)->post(route('superadmin.plugins.store'), [
        'plugin_zip' => pluginUploadedFile($zipPath),
    ]);

    $response->assertRedirect(route('superadmin.plugins'));
    $response->assertSessionHas('error');

    expect(Plugin::count())->toBe(0);
    expect(File::exists(base_path('evil.php')))->toBeFalse();
    expect(File::isDirectory(base_path('plugins/zip-slip-plugin')))->toBeFalse();
});

it('rejects a zip exceeding the configured max extracted size', function () {
    config(['plugins.max_extracted_size_kb' => 1]);

    $admin = adminUser();
    $zipPath = storage_path('app/private/plugin-fixtures/oversized.zip');

    buildPluginZip($zipPath, 'oversized-plugin', [
        'src/filler.bin' => str_repeat('A', 5 * 1024),
    ]);

    $response = $this->actingAs($admin)->post(route('superadmin.plugins.store'), [
        'plugin_zip' => pluginUploadedFile($zipPath),
    ]);

    $response->assertRedirect(route('superadmin.plugins'));
    $response->assertSessionHas('error');

    expect(Plugin::count())->toBe(0);
    expect(File::isDirectory(base_path('plugins/oversized-plugin')))->toBeFalse();
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

    File::ensureDirectoryExists(base_path('plugins'));
    File::copyDirectory($extractDir, base_path('plugins/'.$slug));

    $plugin = Plugin::create([
        'name' => $manifest->name,
        'slug' => $manifest->slug,
        'version' => $manifest->version,
        'description' => $manifest->description,
        'provider_class' => $manifest->providerClass,
        'source_path' => 'plugins/'.$slug,
        'has_web_routes' => true,
        'has_api_routes' => false,
        'migrations_relative_path' => 'plugins/'.$slug.'/database/migrations',
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

    $this->actingAs($admin)->post(route('superadmin.plugins.store'), [
        'plugin_zip' => pluginUploadedFile($zipPath),
    ]);

    $plugin = Plugin::where('slug', 'test-plugin')->firstOrFail();

    expect(DB::table('settings')->where('key', 'plugin_test_plugin_migrated_marker')->exists())->toBeFalse();

    $response = $this->actingAs($admin)->post(route('superadmin.plugins.migrate', $plugin));

    $response->assertRedirect(route('superadmin.plugins'));
    expect(DB::table('settings')->where('key', 'plugin_test_plugin_migrated_marker')->exists())->toBeTrue();
    expect($plugin->fresh()->last_migrated_at)->not->toBeNull();
});

it('blocks non-superadmin users from the plugin management routes', function () {
    $nonSuperadmin = adminUser('admin_akademik');
    $plugin = Plugin::create([
        'name' => 'Dummy',
        'slug' => 'dummy-plugin',
        'version' => '1.0.0',
        'provider_class' => 'Plugins\\DummyPlugin\\DummyPluginServiceProvider',
        'source_path' => 'plugins/dummy-plugin',
        'enabled' => false,
    ]);

    $this->actingAs($nonSuperadmin)->get(route('superadmin.plugins'))
        ->assertRedirect(route('login'));

    $this->actingAs($nonSuperadmin)->post(route('superadmin.plugins.store'))
        ->assertRedirect(route('login'));

    $this->actingAs($nonSuperadmin)->patch(route('superadmin.plugins.enable', $plugin))
        ->assertRedirect(route('login'));

    $this->actingAs($nonSuperadmin)->delete(route('superadmin.plugins.destroy', $plugin))
        ->assertRedirect(route('login'));
});
