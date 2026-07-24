<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\JenisKuliah;

class JenisKuliahSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        JenisKuliah::create([
            'nama' => 'Tatap muka reguler',
            'deskripsi' => 'Materi yang disampaikan secara tatap muka reguler',
            'status' => 'active',
            'is_praktikum' => 0,
            'is_tugas_akhir' => 0,
        ]);
        JenisKuliah::create([
            'nama' => 'Praktikum',
            'deskripsi' => 'Materi yang disampaikan secara praktikum',
            'status' => 'active',
            'is_praktikum' => 1,
            'is_tugas_akhir' => 0,
        ]);
        JenisKuliah::create([
            'nama' => 'Tugas Akhir',
            'deskripsi' => 'Materi yang disampaikan secara tugas akhir',
            'status' => 'active',
            'is_praktikum' => 0,
            'is_tugas_akhir' => 1,
        ]);
    }
}
