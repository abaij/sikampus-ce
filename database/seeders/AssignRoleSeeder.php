<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AssignRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Cari user & role berdasarkan identitas (bukan id hardcoded) supaya tidak bergantung
        // pada urutan insert. Hanya assign jika user belum punya role Spatie sama sekali,
        // supaya tidak menimpa assignment manual yang sudah dilakukan lewat panel admin.
        $user = User::where('email', 'admin@gmail.com')->first();
        $role = Role::where('code', 'superadmin')->orWhere('name', 'Superadmin')->first();

        if ($user && $role && ! $user->roles()->exists()) {
            $user->syncRoles([$role]);
        }
    }
}
