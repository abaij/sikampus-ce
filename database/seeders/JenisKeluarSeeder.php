<?php

namespace Database\Seeders;

use App\Models\JenisKeluar;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class JenisKeluarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        JenisKeluar::create([
            'nama' => 'Yudisium',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        JenisKeluar::create([
            'nama' => 'Lulus',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        JenisKeluar::create([
            'nama' => 'Mengundurkan Diri',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        JenisKeluar::create([
            'nama' => 'Ditolak',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        JenisKeluar::create([
            'nama' => 'Lainnya',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
