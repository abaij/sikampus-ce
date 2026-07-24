<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class JalurMasukFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => 'Jalur '.fake()->unique()->word(),
            'deskripsi' => fake()->sentence(),
            'is_free_of_charge' => false,
            'has_selection' => true,
            'has_interview' => true,
            'has_physical_test' => false,
            'has_psychological_test' => false,
            'has_medical_test' => false,
            'status' => 'active',
        ];
    }
}
