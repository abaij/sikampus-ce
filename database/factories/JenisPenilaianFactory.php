<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class JenisPenilaianFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kode' => fake()->unique()->lexify('JP-????'),
            'nama' => 'Jenis Penilaian '.fake()->unique()->word(),
            'bobot' => 0,
            'status' => 'manual',
        ];
    }
}
