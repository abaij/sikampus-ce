<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\JenisDaftar;

class JenisDaftarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        JenisDaftar::create([
            'nama' => 'Reguler',
            'deskripsi' => 'Regular',
            'status' => 'active',
        ]);
        JenisDaftar::create([
            'nama' => 'Pindahan',
            'deskripsi' => 'Pindahan',
            'status' => 'active',
        ]);
    }
}
