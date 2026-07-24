<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class KategoriBiayaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => 'Kategori '.fake()->unique()->word(),
            'kode' => fake()->unique()->lexify('KB-????'),
            'status' => 'active',
        ];
    }
}
