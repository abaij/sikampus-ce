<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\JenisPenilaian;

class JenisNilaiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        JenisPenilaian::create([
            'kode' => 'UTS',
            'nama' => 'Ujian Tengah Semester',
            'bobot' => 25,
            'status' => 'manual',
        ]);
        JenisPenilaian::create([
            'kode' => 'UAS',
            'nama' => 'Ujian Akhir Semester',
            'bobot' => 25,
            'status' => 'manual',
        ]);
        JenisPenilaian::create([
            'kode' => 'TUGAS',
            'nama' => 'Tugas',
            'bobot' => 25,
            'status' => 'manual',
        ]);
        JenisPenilaian::create([
            'kode' => 'PRESENSI',
            'nama' => 'Kehadiran',
            'bobot' => 25,
            'status' => 'otomatis',
        ]);
    }
}
