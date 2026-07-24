<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\StatusAkademik;

class StatusAkademikSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        StatusAkademik::create([
            'nama' => 'Aktif',
            'deskripsi' => 'Mahasiswa aktif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        StatusAkademik::create([
            'nama' => 'Cuti',
            'deskripsi' => 'Mahasiswa cuti',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        StatusAkademik::create([
            'nama' => 'Lulus',
            'deskripsi' => 'Mahasiswa lulus',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        StatusAkademik::create([
            'nama' => 'Dropout',
            'deskripsi' => 'Mahasiswa dropout',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        StatusAkademik::create([
            'nama' => 'Tidak aktif',
            'deskripsi' => 'Mahasiswa tidak aktif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
