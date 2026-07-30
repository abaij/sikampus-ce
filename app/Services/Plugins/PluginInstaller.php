<?php

namespace App\Services\Plugins;

use App\Exceptions\Plugins\PluginInstallException;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Orkestrasi install plugin: simpan ZIP upload -> extract ke scratch dir (aman
 * dari zip-slip/zip-bomb) -> validasi manifest -> pindahkan ke plugins/<slug> ->
 * catat di tabel `plugins` dengan enabled=false (install tidak otomatis aktif).
 */
class PluginInstaller
{
    public function __construct(
        private readonly PluginZipExtractor $extractor,
        private readonly PluginManifestReader $manifestReader,
    ) {}

    public function install(UploadedFile $file, ?User $installedBy): Plugin
    {
        $diskName = config('plugins.upload_disk');
        $disk = Storage::disk($diskName);

        $uploadedRelativePath = $file->store('plugin-uploads', $diskName);

        if ($uploadedRelativePath === false) {
            throw new PluginInstallException('Gagal menyimpan berkas ZIP yang diunggah.');
        }

        $uploadedAbsolutePath = $disk->path($uploadedRelativePath);
        $checksum = hash_file('sha256', $uploadedAbsolutePath) ?: null;
        $scratchDir = storage_path('app/private/plugin-tmp/'.(string) Str::uuid());

        try {
            $this->extractor->extract($uploadedAbsolutePath, $scratchDir, (int) config('plugins.max_extracted_size_kb'));

            $manifest = $this->manifestReader->read($scratchDir);

            if (Plugin::where('slug', $manifest->slug)->exists()) {
                throw new PluginInstallException("Plugin dengan slug \"{$manifest->slug}\" sudah terinstal.");
            }

            $installPath = config('plugins.install_path');
            $targetDir = $installPath.DIRECTORY_SEPARATOR.$manifest->slug;

            if (File::exists($targetDir)) {
                throw new PluginInstallException(
                    "Direktori plugins/{$manifest->slug} sudah ada di disk (kemungkinan sisa instalasi sebelumnya yang gagal). Hapus direktori tersebut secara manual sebelum instal ulang."
                );
            }

            File::ensureDirectoryExists($installPath);
            File::moveDirectory($scratchDir, $targetDir);

            $migrationsRelativePath = $manifest->hasMigrations
                ? 'plugins/'.$manifest->slug.'/database/migrations'
                : null;

            $plugin = Plugin::create([
                'name' => $manifest->name,
                'slug' => $manifest->slug,
                'version' => $manifest->version,
                'description' => $manifest->description,
                'provider_class' => $manifest->providerClass,
                'source_path' => 'plugins/'.$manifest->slug,
                'has_web_routes' => $manifest->hasWebRoutes,
                'has_api_routes' => $manifest->hasApiRoutes,
                'migrations_relative_path' => $migrationsRelativePath,
                'checksum' => $checksum,
                'enabled' => false,
                'id_user' => $installedBy?->id,
                'installed_at' => now(),
            ]);

            if (function_exists('opcache_reset')) {
                opcache_reset();
            }

            return $plugin;
        } finally {
            if (File::isDirectory($scratchDir)) {
                File::deleteDirectory($scratchDir);
            }

            $disk->delete($uploadedRelativePath);
        }
    }
}
