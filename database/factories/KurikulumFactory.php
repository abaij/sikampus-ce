<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Prodi;
use App\Models\Semester;

class KurikulumFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_prodi' => Prodi::factory(),
            'kode' => fake()->unique()->lexify('KUR-????'),
            'nama' => 'Kurikulum '.fake()->unique()->word(),
            'id_tahun_berlaku' => Semester::factory(),
            'status' => 'active',
        ];
    }
}
