<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PengumumanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'judul' => fake()->sentence(),
            'isi' => fake()->paragraph(),
            'audien' => null,
            'prioritas' => null,
            'tanggal_mulai' => null,
            'tanggal_selesai' => null,
        ];
    }
}
