<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class JenjangFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kode' => fake()->unique()->lexify('S?'),
            'nama' => 'Sarjana '.fake()->unique()->word(),
        ];
    }
}
