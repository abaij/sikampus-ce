<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class FakultasFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => 'Fakultas '.fake()->unique()->word(),
            'kode' => fake()->unique()->lexify('FAK-????'),
        ];
    }
}
