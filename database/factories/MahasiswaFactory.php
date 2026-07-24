<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Prodi;

class MahasiswaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'nim' => fake()->unique()->numerify('20#######'),
            'email' => fake()->unique()->safeEmail(),
            'id_prodi' => Prodi::factory(),
        ];
    }
}
