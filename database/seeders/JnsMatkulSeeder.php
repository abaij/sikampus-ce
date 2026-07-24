<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\JenisMatkul;

class JnsMatkulSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        JenisMatkul::create([
            'kode' => 'MKW',
            'nama' => 'Mata Kuliah Wajib',
            'deskripsi' => 'Mata Kuliah Wajib',
        ]);
        JenisMatkul::create([
            'kode' => 'TA',
            'nama' => 'Mata Kuliah Tugas Akhir',
            'deskripsi' => 'Mata Kuliah Tugas Akhir',
        ]);
    }
}
