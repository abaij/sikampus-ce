<?php

namespace App\Support;

use App\Models\User;

/**
 * Sumber kebenaran tunggal untuk "boleh tidaknya seorang admin membuka satu menu/rute panel".
 * Dipakai bersamaan oleh middleware (penjaga rute) dan navbar (penyembunyi menu) supaya
 * tampilan tidak pernah menjanjikan sesuatu yang fungsinya ditolak, dan sebaliknya.
 */
class PanelAccess
{
    /**
     * Permission yang dibutuhkan untuk sebuah nama rute penuh (mis. "admin.keuangan.tagihan.show").
     * Null berarti rute bebas diakses semua admin (dashboard, profil, dsb).
     */
    public static function permissionFor(?string $routeName): ?string
    {
        if ($routeName === null || ! str_starts_with($routeName, 'admin.')) {
            return null;
        }

        $key = substr($routeName, strlen('admin.'));

        $map = config('panel_access.route_permissions', []);

        if (config('access.granular_permissions')) {
            // Entri granular menang atas entri dasar untuk prefix yang sama (mis. 'keuangan.tagihan'
            // yang di mode dasar berarti 'manage tagihan', di mode granular jadi 'view tagihan'),
            // dan menambah entri baru yang tidak ada di peta dasar (mis. '...tagihan.create').
            $map = array_merge($map, config('panel_access.route_permissions_granular', []));
        }

        $best = null;
        $bestLength = -1;

        foreach ($map as $prefix => $permission) {
            if ($key !== $prefix && ! str_starts_with($key, $prefix.'.')) {
                continue;
            }

            if (strlen($prefix) > $bestLength) {
                $best = $permission;
                $bestLength = strlen($prefix);
            }
        }

        return $best;
    }

    /**
     * Cek hak akses granular per resource+aksi (mis. 'tagihan' + 'delete') — dipakai di dalam
     * komponen Livewire untuk aksi yang tidak lewat rute sendiri (tombol Hapus di halaman index,
     * yang jalan lewat method Livewire, bukan GET ke rute baru sehingga tidak lewat
     * EnsurePanelPermission lagi) dan di Blade untuk menyembunyikan tombol Tambah/Ubah/Hapus.
     *
     * Di mode dasar (GRANULAR_PERMISSIONS=false) semua aksi disamakan ke satu permission
     * "manage {resource}" supaya perilakunya identik dengan skema yang sudah berjalan.
     */
    public static function can(?User $user, string $resource, string $action): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperadmin()) {
            return true;
        }

        if (! config('access.granular_permissions')) {
            return $user->can("manage {$resource}");
        }

        return $user->can("{$action} {$resource}");
    }

    /**
     * Superadmin selalu lolos (agar permission baru yang belum di-seed tidak mengunci dirinya
     * sendiri); selain itu pakai $user->can(), yang menghitung permission dari role MAUPUN
     * permission langsung per user — sehingga pemberian permission per user bisa memperluas
     * akses melewati batas default role-nya.
     */
    public static function allows(?User $user, ?string $routeName): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperadmin()) {
            return true;
        }

        $permission = static::permissionFor($routeName);

        return $permission === null || $user->can($permission);
    }
}
