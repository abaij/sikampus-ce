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

        $best = null;
        $bestLength = -1;

        foreach (config('panel_access.route_permissions', []) as $prefix => $permission) {
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
