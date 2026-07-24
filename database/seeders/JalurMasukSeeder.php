<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\JalurMasuk;

class JalurMasukSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        JalurMasuk::create([
            'nama' => 'Seleksi Mandiri PTS',
            'deskripsi' => 'Seleksi Mandiri PTS',
            'is_free_of_charge' => false,
            'has_selection' => true,
            'has_interview' => true,
            'has_physical_test' => false,
            'has_psychological_test' => false,
            'has_medical_test' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        JalurMasuk::create([
            'nama' => 'Beasiswa Prestasi',
            'deskripsi' => 'Beasiswa Prestasi',
            'is_free_of_charge' => true,
            'has_selection' => true,
            'has_interview' => true,
            'has_physical_test' => false,
            'has_psychological_test' => false,
            'has_medical_test' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        JalurMasuk::create([
            'nama' => 'PMDK Prestasi non-akademik',
            'deskripsi' => 'PMDK Prestasi non-akademik',
            'is_free_of_charge' => true,
            'has_selection' => true,
            'has_interview' => true,
            'has_physical_test' => false,
            'has_psychological_test' => false,
            'has_medical_test' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        JalurMasuk::create([
            'nama' => 'Ujian Masuk Bersama PTS (UMB PTS)',
            'deskripsi' => 'Ujian Masuk Bersama PTS (UMB PTS)',
            'is_free_of_charge' => false,
            'has_selection' => true,
            'has_interview' => true,
            'has_physical_test' => false,
            'has_psychological_test' => false,
            'has_medical_test' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        JalurMasuk::create([
            'nama' => 'Umum',
            'deskripsi' => 'Umum',
            'is_free_of_charge' => true,
            'has_selection' => true,
            'has_interview' => true,
            'has_physical_test' => false,
            'has_psychological_test' => false,
            'has_medical_test' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        JalurMasuk::create([
            'nama' => 'KIP Kuliah',
            'deskripsi' => 'KIP Kuliah',
            'is_free_of_charge' => true,
            'has_selection' => true,
            'has_interview' => true,
            'has_physical_test' => false,
            'has_psychological_test' => false,
            'has_medical_test' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
    }
}
