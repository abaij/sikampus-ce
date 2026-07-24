<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Sebelumnya EnsureUserIsAdmin/EnsureUserHasKeuanganAccess meloloskan users.role
     * legacy ('admin', 'admin_akademik', 'admin_keuangan') sebagai bypass otorisasi tanpa
     * role Spatie. Migration ini memberi role Spatie yang setara ke user lama tersebut
     * (hanya yang belum punya role Spatie sama sekali) supaya mereka tidak kehilangan
     * akses setelah bypass itu dihapus dan Spatie menjadi satu-satunya sumber kebenaran.
     */
    private const MAP = [
        'admin' => 'Superadmin',
        'admin_akademik' => 'Akademik',
        'admin_keuangan' => 'Keuangan',
    ];

    public function up(): void
    {
        $roleIds = [];
        foreach (self::MAP as $roleName) {
            $roleIds[$roleName] = DB::table('roles')
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->value('id');
        }

        foreach (self::MAP as $legacyRole => $spatieRoleName) {
            $roleId = $roleIds[$spatieRoleName] ?? null;

            if (! $roleId) {
                continue;
            }

            $userIds = DB::table('users')
                ->where('role', $legacyRole)
                ->whereNull('deleted_at')
                ->pluck('id');

            foreach ($userIds as $userId) {
                $hasAnySpatieRole = DB::table('model_has_roles')
                    ->where('model_type', 'App\\Models\\User')
                    ->where('model_id', $userId)
                    ->exists();

                if ($hasAnySpatieRole) {
                    continue;
                }

                DB::table('model_has_roles')->insert([
                    'role_id' => $roleId,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $userId,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Data-fix satu arah: tidak ada state "sebelum" yang aman untuk dikembalikan
        // (tidak bisa membedakan assignment hasil migration ini dari assignment manual berikutnya).
    }
};
