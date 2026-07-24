<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MatkulFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kode' => fake()->unique()->lexify('MK-????'),
            'nama' => 'Mata Kuliah '.fake()->unique()->word(),
            'sks' => 3,
            'semester' => 1,
            'status' => 'active',
        ];
    }
}
