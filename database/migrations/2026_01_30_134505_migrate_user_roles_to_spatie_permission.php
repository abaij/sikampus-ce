<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Migrasi data dari user_roles ke model_has_roles (Spatie Permission)
     */
    public function up(): void
    {
        // Migrasi data dari user_roles ke model_has_roles
        $userRoles = DB::table('user_roles')->get();
        
        foreach ($userRoles as $userRole) {
            // Cek apakah sudah ada di model_has_roles
            $exists = DB::table('model_has_roles')
                ->where('model_type', 'App\Models\User')
                ->where('model_id', $userRole->id_user)
                ->where('role_id', $userRole->id_role)
                ->exists();
            
            if (!$exists) {
                DB::table('model_has_roles')->insert([
                    'role_id' => $userRole->id_role,
                    'model_type' => 'App\Models\User',
                    'model_id' => $userRole->id_user,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Hapus data dari model_has_roles yang berasal dari user_roles
        $userRoles = DB::table('user_roles')->pluck('id_user', 'id_role');
        
        foreach ($userRoles as $roleId => $userId) {
            DB::table('model_has_roles')
                ->where('model_type', 'App\Models\User')
                ->where('model_id', $userId)
                ->where('role_id', $roleId)
                ->delete();
        }
    }
};
