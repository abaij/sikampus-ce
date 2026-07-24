<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Matkul;

class MatkulPrasyaratFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id_matkul' => Matkul::factory(),
            'id_matkul_prasyarat' => Matkul::factory(),
        ];
    }
}
