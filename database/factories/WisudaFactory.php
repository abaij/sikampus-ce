<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class WisudaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => 'Wisuda '.fake()->unique()->word(),
            'tanggal_wisuda' => '2026-08-15',
            'status' => 'active',
        ];
    }
}
