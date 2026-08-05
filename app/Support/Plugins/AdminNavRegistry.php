<?php

namespace App\Support\Plugins;

use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Registry grup menu navbar top-level yang di-push plugin lewat boot()
 * provider-nya — beda dari settings_route (satu tombol di baris plugin
 * sendiri di halaman manajemen plugin), ini grup dropdown BARU yang
 * sejajar dengan "Akademik"/"Administrasi"/dst.
 *
 * push() menerima array literal (bukan Closure seperti DashboardWidgetRegistry)
 * karena plugin cuma menyerahkan DATA (label + nama route) — route() yang
 * sesungguhnya baru dipanggil belakangan di nav.blade.php, saat navbar
 * benar-benar dirender, jauh setelah seluruh app selesai boot. Karena itu
 * all() WAJIB validasi tiap nama route dengan Route::has() sebelum
 * dipercaya, mengikuti pola Plugin::settingsUrl() — dan karena nav.blade.php
 * di-@include di ~100+ halaman admin (bukan cuma satu halaman seperti
 * dashboard widget), satu route plugin yang rusak tidak boleh mematahkan
 * seluruh panel admin, bukan cuma satu halaman.
 */
class AdminNavRegistry
{
    /** @var list<array{label: string, items: array}> */
    private array $groups = [];

    public function push(array $group): void
    {
        $this->groups[] = $group;
    }

    /** @return list<array{label: string, items: array}> */
    public function all(): array
    {
        return array_values(array_filter(array_map(
            fn (array $group) => $this->sanitizeGroup($group),
            $this->groups
        )));
    }

    private function sanitizeGroup(array $group): ?array
    {
        try {
            $label = (string) ($group['label'] ?? '');
            $items = array_values(array_filter(array_map(
                fn ($item) => $this->sanitizeItem($item),
                $group['items'] ?? []
            )));

            if ($label === '' || $items === []) {
                return null;
            }

            return ['label' => $label, 'items' => $items];
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function sanitizeItem(mixed $item): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        if (isset($item['children'])) {
            $label = (string) ($item['label'] ?? '');
            $children = array_values(array_filter(array_map(
                fn ($child) => $this->sanitizeRouteRef($child),
                $item['children'] ?? []
            )));

            return ($label === '' || $children === []) ? null : ['label' => $label, 'children' => $children];
        }

        return $this->sanitizeRouteRef($item);
    }

    private function sanitizeRouteRef(mixed $ref): ?array
    {
        if (! is_array($ref)) {
            return null;
        }

        $route = (string) ($ref['route'] ?? '');
        $label = (string) ($ref['label'] ?? '');

        if ($route === '' || $label === '' || ! Route::has($route)) {
            return null;
        }

        return ['route' => $route, 'label' => $label];
    }
}
