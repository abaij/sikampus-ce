<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class JenisKeringananBiayaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => 'Keringanan '.fake()->unique()->word(),
            'is_persentase' => false,
            'nominal' => 0,
            'is_active' => true,
        ];
    }
}
