<?php

namespace App\Services\Plugins;

/**
 * Hasil parsing & validasi plugin.json yang sudah tervalidasi (namespace provider
 * sudah dipastikan cocok dengan slug, class provider sudah dipastikan ada di disk).
 */
class PluginManifest
{
    public function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly string $version,
        public readonly ?string $description,
        public readonly string $providerClass,
        public readonly bool $hasWebRoutes,
        public readonly bool $hasApiRoutes,
        public readonly bool $hasMigrations,
    ) {}
}
