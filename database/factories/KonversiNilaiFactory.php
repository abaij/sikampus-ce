<?php

namespace Database\Factories;

use App\Models\JenisKonversiNilai;
use App\Models\Kurikulum;
use App\Models\Mahasiswa;
use Illuminate\Database\Eloquent\Factories\Factory;

class KonversiNilaiFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_mahasiswa' => Mahasiswa::factory(),
            'id_kurikulum' => Kurikulum::factory(),
            'id_jenis_konversi' => JenisKonversiNilai::factory(),
            'kode_mk_lama' => fake()->unique()->lexify('OLD-????'),
            'nama_mk_lama' => 'Mata Kuliah '.fake()->unique()->word(),
            'sks_lama' => 3,
            'nilai_lama' => 'B',
            'kode_mk_baru' => fake()->unique()->lexify('NEW-????'),
            'nama_mk_baru' => 'Mata Kuliah '.fake()->unique()->word(),
            'sks_baru' => 3,
            'nilai_baru' => 'B',
            'is_approved' => true,
        ];
    }
}
