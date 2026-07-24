<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scope terikat ke pasangan (user, role) selaras dengan Spatie model_has_roles,
     * bukan lagi ke baris user_roles.
     */
    public function up(): void
    {
        Schema::table('user_role_scopes', function (Blueprint $table) {
            $table->unsignedBigInteger('id_user')->nullable()->after('id');
            $table->unsignedBigInteger('id_role')->nullable()->after('id_user');
        });

        $scopes = DB::table('user_role_scopes')->select('id', 'id_user_role')->get();
        foreach ($scopes as $row) {
            $ur = DB::table('user_roles')->where('id', $row->id_user_role)->first();
            if ($ur) {
                DB::table('user_role_scopes')
                    ->where('id', $row->id)
                    ->update([
                        'id_user' => $ur->id_user,
                        'id_role' => $ur->id_role,
                    ]);
            }
        }

        DB::table('user_role_scopes')->whereNull('id_user')->delete();

        Schema::table('user_role_scopes', function (Blueprint $table) {
            $table->dropForeign(['id_user_role']);
        });

        Schema::table('user_role_scopes', function (Blueprint $table) {
            $table->dropColumn('id_user_role');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE user_role_scopes MODIFY id_user BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE user_role_scopes MODIFY id_role BIGINT UNSIGNED NOT NULL');
        }

        Schema::table('user_role_scopes', function (Blueprint $table) use ($driver) {
            if ($driver !== 'mysql') {
                // SQLite / testing: biarkan nullable; integritas dijamin aplikasi
            }
            $table->index(['id_user', 'id_role']);
            $table->foreign('id_user')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('id_role')->references('id')->on('roles')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new \RuntimeException('Migration 2026_04_09_100000_user_role_scopes_add_id_user_id_role tidak dapat di-rollback dengan aman.');
    }
};
