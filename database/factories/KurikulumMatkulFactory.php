<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Kurikulum;
use App\Models\Matkul;

class KurikulumMatkulFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_kurikulum' => Kurikulum::factory(),
            'id_matkul' => Matkul::factory(),
            'sks' => 3,
            'semester_rekomendasi' => 1,
            'is_wajib' => true,
        ];
    }
}
