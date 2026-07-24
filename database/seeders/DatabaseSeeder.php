<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DatabaseSeeder::call(UserSeeder::class);
        DatabaseSeeder::call(AgamaSeeder::class);
        DatabaseSeeder::call(JenisKuliahSeeder::class);
        DatabaseSeeder::call(StatusAkademikSeeder::class);
        DatabaseSeeder::call(JenisKeluarSeeder::class);
        DatabaseSeeder::call(PermissionSeeder::class);
        DatabaseSeeder::call(PmbUserSeeder::class);
        DatabaseSeeder::call(AssignRoleSeeder::class);
        DatabaseSeeder::call(JenisNilaiSeeder::class);
        DatabaseSeeder::call(JnsMatkulSeeder::class);
        DatabaseSeeder::call(PekerjaanSeeder::class);
        DatabaseSeeder::call(PendidikanSeeder::class);
        DatabaseSeeder::call(PenghasilanSeeder::class);
        DatabaseSeeder::call(JalurMasukSeeder::class);
        DatabaseSeeder::call(JenisDaftarSeeder::class);
       // DatabaseSeeder::call(SuperadminSeeder::class);
    }
}
