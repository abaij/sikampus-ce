<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Penghasilan;

class PenghasilanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Penghasilan::create([
            'nama' => 'Kurang dari Rp 1.000.000',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Penghasilan::create([
            'nama' => 'Rp 1.000.000 - Rp 2.000.000',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Penghasilan::create([
            'nama' => 'Lebih dari Rp 2.000.000',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Penghasilan::create([
            'nama' => 'Lainnya',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Penghasilan::create([
            'nama' => 'Kurang dari Rp. 500,000',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Penghasilan::create([
            'nama' => 'Rp. 500,000 - Rp. 999,999',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Penghasilan::create([
            'nama' => 'Rp. 1,000,000 - Rp. 1,999,999',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Penghasilan::create([
            'nama' => 'Rp. 2,000,000 - Rp. 4,999,999',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Penghasilan::create([
            'nama' => 'Rp. 5,000,000 - Rp. 20,000,000',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Penghasilan::create([
            'nama' => 'Lebih dari Rp. 20,000,000',
            'created_by' => 'system',
            'updated_by' => 'system',
            'deleted_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
