<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tambahkan kolom guard_name jika belum ada
        if (!Schema::hasColumn('roles', 'guard_name')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->string('guard_name')->default('web')->after('name');
            });
        }
        
        // Update existing roles dengan guard_name 'web'
        DB::table('roles')->whereNull('guard_name')->update(['guard_name' => 'web']);
        
        // Hapus unique constraint pada code jika ada, lalu tambahkan unique pada name dan guard_name
        Schema::table('roles', function (Blueprint $table) {
            try {
                $table->dropUnique(['code']);
            } catch (\Exception $e) {
                // Ignore jika constraint tidak ada
            }
        });
        
        Schema::table('roles', function (Blueprint $table) {
            // Tambahkan unique constraint pada name dan guard_name
            try {
                $table->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
            } catch (\Exception $e) {
                // Ignore jika constraint sudah ada
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // Kembalikan unique constraint ke code
            $table->dropUnique(['name', 'guard_name']);
            $table->unique(['code']);
            
            // Hapus kolom guard_name
            $table->dropColumn('guard_name');
        });
    }
};
