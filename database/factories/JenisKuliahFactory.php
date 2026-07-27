<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class JenisKuliahFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => fake()->randomElement(['Teori', 'Praktikum', 'Responsi', 'Seminar']),
            'status' => 'active',
        ];
    }
}
