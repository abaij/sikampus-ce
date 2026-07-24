<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Mahasiswa;
use App\Models\JenisKeluar;

class YudisiumFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_mahasiswa' => Mahasiswa::factory(),
            'id_jenis_keluar' => JenisKeluar::factory(),
            'no_sk_yudisium' => 'SK/'.fake()->unique()->numerify('###').'/2026',
            'tanggal_sk_yudisium' => '2026-07-01',
            'no_ijazah' => 'IJZ/'.fake()->unique()->numerify('######'),
            'ipk' => 3.5,
        ];
    }
}
