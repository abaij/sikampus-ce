<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\JenisKonversiNilai;

class JenisKonversiNilaiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        JenisKonversiNilai::create([
            'nama' => 'Pindahan',
            'keterangan' => 'Konversi nilai pindahan',
            'is_aktif' => true,
            'created_by' => 'system',
            'updated_by' => 'system',
        ]);
        JenisKonversiNilai::create([
            'nama' => 'RPL Perolehan SKS',
            'keterangan' => 'Konversi nilai RPL perolehan SKS',
            'is_aktif' => true,
            'created_by' => 'system',
            'updated_by' => 'system',
        ]);
        JenisKonversiNilai::create([
            'nama' => 'MBKM',
            'keterangan' => 'Konversi nilai MBKM',
            'is_aktif' => true,
            'created_by' => 'system',
            'updated_by' => 'system',
        ]);
        JenisKonversiNilai::create([
            'nama' => 'Lain-lain',
            'keterangan' => 'Konversi nilai lain-lain',
            'is_aktif' => true,
            'created_by' => 'system',
            'updated_by' => 'system',
        ]);
    }
}
