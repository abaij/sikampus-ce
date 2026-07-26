<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RuanganFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => 'Ruangan '.fake()->unique()->bothify('?##'),
            'kapasitas' => fake()->numberBetween(10, 100),
        ];
    }
}
