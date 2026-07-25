<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class JenisMatkulFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kode' => fake()->unique()->lexify('JM-????'),
            'nama' => 'Jenis Matkul '.fake()->unique()->word(),
        ];
    }
}
