<?php

namespace App\Services\Plugins;

use App\Exceptions\Plugins\PluginInstallException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Parse & validasi plugin.json dari direktori plugin hasil ekstrak. Namespace
 * provider TIDAK dipercaya mentah dari manifest — diturunkan server-side dari
 * slug, lalu field `provider` ditolak kalau tidak diawali prefix itu. Ini
 * mencegah plugin mengklaim namespace `App\` atau namespace plugin lain untuk
 * shadow class asli.
 */
class PluginManifestReader
{
    public function read(string $pluginDir): PluginManifest
    {
        $manifestPath = $pluginDir.DIRECTORY_SEPARATOR.'plugin.json';

        if (! File::exists($manifestPath)) {
            throw new PluginInstallException('Berkas plugin.json tidak ditemukan di root ZIP.');
        }

        $data = json_decode(File::get($manifestPath), true);

        if (! is_array($data)) {
            throw new PluginInstallException('Berkas plugin.json tidak valid (bukan JSON objek).');
        }

        foreach (['name', 'slug', 'version', 'provider'] as $key) {
            if (! isset($data[$key]) || ! is_string($data[$key]) || trim($data[$key]) === '') {
                throw new PluginInstallException("Field \"{$key}\" wajib diisi di plugin.json.");
            }
        }

        $slug = $data['slug'];
        if (preg_match('/^[a-z0-9-]+$/', $slug) !== 1) {
            throw new PluginInstallException('Slug plugin hanya boleh berisi huruf kecil, angka, dan tanda hubung.');
        }

        $expectedNamespacePrefix = 'Plugins\\'.Str::studly($slug).'\\';
        $providerClass = $data['provider'];

        if (! str_starts_with($providerClass, $expectedNamespacePrefix)) {
            throw new PluginInstallException(
                "Namespace provider harus diawali \"{$expectedNamespacePrefix}\" sesuai slug plugin."
            );
        }

        $relativeClassPath = str_replace('\\', DIRECTORY_SEPARATOR, substr($providerClass, strlen($expectedNamespacePrefix)));
        $providerFilePath = $pluginDir.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.$relativeClassPath.'.php';

        if (! File::exists($providerFilePath)) {
            throw new PluginInstallException("Berkas class provider tidak ditemukan: src/{$relativeClassPath}.php");
        }

        return new PluginManifest(
            name: $data['name'],
            slug: $slug,
            version: $data['version'],
            description: isset($data['description']) && is_string($data['description']) ? $data['description'] : null,
            providerClass: $providerClass,
            hasWebRoutes: File::exists($pluginDir.DIRECTORY_SEPARATOR.'routes'.DIRECTORY_SEPARATOR.'web.php'),
            hasApiRoutes: File::exists($pluginDir.DIRECTORY_SEPARATOR.'routes'.DIRECTORY_SEPARATOR.'api.php'),
            hasMigrations: File::isDirectory($pluginDir.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations'),
        );
    }
}
