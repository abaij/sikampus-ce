<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

class Plugin extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'version',
        'description',
        'provider_class',
        'source_path',
        'has_web_routes',
        'has_api_routes',
        'migrations_relative_path',
        'settings_route',
        'checksum',
        'enabled',
        'id_user',
        'installed_at',
        'disabled_at',
        'last_migrated_at',
    ];

    protected $casts = [
        'has_web_routes' => 'boolean',
        'has_api_routes' => 'boolean',
        'enabled' => 'boolean',
        'installed_at' => 'datetime',
        'disabled_at' => 'datetime',
        'last_migrated_at' => 'datetime',
    ];

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function sourceAbsolutePath(): string
    {
        return base_path($this->source_path);
    }

    public function migrationsAbsolutePath(): ?string
    {
        return $this->migrations_relative_path ? base_path($this->migrations_relative_path) : null;
    }

    /**
     * URL halaman setting plugin ini, atau null kalau tidak boleh/tidak bisa
     * ditampilkan sekarang. Dua syarat SEKALIGUS: plugin harus enabled (kalau
     * tidak, PluginBootManager tidak pernah memanggil boot() provider-nya,
     * jadi route-nya belum tentu terdaftar) DAN route-nya harus benar-benar
     * ada di router saat ini (Route::has(), bukan asumsi dari kolom DB) —
     * supaya satu plugin yang settings_route-nya rusak/hilang tidak
     * menjatuhkan seluruh halaman manajemen plugin lewat RouteNotFoundException.
     */
    public function settingsUrl(): ?string
    {
        if (! $this->enabled || ! $this->settings_route || ! Route::has($this->settings_route)) {
            return null;
        }

        return route($this->settings_route);
    }
}
